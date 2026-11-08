# shellcheck shell=bash
# The install folder's files, the frontend build, the backend image, migrations and the running containers.

# Copied from the backend clone's deploy/ on every run. lib/ goes along because the moviebrowser3 command uses it.
DEPLOY_FILES=(compose.yml vlc_bridge.py moviebrowser3 windows/register-vlc-bridge.ps1)

install_files() {
    local source_dir="$INSTALL_DIR/MovieBrowser3_backend/deploy" file

    mkdir -p "$INSTALL_DIR/windows" "$INSTALL_DIR/data/postgres" "$INSTALL_DIR/storage/app/private" \
        "$INSTALL_DIR/storage/app/public" "$INSTALL_DIR/web/current"

    for file in "${DEPLOY_FILES[@]}"; do
        cp "$source_dir/$file" "$INSTALL_DIR/$file"
    done
    chmod +x "$INSTALL_DIR/moviebrowser3" "$INSTALL_DIR/vlc_bridge.py"

    # Replaced whole, so a module a newer release drops doesn't linger
    rm -rf "$INSTALL_DIR/lib"
    cp -R "$source_dir/lib" "$INSTALL_DIR/lib"

    mkdir -p "$HOME/.local/bin"
    ln -sf "$INSTALL_DIR/moviebrowser3" "$HOME/.local/bin/moviebrowser3"
    [[ ":$PATH:" == *":$HOME/.local/bin:"* ]] ||
        warn "$HOME/.local/bin is not on your PATH; add it to use the 'moviebrowser3' command."
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
# on the host, and copies the result to web/next. The app serves web/current, which only changes in publish_frontend,
# after the migrations: a failed update keeps the old frontend.
build_frontend() {
    local dir="$INSTALL_DIR/MovieBrowser3_frontend"

    run_quiet "Frontend packages and build" docker run --rm --user "$(id -u):$(id -g)" -e HOME=/tmp -v "$dir:/app" -v "$INSTALL_DIR/web:/out" -w /app \
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

# Builds the backend image and migrates. The running app is untouched until start_stack.
build_backend() {
    cd "$INSTALL_DIR"

    run_quiet "Backend image" docker compose build || fail "The backend image build failed; MovieBrowser3 was not restarted."

    run_migrations
}

# Swaps in the new frontend and (re)starts every service
start_stack() {
    cd "$INSTALL_DIR"

    publish_frontend
    # Recreate the backend even when its image is unchanged: its mount must pick up the new web/current
    run_quiet "Backend on the new version" docker compose up -d --force-recreate --no-deps backend ||
        fail "The backend failed to start."
    run_quiet "Database, queue and scheduler" docker compose up -d --remove-orphans ||
        fail "The services failed to start."
    rm -rf "$INSTALL_DIR/web/previous"
}

# Migrates in a one-off container. The seed migration creates the admin when it first runs, so its password never has
# to stay in .env. Asks for it whenever that migration hasn't run on this database yet, including a recreated one, so
# it never falls back to its default.
run_migrations() {
    local admin_env=() migrations

    # Fails on a new database, which has no migrations table yet: that also means ask
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

    run_quiet "Database migrations" docker compose run --rm -T "${admin_env[@]}" backend php artisan migrate --force ||
        fail "The migrations failed; MovieBrowser3 was not restarted."
    unset ADMIN_USER_USERNAME ADMIN_USER_PASSWORD
}

# A double-clickable update for Windows; the absolute path because ~/.local/bin may not be on PATH there.
# Sets UPDATE_BAT to its Windows path, empty off WSL.
write_update_bat() {
    UPDATE_BAT=""
    is_wsl || return 0

    local bat="$INSTALL_DIR/windows/update_movie_browser_3.bat"
    printf '@echo off\r\nwsl.exe -d %s -e bash -lc "%s update"\r\npause\r\n' "$WSL_DISTRO_NAME" "$INSTALL_DIR/moviebrowser3" >"$bat"
    UPDATE_BAT=$(wslpath -w "$bat")
}
