#!/usr/bin/env python3
"""MovieBrowser3 VLC bridge.

The backend runs in a container that cannot see vlc.exe or run wslpath, so this small host-side server does the only
host-bound work: start VLC with its web interface, and convert Linux paths to Windows paths. It listens on 127.0.0.1
only, checks a shared bearer token, and only ever starts a binary named vlc or vlc.exe.

    POST /launch         {"binary": str, "port": int, "password": str}  -> 204
    POST /windows-paths  {"paths": [str, ...]}                          -> 200 {"paths": [str, ...]}
    POST /check-binary   {"binary": str}                                -> 204 when it's a VLC that exists

Bad or missing token: 403. Invalid input: 422 {"message"}, plus "code" (vlc_not_found or vlc_wrong_binary) when the
binary is the problem. Anything else: 404.

Settings come from the environment, or from the .env file next to this script (as written by install.sh):
VLC_BRIDGE_TOKEN (required) and VLC_BRIDGE_PORT (default 8767). Python 3 standard library only.
"""

import hmac
import json
import os
import shutil
import subprocess
import sys
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

ALLOWED_BINARIES = {"vlc", "vlc.exe"}
MAX_BODY_BYTES = 1024 * 1024


class BadRequest(Exception):
    """Invalid input, answered with 422, the message and the code when it has one."""

    def __init__(self, message, code=None):
        super().__init__(message)
        self.code = code


def read_env_file(path):
    """Reads KEY=value lines from a .env file, ignoring comments and surrounding quotes."""
    values = {}
    if not os.path.isfile(path):
        return values
    with open(path, encoding="utf-8") as file:
        for line in file:
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, value = line.split("=", 1)
            values[key.strip()] = value.strip().strip("'\"")
    return values


def setting(name, default=None):
    return os.environ.get(name) or ENV_FILE.get(name) or default


def resolve_binary(binary):
    """Returns the VLC executable to start, or raises BadRequest when it is missing or is not VLC."""
    if not isinstance(binary, str) or not binary:
        raise BadRequest("binary must be a non-empty string.")
    if os.path.basename(binary).lower() not in ALLOWED_BINARIES:
        raise BadRequest(f"{binary} is not VLC. Only vlc or vlc.exe can be started.", "vlc_wrong_binary")
    # A bare name such as "vlc" is looked up on the PATH, like the backend does without the bridge
    path = binary if "/" in binary else shutil.which(binary)
    if path is None or not os.path.isfile(path):
        raise BadRequest(f"VLC was not found at {binary}. Check the vlc_media_player_path setting.", "vlc_not_found")
    return path


def launch(body):
    binary = resolve_binary(body.get("binary"))
    port = body.get("port")
    password = body.get("password")
    if not isinstance(port, int) or isinstance(port, bool) or not 1 <= port <= 65535:
        raise BadRequest("port must be a number from 1 to 65535.")
    if not isinstance(password, str) or not password:
        raise BadRequest("password must be a non-empty string.")

    # Detached in its own session so VLC outlives the request and this bridge
    subprocess.Popen(
        [
            binary,
            "--extraintf",
            "http",
            "--http-host",
            "127.0.0.1",
            "--http-port",
            str(port),
            "--http-password",
            password,
        ],
        stdin=subprocess.DEVNULL,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
        start_new_session=True,
    )


def check_binary(body):
    resolve_binary(body.get("binary"))


def windows_paths(body):
    paths = body.get("paths")
    if not isinstance(paths, list) or not all(isinstance(path, str) and path for path in paths):
        raise BadRequest("paths must be a list of non-empty strings.")

    converted = []
    for path in paths:
        result = subprocess.run(["wslpath", "-w", path], capture_output=True, text=True)
        if result.returncode != 0:
            raise BadRequest(f"Could not convert {path} to a Windows path: {result.stderr.strip()}")
        converted.append(result.stdout.strip())
    return {"paths": converted}


ROUTES = {"/launch": launch, "/windows-paths": windows_paths, "/check-binary": check_binary}


class Handler(BaseHTTPRequestHandler):
    server_version = "MovieBrowser3VlcBridge"

    def do_POST(self):
        route = ROUTES.get(self.path)
        if route is None:
            return self.respond(404, {"message": "Not found."})
        if not self.has_valid_token():
            return self.respond(403, {"message": "Forbidden."})

        try:
            result = route(self.read_json())
        except BadRequest as error:
            body = {"message": str(error)}
            if error.code:
                body["code"] = error.code
            return self.respond(422, body)
        except OSError as error:
            return self.respond(422, {"message": f"VLC could not be started: {error}"})

        if result is None:
            self.send_response(204)
            self.end_headers()
        else:
            self.respond(200, result)

    def do_GET(self):
        self.respond(404, {"message": "Not found."})

    do_PUT = do_PATCH = do_DELETE = do_GET

    def has_valid_token(self):
        header = self.headers.get("Authorization", "")
        return hmac.compare_digest(header.encode(), f"Bearer {TOKEN}".encode())

    def read_json(self):
        try:
            length = int(self.headers.get("Content-Length", "0"))
        except ValueError:
            raise BadRequest("Invalid Content-Length.")
        if length > MAX_BODY_BYTES:
            raise BadRequest("Body is too large.")
        try:
            body = json.loads(self.rfile.read(length) or b"{}")
        except ValueError:
            raise BadRequest("Body must be JSON.")
        if not isinstance(body, dict):
            raise BadRequest("Body must be a JSON object.")
        return body

    def respond(self, status, body):
        data = json.dumps(body).encode()
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def log_message(self, format, *args):
        sys.stderr.write(f"vlc-bridge: {self.address_string()} {format % args}\n")


ENV_FILE = read_env_file(os.path.join(os.path.dirname(os.path.abspath(__file__)), ".env"))
TOKEN = setting("VLC_BRIDGE_TOKEN")
PORT = int(setting("VLC_BRIDGE_PORT", "8767"))

if __name__ == "__main__":
    if not TOKEN:
        sys.exit("vlc-bridge: VLC_BRIDGE_TOKEN is not set (environment or .env next to this script).")
    print(f"vlc-bridge: listening on 127.0.0.1:{PORT}", file=sys.stderr, flush=True)
    ThreadingHTTPServer(("127.0.0.1", PORT), Handler).serve_forever()
