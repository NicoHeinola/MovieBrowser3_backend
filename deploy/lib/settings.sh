# shellcheck shell=bash
# The installer's settings: asked, prefilled with the saved answer, or taken as they are with --update.
# Sets INSTALL_DIR, BACKEND_REPO, FRONTEND_REPO, MEDIA_DIRS, WEB_PORT, WEB_BIND, DB_PORT and VLC_BRIDGE_PORT.

configure() {
    has_tty || { TTY=false; ASK_SETTINGS=false; }

    ask_install_dir

    local saved
    saved=$(env_get MOVIEBROWSER3_BACKEND_REPO)
    ask BACKEND_REPO "Backend repo" "${MOVIEBROWSER3_BACKEND_REPO:-${saved:-$DEFAULT_BACKEND_REPO}}"
    saved=$(env_get MOVIEBROWSER3_FRONTEND_REPO)
    ask FRONTEND_REPO "Frontend repo" "${MOVIEBROWSER3_FRONTEND_REPO:-${saved:-$DEFAULT_FRONTEND_REPO}}"

    ask_media_dirs

    local lan
    saved=$(env_get WEB_PORT)
    ask_port WEB_PORT "App port" "${saved:-8780}"
    saved=$(env_get WEB_BIND)
    ask lan "Reachable from other devices on the network? (y/n)" "$([[ "$saved" == 0.0.0.0 ]] && echo y || echo n)"
    [[ "$lan" == [yY]* ]] && WEB_BIND=0.0.0.0 || WEB_BIND=127.0.0.1

    saved=$(env_get DB_PORT)
    ask_port DB_PORT "Database port (localhost only)" "${saved:-7564}"
    saved=$(env_get VLC_BRIDGE_PORT)
    ask_port VLC_BRIDGE_PORT "VLC bridge port (localhost only)" "${saved:-8767}"
}

# The install folder comes first: every other saved answer is read from its .env
ask_install_dir() {
    local saved_dir="" dir
    [[ -f "$INSTALL_DIR_POINTER" ]] && saved_dir=$(<"$INSTALL_DIR_POINTER")

    ask dir "Install folder" "${MOVIEBROWSER3_DIR:-${saved_dir:-$DEFAULT_INSTALL_DIR}}"
    dir=${dir/#\~/$HOME}
    [[ "$dir" == /* ]] || fail "The install folder needs an absolute path: $dir"
    # The .bat, the bridge task and the systemd unit all run commands from this path, unquoted
    [[ "$dir" != *[[:space:]]* ]] || fail "The install folder can't contain spaces: $dir"
    INSTALL_DIR=${dir%/}

    if [[ -n "$saved_dir" && "$saved_dir" != "$INSTALL_DIR" && -f "$saved_dir/.env" ]]; then
        warn "This is a new install next to the one in $saved_dir. Stop that one first ($saved_dir/moviebrowser3 stop), or the ports clash."
    fi

    [[ "$UPDATE" == false || -f "$INSTALL_DIR/.env" ]] || fail "Nothing installed in $INSTALL_DIR yet. Run install.sh first."
}

# One prompt per media folder, prefilled with the saved ones (clear a line to drop it), then empty ones to add more
ask_media_dirs() {
    local saved=${MOVIEBROWSER3_MEDIA_DIRS:-$(env_get MOVIEBROWSER3_MEDIA_DIRS)} existing=() dir number=1
    [[ -n "$saved" ]] && IFS=: read -r -a existing <<<"$saved"
    MEDIA_DIRS=()

    [[ "$ASK_SETTINGS" == true ]] && info "Media folders: the folders with your videos, e.g. /mnt/g/New_Vid. Tab completes paths."

    for dir in "${existing[@]}"; do
        ask dir "Media folder $number (clear to remove)" "$dir"
        add_media_dir "$dir" && number=$((number + 1))
    done

    while [[ "$ASK_SETTINGS" == true ]]; do
        ask dir "Media folder $number (empty to finish)" ""
        [[ -n "$dir" ]] || break
        add_media_dir "$dir" && number=$((number + 1))
    done

    ((${#MEDIA_DIRS[@]} > 0)) || warn "No media folders. Re-run install.sh to add them."
}

# Adds a folder to MEDIA_DIRS, or warns and returns 1 when it can't be one
add_media_dir() {
    local dir=${1/#\~/$HOME}
    [[ -n "$dir" ]] || return 1
    dir=${dir%/}

    [[ "$dir" == /* ]] || { warn "Skipped $dir: media folders need an absolute path."; return 1; }
    # MOVIEBROWSER3_MEDIA_DIRS separates folders with ':'
    [[ "$dir" != *:* ]] || { warn "Skipped $dir: a media folder can't contain ':'."; return 1; }

    # /mnt/<letter> is a Windows drive. An external one is only there if it was plugged in when WSL started.
    if [[ "$dir" =~ ^/mnt/([a-z])(/|$) ]] && ! mountpoint -q "/mnt/${BASH_REMATCH[1]}"; then
        warn "Drive ${BASH_REMATCH[1]^^}: isn't mounted in WSL, so $dir is empty for now. See step 5 of deploy/README.md."
    elif [[ ! -d "$dir" ]]; then
        warn "$dir does not exist (yet)."
    fi

    MEDIA_DIRS+=("$dir")
}
