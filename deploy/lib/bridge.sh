# shellcheck shell=bash
# The host-side VLC bridge (vlc_bridge.py), shared by install.sh and the moviebrowser3 command. Needs common.sh and
# env.sh, and INSTALL_DIR set to the install folder.
#
# On WSL it's a Windows logon task (windows/register-vlc-bridge.ps1) that runs the bridge through wsl.exe, so VLC
# shows on the desktop. The task fires only at logon, so after `wsl --shutdown` the bridge stays down until
# start_bridge runs. On Linux it's a systemd user service.

BRIDGE_TASK='MovieBrowser3 VLC bridge'
BRIDGE_SERVICE=moviebrowser3-vlc-bridge.service

# Their checksum, plus the port, decides when install_bridge re-registers (paths relative to the install folder)
BRIDGE_FILES=(vlc_bridge.py windows/register-vlc-bridge.ps1 lib/bridge.sh)

# powershell.exe started from a \\wsl$ folder warns about UNC paths, so it runs from C:
run_powershell() {
    (
        cd /mnt/c 2>/dev/null || true
        powershell.exe -NoProfile "$@"
    )
}

# True when something listens on the bridge port. The redirect opens the port and closes it again; the timeout keeps
# a connect that never answers (a firewall dropping it) from hanging the command.
bridge_up() {
    local port
    port=$(env_get VLC_BRIDGE_PORT)

    timeout 2 bash -c ': <>"/dev/tcp/127.0.0.1/$0"' "${port:-8767}" 2>/dev/null
}

start_bridge() {
    if bridge_up; then
        done_line "VLC bridge" "already running"
        return 0
    fi

    if is_wsl; then
        run_powershell -Command "Start-ScheduledTask -TaskName '$BRIDGE_TASK'" >/dev/null 2>&1 ||
            { warn "Could not start the VLC bridge. Run '$BRIDGE_TASK' from Task Scheduler."; return 0; }
    else
        systemctl --user start "$BRIDGE_SERVICE" 2>/dev/null ||
            { warn "Could not start the VLC bridge ($BRIDGE_SERVICE)."; return 0; }
    fi
    done_line "VLC bridge" "started"
}

stop_bridge() {
    local was_up=false
    bridge_up && was_up=true

    if is_wsl; then
        # Ending the task stops wsl.exe. pkill catches a bridge that outlives it or was started by hand.
        run_powershell -Command "Stop-ScheduledTask -TaskName '$BRIDGE_TASK' -ErrorAction SilentlyContinue" >/dev/null || true
        pkill -f "python3 $INSTALL_DIR/vlc_bridge.py" 2>/dev/null || true
    else
        systemctl --user stop "$BRIDGE_SERVICE" 2>/dev/null || true
    fi

    # Wait for the port to close, so a start right after doesn't take the old bridge for a running one
    local _
    for _ in {1..20}; do
        if ! bridge_up; then
            [[ "$was_up" == false ]] || done_line "VLC bridge" "stopped"
            return 0
        fi
        sleep 0.25
    done
    warn "The VLC bridge still answers on its port."
}

# Registers and restarts the bridge when it differs from the last one registered: other files, another port, or never
# registered. .bridge-installed holds that checksum, written only after registering worked, so a failed run retries.
# Otherwise it only makes sure the bridge runs.
install_bridge() {
    local stamp="$INSTALL_DIR/.bridge-installed" sum
    sum="$(cd "$INSTALL_DIR" && cat "${BRIDGE_FILES[@]}" | cksum) port $(env_get VLC_BRIDGE_PORT)"

    if [[ -f "$stamp" && "$(<"$stamp")" == "$sum" ]]; then
        start_bridge
        return
    fi

    # Stop the old bridge, so the restarted one runs the new script
    stop_bridge
    if is_wsl; then
        register_bridge_task
    else
        register_bridge_service
    fi
    printf '%s\n' "$sum" >"$stamp"
}

register_bridge_task() {
    command -v powershell.exe >/dev/null ||
        fail "No WSL interop (powershell.exe), so the VLC bridge can't be registered. See TECHNICAL.md."

    run_quiet "VLC bridge logon task, registered and started" \
        run_powershell -ExecutionPolicy Bypass -File "$(wslpath -w "$INSTALL_DIR/windows/register-vlc-bridge.ps1")" \
        -Distro "$WSL_DISTRO_NAME" -BridgeScript "$INSTALL_DIR/vlc_bridge.py" ||
        fail "Registering the VLC bridge logon task failed. Re-run the update to try again."
}

register_bridge_service() {
    command -v systemctl >/dev/null ||
        fail "No systemd, so the VLC bridge can't be installed as a service. Start it yourself: python3 $INSTALL_DIR/vlc_bridge.py"

    mkdir -p "$HOME/.config/systemd/user"
    cat >"$HOME/.config/systemd/user/$BRIDGE_SERVICE" <<UNIT
[Unit]
Description=MovieBrowser3 VLC bridge
After=graphical-session.target

[Service]
ExecStart=/usr/bin/env python3 $INSTALL_DIR/vlc_bridge.py
Restart=on-failure

[Install]
WantedBy=default.target
UNIT

    run_quiet "VLC bridge user service, installed and started" bash -c \
        'systemctl --user daemon-reload && systemctl --user enable "$1" && systemctl --user restart "$1"' _ "$BRIDGE_SERVICE" ||
        fail "Installing the VLC bridge user service failed. Re-run the update to try again."
}
