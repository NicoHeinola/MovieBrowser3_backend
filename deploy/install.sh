#!/usr/bin/env bash
# MovieBrowser3 installer and updater. It asks for every setting (install folder, repos, media folders, ports),
# prefilled with the saved answer, so Enter keeps it. Answers are saved in the install folder's .env, and
# ~/.config/moviebrowser3/install-dir remembers that folder. Then it clones or fetches both repos, checks out their
# newest release tag, builds the frontend and the backend image from those clones, migrates and restarts. .env starts
# from deploy/.env.production.
# Safe to re-run: it keeps the secrets in .env and the database.
#
#   curl -fsSL https://raw.githubusercontent.com/NicoHeinola/MovieBrowser3_backend/main/deploy/install.sh | bash
#   ./deploy/install.sh            # from any checkout; it still builds from the clones
#   ./deploy/install.sh --update   # no questions, the saved answers (what `moviebrowser3 update` runs)
#
# The steps live in deploy/lib/*.sh. Under `curl | bash` there is no lib/ next to this file, so it clones the backend
# to a temporary folder and runs the installer from there.
#
# When the backend update brings a different installer (this file or lib/), it hands over to the new one (--resume,
# same answers), so installer changes apply on the update that brings them. When .env.production renames or drops a
# setting, deploy/env-migrations moves the saved values once.
#
# Without a terminal it uses the saved answers too. MOVIEBROWSER3_DIR, MOVIEBROWSER3_BACKEND_REPO,
# MOVIEBROWSER3_FRONTEND_REPO and MOVIEBROWSER3_MEDIA_DIRS (folders separated by ":") override them, and
# MOVIEBROWSER3_ADMIN_USERNAME / MOVIEBROWSER3_ADMIN_PASSWORD answer the first-install admin prompt.
set -euo pipefail

DEFAULT_INSTALL_DIR="$HOME/prod_codes/MovieBrowser3"
DEFAULT_BACKEND_REPO="https://github.com/NicoHeinola/MovieBrowser3_backend"
DEFAULT_FRONTEND_REPO="https://github.com/NicoHeinola/MovieBrowser3_frontend"
INSTALL_DIR_POINTER="$HOME/.config/moviebrowser3/install-dir"

# --update: an existing install, no questions. --resume: the run a changed installer hands over to.
UPDATE=false
ASK_SETTINGS=true
case "${1:-}" in
    --update) UPDATE=true ASK_SETTINGS=false ;;
    --resume) ASK_SETTINGS=false ;;
esac
TTY=true

# Empty under `curl | bash`, which runs this file from stdin
DEPLOY_DIR=""
if [[ -n "${BASH_SOURCE[0]:-}" && -f "${BASH_SOURCE[0]}" ]]; then
    DEPLOY_DIR=$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")
fi

# The installer is this file plus lib/: their checksum tells when the backend update brought a different one.
# A release from before lib/ existed has none, which still counts as different (and hands over to that release).
installer_sum() { { cat "$1/install.sh" "$1"/lib/*.sh 2>/dev/null || true; } | cksum; }

# Runs the installer from a temporary clone of the backend (main, like the curl URL), then removes the clone.
# Not exec: the clone must outlive the run.
bootstrap() {
    local repo=${MOVIEBROWSER3_BACKEND_REPO:-$DEFAULT_BACKEND_REPO} tmp status=0

    command -v git >/dev/null || { echo "error: git is not installed." >&2; exit 1; }
    tmp=$(mktemp -d)
    git clone --quiet --depth 1 "$repo" "$tmp/backend" ||
        { echo "error: can't clone $repo. Check your connection and re-run." >&2; rm -rf "$tmp"; exit 1; }

    bash "$tmp/backend/deploy/install.sh" "$@" || status=$?
    rm -rf "$tmp"
    exit "$status"
}

# Loaded in full before main runs, so a backend checkout that replaces them mid-run changes nothing
if [[ -n "$DEPLOY_DIR" && -f "$DEPLOY_DIR/lib/common.sh" ]]; then
    for module in common prompts env settings checks repos stack bridge; do
        # shellcheck source=/dev/null
        source "$DEPLOY_DIR/lib/$module.sh"
    done
    unset module
    SELF_SUM=$(installer_sum "$DEPLOY_DIR")
fi

# When the backend update brought a different installer, continue with that one, with the answers already saved
hand_over_if_installer_changed() {
    local new="$INSTALL_DIR/MovieBrowser3_backend/deploy"

    [[ "${MOVIEBROWSER3_RESUMED:-}" != 1 ]] || return 0
    [[ "$(installer_sum "$new")" != "$SELF_SUM" ]] || return 0

    info "The installer changed; continuing with the new one"
    MOVIEBROWSER3_RESUMED=1 MOVIEBROWSER3_DIR="$INSTALL_DIR" MOVIEBROWSER3_BACKEND_FROM="${BACKEND_FROM:-}" \
        exec bash "$new/install.sh" --resume
}

# Everything runs from main, which bash parses in full first: the backend checkout may replace this file while it runs
main() {
    [[ -n "${SELF_SUM:-}" ]] || bootstrap "$@"

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
    write_update_bat

    info "MovieBrowser3 is running at http://localhost:$WEB_PORT"
    echo "    Update later with: moviebrowser3 update"
    if [[ -n "$UPDATE_BAT" ]]; then
        echo "    Or from Windows: double-click $UPDATE_BAT (copy it anywhere you like, e.g. your desktop)"
    fi
}

main "$@"
exit
