#!/usr/bin/env bash
# MovieBrowser3 installer and updater. It asks for every setting (install folder, repos, media folders, ports),
# prefilled with the saved answer, so Enter keeps it. Answers are saved in the install folder's .env, and
# ~/.config/moviebrowser3/install-dir remembers that folder. Then it clones (or fast-forwards) both repos, builds the
# frontend and the backend image from those clones, migrates and restarts. .env starts from deploy/.env.production.
# Safe to re-run: it keeps the secrets in .env and the database.
#
#   curl -fsSL https://raw.githubusercontent.com/NicoHeinola/MovieBrowser3_backend/main/deploy/install.sh | bash
#   ./deploy/install.sh            # from any checkout; it still builds from the clones
#   ./deploy/install.sh --update   # no questions, the saved answers (what `moviebrowser3 update` runs)
#
# When `git pull` brings a new install.sh, it hands over to the new one (--resume, same answers), so installer changes
# apply on the update that brings them. When .env.production renames or drops a setting, deploy/env-migrations moves
# the saved values once.
#
# Without a terminal it uses the saved answers too. MOVIEBROWSER3_DIR, MOVIEBROWSER3_BACKEND_REPO,
# MOVIEBROWSER3_FRONTEND_REPO and MOVIEBROWSER3_MEDIA_DIRS (folders separated by ":") override them, and
# MOVIEBROWSER3_ADMIN_USERNAME / MOVIEBROWSER3_ADMIN_PASSWORD answer the first-install admin prompt.
set -euo pipefail

DEFAULT_INSTALL_DIR="$HOME/prod_codes/MovieBrowser3"
DEFAULT_BACKEND_REPO="https://github.com/NicoHeinola/MovieBrowser3_backend"
DEFAULT_FRONTEND_REPO="https://github.com/NicoHeinola/MovieBrowser3_frontend"
INSTALL_DIR_POINTER="$HOME/.config/moviebrowser3/install-dir"
DEPLOY_FILES=(compose.yml vlc_bridge.py moviebrowser3 windows/register-vlc-bridge.ps1)
BRIDGE_FILES=(vlc_bridge.py windows/register-vlc-bridge.ps1)

# --update: an existing install, no questions. --resume: the run a changed installer hands over to.
UPDATE=false
ASK_SETTINGS=true
case "${1:-}" in
    --update) UPDATE=true ASK_SETTINGS=false ;;
    --resume) ASK_SETTINGS=false ;;
esac
TTY=true

# This script's own checksum, to notice when `git pull` brings a different install.sh
SELF=""
SELF_SUM=""
if [[ -n "${BASH_SOURCE[0]:-}" && -f "${BASH_SOURCE[0]}" ]]; then
    SELF=$(readlink -f "${BASH_SOURCE[0]}")
    SELF_SUM=$(cksum <"$SELF")
fi

info() { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33mwarning:\033[0m %s\n' "$*" >&2; }
fail() { printf '\033[1;31merror:\033[0m %s\n' "$*" >&2; exit 1; }

# Prompts read from the terminal, because stdin is the script itself under curl | bash
has_tty() { [[ -r /dev/tty ]] && { : </dev/tty; } 2>/dev/null; }

# ask VAR "Prompt" "default": the answer lands in VAR, prefilled with the default (Enter keeps it, Tab completes
# paths). It reads into the variable directly: readline inside $(...) would hide what you type.
ask() {
    local -n answer=$1
    if [[ "$ASK_SETTINGS" == false ]]; then
        answer=$3
        return
    fi
    read -r -e -i "$3" -p "$2: " answer </dev/tty
}

ask_port() {
    local -n port=$1
    while true; do
        ask port "$2" "$3"
        [[ "$port" =~ ^[0-9]+$ ]] && ((port >= 1 && port <= 65535)) && return
        [[ "$ASK_SETTINGS" == false ]] && fail "$2 must be a port number, not '$port'."
        warn "Enter a port number from 1 to 65535."
    done
}

ask_secret() {
    local -n secret=$1
    local confirm
    [[ "$TTY" == true ]] || fail "Can't ask \"$2\" without a terminal; set MOVIEBROWSER3_ADMIN_PASSWORD."
    while true; do
        read -r -s -p "$2: " secret </dev/tty && echo >&2
        read -r -s -p "Repeat it: " confirm </dev/tty && echo >&2
        [[ -n "$secret" && "$secret" == "$confirm" ]] && return
        warn "Empty or not the same, try again."
    done
}

# env_get KEY: the saved value from the install folder's .env, without quotes
env_get() {
    [[ -f "$INSTALL_DIR/.env" ]] || return 0
    local line
    line=$(grep -E "^$1=" "$INSTALL_DIR/.env" | tail -n 1) || return 0
    line=${line#*=}
    line=${line#\"}
    printf '%s' "${line%\"}"
}

# env_has KEY / env_delete KEY / env_rename OLD NEW (keeps the value and the line's place)
env_has() { grep -qE "^$1=" "$INSTALL_DIR/.env"; }

env_rewrite() {
    local tmp
    tmp=$(mktemp)
    awk "$@" "$INSTALL_DIR/.env" >"$tmp"
    # cat keeps the file's mode (600)
    cat "$tmp" >"$INSTALL_DIR/.env"
    rm -f "$tmp"
}

env_delete() { KEY=$1 env_rewrite 'index($0, ENVIRON["KEY"] "=") != 1'; }

env_rename() {
    OLD=$1 NEW=$2 env_rewrite 'index($0, ENVIRON["OLD"] "=") == 1 { print ENVIRON["NEW"] substr($0, length(ENVIRON["OLD"]) + 1); next } 1'
}

# Applies each numbered line of deploy/env-migrations newer than MOVIEBROWSER3_ENV_MIGRATION, once
apply_env_migrations() {
    local file=$1 applied number op key value
    applied=$(env_get MOVIEBROWSER3_ENV_MIGRATION)
    applied=${applied:-0}
    while read -r number op key value; do
        [[ "$number" =~ ^[0-9]+$ ]] && ((number > applied)) || continue
        case "$op" in
            rename)
                if env_has "$key" && env_has "$value"; then
                    env_delete "$key"
                elif env_has "$key"; then
                    env_rename "$key" "$value"
                fi
                ;;
            remove) env_delete "$key" ;;
            set) env_set "$key" "$value" ;;
            *) fail "Unknown step in $file: $number $op" ;;
        esac
        info ".env migration $number: $op $key${value:+ $value}"
        applied=$number
    done <"$file"
    env_set MOVIEBROWSER3_ENV_MIGRATION "$applied"
}

# env_set KEY VALUE: replaces the key's line in .env, or appends it. Values with spaces are quoted.
env_set() {
    local value=$2 tmp
    [[ "$value" == *" "* ]] && value="\"$value\""
    if grep -qE "^$1=" "$INSTALL_DIR/.env"; then
        tmp=$(mktemp)
        KEY=$1 VALUE=$value awk 'index($0, ENVIRON["KEY"] "=") == 1 { print ENVIRON["KEY"] "=" ENVIRON["VALUE"]; next } 1' \
            "$INSTALL_DIR/.env" >"$tmp"
        # cat keeps the file's mode (600)
        cat "$tmp" >"$INSTALL_DIR/.env"
        rm -f "$tmp"
    else
        printf '%s=%s\n' "$1" "$value" >>"$INSTALL_DIR/.env"
    fi
}

random_string() { LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c "$1"; }

is_wsl() { grep -qi microsoft /proc/sys/kernel/osrelease 2>/dev/null; }

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

# Asks for (or, with --update, reuses) the install folder, then every saved setting
configure() {
    has_tty || { TTY=false; ASK_SETTINGS=false; }

    local saved_dir="" dir
    [[ -f "$INSTALL_DIR_POINTER" ]] && saved_dir=$(<"$INSTALL_DIR_POINTER")
    ask dir "Install folder" "${MOVIEBROWSER3_DIR:-${saved_dir:-$DEFAULT_INSTALL_DIR}}"
    dir=${dir/#\~/$HOME}
    [[ "$dir" == /* ]] || fail "The install folder needs an absolute path: $dir"
    # The .bat, the bridge task and the systemd unit all run commands from this path
    [[ "$dir" != *[[:space:]]* ]] || fail "The install folder can't contain spaces: $dir"
    INSTALL_DIR=${dir%/}
    if [[ -n "$saved_dir" && "$saved_dir" != "$INSTALL_DIR" && -f "$saved_dir/.env" ]]; then
        warn "This is a new install next to the one in $saved_dir. Stop that one first ($saved_dir/moviebrowser3 stop), or the ports clash."
    fi
    [[ "$UPDATE" == false || -f "$INSTALL_DIR/.env" ]] ||
        fail "Nothing installed in $INSTALL_DIR yet. Run install.sh first."

    local saved
    saved=$(env_get MOVIEBROWSER3_BACKEND_REPO)
    ask BACKEND_REPO "Backend repo" "${MOVIEBROWSER3_BACKEND_REPO:-${saved:-$DEFAULT_BACKEND_REPO}}"
    saved=$(env_get MOVIEBROWSER3_FRONTEND_REPO)
    ask FRONTEND_REPO "Frontend repo" "${MOVIEBROWSER3_FRONTEND_REPO:-${saved:-$DEFAULT_FRONTEND_REPO}}"

    ask_media_dirs

    local saved_bind lan
    saved=$(env_get WEB_PORT)
    ask_port WEB_PORT "App port" "${saved:-8780}"
    saved_bind=$(env_get WEB_BIND)
    ask lan "Reachable from other devices on the network? (y/n)" "$([[ "$saved_bind" == 0.0.0.0 ]] && echo y || echo n)"
    [[ "$lan" == [yY]* ]] && WEB_BIND=0.0.0.0 || WEB_BIND=127.0.0.1
    saved=$(env_get DB_PORT)
    ask_port DB_PORT "Database port (localhost only)" "${saved:-7564}"
    saved=$(env_get VLC_BRIDGE_PORT)
    ask_port VLC_BRIDGE_PORT "VLC bridge port (localhost only)" "${saved:-8767}"
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

add_media_dir() {
    local dir=${1/#\~/$HOME}
    [[ -n "$dir" ]] || return 1
    dir=${dir%/}
    [[ "$dir" == /* ]] || { warn "Skipped $dir: media folders need an absolute path."; return 1; }
    [[ "$dir" != *:* ]] || { warn "Skipped $dir: a media folder can't contain ':'."; return 1; }
    # /mnt/<letter> is a Windows drive. An external one is only there if it was plugged in when WSL started.
    if [[ "$dir" =~ ^/mnt/([a-z])(/|$) ]] && ! mountpoint -q "/mnt/${BASH_REMATCH[1]}"; then
        warn "Drive ${BASH_REMATCH[1]^^}: isn't mounted in WSL, so $dir is empty for now. See step 5 of deploy/README.md."
    elif [[ ! -d "$dir" ]]; then
        warn "$dir does not exist (yet)."
    fi
    MEDIA_DIRS+=("$dir")
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

# Clones a repo, or fast-forwards it, and prints which commit it moved from and to
sync_repo() {
    local name=$1 url=$2 dir=$3 old new
    if [[ -d "$dir/.git" ]]; then
        old=$(git -C "$dir" rev-parse --short HEAD)
        # After a hand-over to a new installer, the pull already happened: show where it started
        [[ "$name" == backend && -n "${MOVIEBROWSER3_BACKEND_FROM:-}" ]] && old=$MOVIEBROWSER3_BACKEND_FROM
        [[ "$name" == backend ]] && BACKEND_FROM=$old
        # Follow a repo URL changed in the installer
        git -C "$dir" remote set-url origin "$url"
        git -C "$dir" pull --ff-only --quiet ||
            fail "$dir can't be fast-forwarded to GitHub main. Check 'git -C $dir status', fix or reset it, and re-run."
    else
        old="(new)"
        git clone --quiet "$url" "$dir"
    fi
    new=$(git -C "$dir" rev-parse --short HEAD)
    if [[ "$old" == "$new" ]]; then
        info "$name: $new (unchanged)"
    else
        info "$name: $old -> $new  $(git -C "$dir" log -1 --format=%s)"
    fi
}

# Copies the deploy files from the backend clone
install_files() {
    local source_dir="$INSTALL_DIR/MovieBrowser3_backend/deploy" file
    mkdir -p "$INSTALL_DIR/windows" "$INSTALL_DIR/data/postgres" "$INSTALL_DIR/storage/app/private" \
        "$INSTALL_DIR/storage/app/public" "$INSTALL_DIR/web/current"
    for file in "${DEPLOY_FILES[@]}"; do
        cp "$source_dir/$file" "$INSTALL_DIR/$file"
    done
    chmod +x "$INSTALL_DIR/moviebrowser3" "$INSTALL_DIR/vlc_bridge.py"

    mkdir -p "$HOME/.local/bin"
    ln -sf "$INSTALL_DIR/moviebrowser3" "$HOME/.local/bin/moviebrowser3"
    [[ ":$PATH:" == *":$HOME/.local/bin:"* ]] || warn "$HOME/.local/bin is not on your PATH; add it to use the 'moviebrowser3' command."
}

write_env() {
    create_or_upgrade_env

    # The installer's answers; everything else in .env (and any manual edit) is left alone
    local media_dirs
    media_dirs=$(IFS=:; echo "${MEDIA_DIRS[*]}")
    env_set MOVIEBROWSER3_BACKEND_REPO "$BACKEND_REPO"
    env_set MOVIEBROWSER3_FRONTEND_REPO "$FRONTEND_REPO"
    env_set MOVIEBROWSER3_MEDIA_DIRS "$media_dirs"
    env_set WEB_PORT "$WEB_PORT"
    env_set WEB_BIND "$WEB_BIND"
    env_set APP_URL "http://localhost:$WEB_PORT"
    env_set DB_PORT "$DB_PORT"
    env_set VLC_BRIDGE_PORT "$VLC_BRIDGE_PORT"
    env_set VLC_BRIDGE_URL "http://127.0.0.1:$VLC_BRIDGE_PORT"

    mkdir -p "$(dirname "$INSTALL_DIR_POINTER")"
    printf '%s\n' "$INSTALL_DIR" >"$INSTALL_DIR_POINTER"
}

# Starts .env from deploy/.env.production, or migrates an existing one: renames and removals from
# deploy/env-migrations first, then the keys a newer template brings. Then it fills empty secrets.
create_or_upgrade_env() {
    local deploy="$INSTALL_DIR/MovieBrowser3_backend/deploy" template line key
    template="$deploy/.env.production"
    if [[ ! -f "$INSTALL_DIR/.env" ]]; then
        info "Writing $INSTALL_DIR/.env"
        (umask 077 && cp "$template" "$INSTALL_DIR/.env")
        # A new .env already matches the template: no migration applies to it
        env_set MOVIEBROWSER3_ENV_MIGRATION "$(awk '$1 ~ /^[0-9]+$/ { last = $1 } END { print last + 0 }' "$deploy/env-migrations")"
    else
        apply_env_migrations "$deploy/env-migrations"
        while IFS= read -r line; do
            [[ "$line" =~ ^([A-Z0-9_]+)= ]] || continue
            key=${BASH_REMATCH[1]}
            grep -qE "^$key=" "$INSTALL_DIR/.env" && continue
            info "New setting in .env: $line"
            printf '%s\n' "$line" >>"$INSTALL_DIR/.env"
        done <"$template"
    fi

    [[ -n "$(env_get APP_KEY)" ]] || env_set APP_KEY "base64:$(head -c 32 /dev/urandom | base64)"
    [[ -n "$(env_get DB_PASSWORD)" ]] || env_set DB_PASSWORD "$(random_string 32)"
    [[ -n "$(env_get VLC_BRIDGE_TOKEN)" ]] || env_set VLC_BRIDGE_TOKEN "$(random_string 48)"
    [[ -n "$(env_get APP_UID)" ]] || env_set APP_UID "$(id -u)"
    [[ -n "$(env_get APP_GID)" ]] || env_set APP_GID "$(id -g)"
}

# Media folders are mounted at the same path as on the host, so stored video paths work for the backend and VLC
write_media_override() {
    local service dir
    {
        echo "# Media folders, mounted at the same path as on the host. Written by install.sh from MOVIEBROWSER3_MEDIA_DIRS."
        echo "services:"
        for service in backend queue; do
            echo "  $service:"
            if ((${#MEDIA_DIRS[@]} == 0)); then
                echo "    volumes: []"
                continue
            fi
            echo "    volumes:"
            for dir in "${MEDIA_DIRS[@]}"; do
                printf '      - "%s:%s"\n' "$dir" "$dir"
            done
        done
    } >"$INSTALL_DIR/compose.override.yml"
}

# Builds the frontend clone (`npm run build` into its git-ignored dist/) with Node in a throwaway container, no Node
# on the host, and copies the result to web/next. The app serves
# web/current, which only changes in publish_frontend, after the migrations: a failed update keeps the old frontend.
build_frontend() {
    local dir="$INSTALL_DIR/MovieBrowser3_frontend"
    info "Building the frontend"
    docker run --rm --user "$(id -u):$(id -g)" -e HOME=/tmp -v "$dir:/app" -v "$INSTALL_DIR/web:/out" -w /app \
        node:24-alpine sh -c 'npm ci && npm run build && rm -rf /out/next && cp -R dist /out/next' ||
        fail "The frontend build failed; MovieBrowser3 was not restarted."
    [[ -f "$INSTALL_DIR/web/next/index.html" ]] ||
        fail "The frontend build left no web/next/index.html; MovieBrowser3 was not restarted."
}

# Swaps web/next in as web/current. The running backend keeps the old folder open until start_stack recreates it.
publish_frontend() {
    local web="$INSTALL_DIR/web"
    rm -rf "$web/previous"
    [[ ! -d "$web/current" ]] || mv "$web/current" "$web/previous"
    mv "$web/next" "$web/current"
}

start_stack() {
    cd "$INSTALL_DIR"
    info "Building the backend image"
    docker compose build

    local admin_env=() migrations
    # The seed migration creates the admin when it first runs, so its password never has to stay in .env. Ask
    # whenever it hasn't run on this database yet, including a recreated one, so it never falls back to its default.
    # (Fails on a new database, which has no migrations table yet: that also means ask.)
    migrations=$(docker compose run --rm -T backend php artisan migrate:status 2>/dev/null || true)
    if ! grep -q 'seed_admin_user.*Ran' <<<"$migrations"; then
        local username=${MOVIEBROWSER3_ADMIN_USERNAME:-} password=${MOVIEBROWSER3_ADMIN_PASSWORD:-}
        if [[ -z "$username" ]]; then
            username=admin
            [[ "$TTY" == false ]] || read -r -e -i admin -p "Admin username: " username </dev/tty
        fi
        [[ -n "$password" ]] || ask_secret password "Admin password"
        # `-e NAME` without a value takes it from this environment, so the password stays out of the process list
        export ADMIN_USER_USERNAME=$username ADMIN_USER_PASSWORD=$password
        admin_env=(-e ADMIN_USER_USERNAME -e ADMIN_USER_PASSWORD)
    fi

    info "Running migrations"
    docker compose run --rm "${admin_env[@]}" backend php artisan migrate --force
    unset ADMIN_USER_USERNAME ADMIN_USER_PASSWORD

    info "Starting MovieBrowser3"
    publish_frontend
    # Recreate the backend even when its image is unchanged: its mount must pick up the new web/current
    docker compose up -d --force-recreate --no-deps backend
    docker compose up -d --remove-orphans
    rm -rf "$INSTALL_DIR/web/previous"
}

# Registers and (re)starts the bridge when it differs from the last one registered: other files, another port, or
# never registered. .bridge-installed holds that checksum, written only after registering worked.
install_bridge() {
    local script="$INSTALL_DIR/vlc_bridge.py" stamp="$INSTALL_DIR/.bridge-installed" sum
    sum="$(cd "$INSTALL_DIR" && cat "${BRIDGE_FILES[@]}" | cksum) port $VLC_BRIDGE_PORT"
    [[ -f "$stamp" && "$(<"$stamp")" == "$sum" ]] && { info "VLC bridge unchanged"; return; }
    register_bridge "$script"
    printf '%s\n' "$sum" >"$stamp"
}

register_bridge() {
    local script=$1

    if is_wsl; then
        command -v powershell.exe >/dev/null || fail "No WSL interop (powershell.exe), so the VLC bridge can't be registered. See TECHNICAL.md."
        info "Registering and restarting the VLC bridge logon task"
        # Stop a running bridge so the task starts the new script
        pkill -f "python3 $script" 2>/dev/null || true
        (cd /mnt/c 2>/dev/null || true
            powershell.exe -NoProfile -ExecutionPolicy Bypass -File "$(wslpath -w "$INSTALL_DIR/windows/register-vlc-bridge.ps1")" \
                -Distro "$WSL_DISTRO_NAME" -BridgeScript "$script")
        return
    fi

    command -v systemctl >/dev/null || fail "No systemd, so the VLC bridge can't be installed as a service. Start it yourself: python3 $script"
    info "Installing and restarting the VLC bridge user service"
    mkdir -p "$HOME/.config/systemd/user"
    cat >"$HOME/.config/systemd/user/moviebrowser3-vlc-bridge.service" <<UNIT
[Unit]
Description=MovieBrowser3 VLC bridge
After=graphical-session.target

[Service]
ExecStart=/usr/bin/env python3 $script
Restart=on-failure

[Install]
WantedBy=default.target
UNIT
    systemctl --user daemon-reload
    systemctl --user enable moviebrowser3-vlc-bridge.service
    systemctl --user restart moviebrowser3-vlc-bridge.service
}

# A double-clickable update for Windows; the absolute path because ~/.local/bin may not be on PATH there
write_update_bat() {
    is_wsl || return 0
    local bat="$INSTALL_DIR/windows/update_movie_browser_3.bat"
    printf '@echo off\r\nwsl.exe -d %s -e bash -lc "%s update"\r\npause\r\n' "$WSL_DISTRO_NAME" "$INSTALL_DIR/moviebrowser3" >"$bat"
    UPDATE_BAT=$(wslpath -w "$bat")
}

# When the pull brought a different install.sh, continue with that one, with the answers already saved
hand_over_if_installer_changed() {
    local new="$INSTALL_DIR/MovieBrowser3_backend/deploy/install.sh"
    [[ -n "$SELF" && "${MOVIEBROWSER3_RESUMED:-}" != 1 ]] || return 0
    [[ "$(cksum <"$new")" != "$SELF_SUM" ]] || return 0
    info "The installer changed; continuing with the new one"
    MOVIEBROWSER3_RESUMED=1 MOVIEBROWSER3_DIR="$INSTALL_DIR" MOVIEBROWSER3_BACKEND_FROM="${BACKEND_FROM:-}" \
        exec bash "$new" --resume
}

# Everything runs from main, which bash parses in full first: `git pull` may replace this file while it runs
main() {
    check_host
    configure
    check_other_install
    info "Installing into $INSTALL_DIR"
    mkdir -p "$INSTALL_DIR"
    BACKEND_FROM=""
    sync_repo backend "$BACKEND_REPO" "$INSTALL_DIR/MovieBrowser3_backend"
    # After the clone: .env starts from the backend clone's deploy/.env.production
    write_env
    hand_over_if_installer_changed
    sync_repo frontend "$FRONTEND_REPO" "$INSTALL_DIR/MovieBrowser3_frontend"
    install_files
    write_media_override
    build_frontend
    start_stack
    install_bridge
    UPDATE_BAT=""
    write_update_bat

    info "MovieBrowser3 is running at http://localhost:$WEB_PORT"
    echo "    Update later with: moviebrowser3 update"
    if [[ -n "$UPDATE_BAT" ]]; then
        echo "    Or from Windows: double-click $UPDATE_BAT (copy it anywhere you like, e.g. your desktop)"
    fi
}

main "$@"
exit
