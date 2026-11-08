# shellcheck shell=bash
# What the installer checks before it changes anything.

# WSL2 with mirrored networking (only warned about), git, Docker with Compose, and python3 for the bridge
check_host() {
    if is_wsl; then
        grep -qi wsl2 /proc/sys/kernel/osrelease || warn "This looks like WSL1. MovieBrowser3 needs WSL2."

        local mode
        mode=$(wslinfo --networking-mode 2>/dev/null || true)
        if [[ -z "$mode" ]]; then
            warn "Could not check the WSL networking mode (no wslinfo). VLC tracking needs networkingMode=mirrored."
        elif [[ "$mode" != "mirrored" ]]; then
            warn "WSL networking is '$mode'. Set networkingMode=mirrored under [wsl2] in %UserProfile%\\.wslconfig, then run 'wsl --shutdown'."
        fi
    fi

    command -v git >/dev/null || fail "git is not installed."
    command -v docker >/dev/null || fail "Docker is not installed. Install Docker Engine (inside WSL on Windows) and re-run."
    docker compose version >/dev/null 2>&1 || fail "The Docker Compose plugin is missing ('docker compose')."
    docker info >/dev/null 2>&1 || fail "Cannot reach the Docker daemon. Is it running, and is $USER in the docker group?"
    command -v python3 >/dev/null || fail "python3 is needed for the VLC bridge."
}

# Every install uses the Docker Compose project "moviebrowser3", so a second install on this machine would take over
# (and `down` would remove) the first one's containers
check_other_install() {
    local project=${COMPOSE_PROJECT_NAME:-moviebrowser3} others
    others=$(docker ps -a --filter "label=com.docker.compose.project=$project" \
        --format '{{.Label "com.docker.compose.project.working_dir"}}' | sort -u | grep -vxF "$INSTALL_DIR" || true)

    [[ -z "$others" ]] ||
        fail "Another MovieBrowser3 install has containers on this machine: $others. Stop it first ($others/moviebrowser3 stop), then re-run."
}
