#!/usr/bin/env python3
"""MovieBrowser3 host bridge.

The backend runs in a container that cannot see vlc.exe or run wslpath and robocopy, so this small host-side server does
the only host-bound work: start VLC with its web interface, convert Linux paths to Windows paths, and copy files between
Windows drives. It listens on 127.0.0.1 only, checks a shared bearer token, only ever starts a binary named vlc or
vlc.exe, and only copies inside the media folders.

    POST /launch         {"binary": str, "port": int, "password": str}  -> 204
    POST /windows-paths  {"paths": [str, ...]}                          -> 200 {"paths": [str, ...]}
    POST /check-binary   {"binary": str}                                -> 204 when it's a VLC that exists
    POST /copy-file      {"from": str, "to": str}                       -> 204 once "to" is complete

/copy-file answers when the copy is done, which can take minutes. A file on the same drive gets a second name (a hard
link) instead, which takes no time or space; two media folders on one drive are separate mounts in a container, so only
the bridge can link those.

Bad or missing token: 403. Invalid input or a failed copy: 422 {"message"}, plus "code" (vlc_not_found or
vlc_wrong_binary) when the binary is the problem. Anything else: 404.

Settings come from the environment, or from the .env file next to this script (as written by install.sh):
HOST_BRIDGE_TOKEN (required), HOST_BRIDGE_PORT (default 8767) and MOVIEBROWSER3_MEDIA_DIRS (":"-separated, read again for
every copy). Python 3 standard library only.
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
    """Invalid input or a failed copy, answered with 422, the message and the code when it has one."""

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


def windows_path(path):
    result = subprocess.run(["wslpath", "-w", path], capture_output=True, text=True)
    if result.returncode != 0:
        raise BadRequest(f"Could not convert {path} to a Windows path: {result.stderr.strip()}")
    return result.stdout.strip()


def windows_paths(body):
    paths = body.get("paths")
    if not isinstance(paths, list) or not all(isinstance(path, str) and path for path in paths):
        raise BadRequest("paths must be a list of non-empty strings.")

    return {"paths": [windows_path(path) for path in paths]}


def find_robocopy():
    # A bridge started without the Windows PATH still finds it where Windows keeps it
    fallback = "/mnt/c/Windows/System32/robocopy.exe"
    return shutil.which("robocopy.exe") or (fallback if os.path.isfile(fallback) else None)


def media_path(body, name):
    """Returns the real path of a file in a media folder, or raises BadRequest. Symlinks can't lead out of the folders."""
    path = body.get(name)
    if not isinstance(path, str) or not os.path.isabs(path) or os.path.basename(path) in ("", ".", ".."):
        raise BadRequest(f"{name} must be the full path of a file.")

    real = os.path.realpath(path)
    saved = os.environ.get("MOVIEBROWSER3_MEDIA_DIRS") or read_env_file(ENV_PATH).get("MOVIEBROWSER3_MEDIA_DIRS", "")
    for folder in (os.path.realpath(folder) for folder in saved.split(":") if folder):
        if real != folder and os.path.commonpath([real, folder]) == folder:
            return real
    raise BadRequest(f"{path} is not in a media folder.")


def robocopy(source, folder):
    """Copies a file into a folder with robocopy, which keeps its name, and returns the copy's path."""
    program = find_robocopy()
    if program is None:
        raise BadRequest("This machine has no robocopy.")

    name = os.path.basename(source)
    # /J: unbuffered, for big files. /R:0 /W:0: robocopy otherwise retries a vanished drive a million times
    options = ("/J", "/R:0", "/W:0", "/NP", "/NJH", "/NJS", "/NDL")
    result = subprocess.run(
        [program, windows_path(os.path.dirname(source)), windows_path(folder), name, *options],
        stdin=subprocess.DEVNULL,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        start_new_session=True,
    )
    # The exit code is a bit mask: 8 and up is a failure, and 1 means a file was copied
    if result.returncode >= 8 or not result.returncode & 1:
        output = result.stdout.decode(errors="replace").strip()
        raise BadRequest(f"Robocopy failed (exit code {result.returncode}): {output[:500]}")

    copied = os.path.join(folder, name)
    if os.path.getsize(copied) != os.path.getsize(source):
        raise BadRequest("The copy's size does not match the original.")
    return copied


def copy_file(body):
    source, target = media_path(body, "from"), media_path(body, "to")
    if os.path.lexists(target):
        raise BadRequest(f"{target} already exists.")

    try:
        os.link(source, target)
        return
    except OSError:
        # Another drive, or one without hard links: copy it
        pass

    # A folder of its own, so a half-written copy never has the target's name. What's in there was cut off. When it still
    # runs, robocopy can't share the file and fails, and the caller copies it some other way
    folder = f"{target}.copying"
    shutil.rmtree(folder, ignore_errors=True)
    os.mkdir(folder)
    try:
        copied = robocopy(source, folder)
        if os.path.lexists(target):
            raise BadRequest(f"{target} already exists.")
        os.rename(copied, target)
    finally:
        shutil.rmtree(folder, ignore_errors=True)


ROUTES = {
    "/launch": launch,
    "/windows-paths": windows_paths,
    "/check-binary": check_binary,
    "/copy-file": copy_file,
}


class Handler(BaseHTTPRequestHandler):
    server_version = "MovieBrowser3HostBridge"

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
            return self.respond(422, {"message": str(error)})

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
        sys.stderr.write(f"host-bridge: {self.address_string()} {format % args}\n")


ENV_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), ".env")
ENV_FILE = read_env_file(ENV_PATH)
TOKEN = setting("HOST_BRIDGE_TOKEN")
PORT = int(setting("HOST_BRIDGE_PORT", "8767"))

if __name__ == "__main__":
    if not TOKEN:
        sys.exit("host-bridge: HOST_BRIDGE_TOKEN is not set (environment or .env next to this script).")
    print(f"host-bridge: listening on 127.0.0.1:{PORT}", file=sys.stderr, flush=True)
    ThreadingHTTPServer(("127.0.0.1", PORT), Handler).serve_forever()
