# shellcheck shell=bash
# Reading and writing the install folder's .env ($INSTALL_DIR/.env), and keeping it in step with
# deploy/.env.production and deploy/env-migrations.

# env_get KEY: the saved value, without quotes. Empty when .env or the key is missing.
env_get() {
    [[ -f "$INSTALL_DIR/.env" ]] || return 0

    local line
    line=$(grep -E "^$1=" "$INSTALL_DIR/.env" | tail -n 1) || return 0
    line=${line#*=}
    line=${line#\"}
    printf '%s' "${line%\"}"
}

env_has() { grep -qE "^$1=" "$INSTALL_DIR/.env"; }

# env_rewrite <awk args>: runs awk over .env and writes the result back
env_rewrite() {
    local tmp
    tmp=$(mktemp)
    awk "$@" "$INSTALL_DIR/.env" >"$tmp"

    # cat keeps the file's mode (600)
    cat "$tmp" >"$INSTALL_DIR/.env"
    rm -f "$tmp"
}

# env_set KEY VALUE: replaces the key's line, or appends it. Values with spaces are quoted.
env_set() {
    local value=$2
    [[ "$value" == *" "* ]] && value="\"$value\""

    if env_has "$1"; then
        KEY=$1 VALUE=$value env_rewrite \
            'index($0, ENVIRON["KEY"] "=") == 1 { print ENVIRON["KEY"] "=" ENVIRON["VALUE"]; next } 1'
    else
        printf '%s=%s\n' "$1" "$value" >>"$INSTALL_DIR/.env"
    fi
}

env_delete() { KEY=$1 env_rewrite 'index($0, ENVIRON["KEY"] "=") != 1'; }

# env_rename OLD NEW: keeps the value and the line's place
env_rename() {
    OLD=$1 NEW=$2 env_rewrite \
        'index($0, ENVIRON["OLD"] "=") == 1 { print ENVIRON["NEW"] substr($0, length(ENVIRON["OLD"]) + 1); next } 1'
}

random_string() { LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c "$1"; }

# Applies each numbered line of deploy/env-migrations newer than MOVIEBROWSER3_ENV_MIGRATION, once
apply_env_migrations() {
    local file=$1 applied number op key value
    applied=$(env_get MOVIEBROWSER3_ENV_MIGRATION)
    applied=${applied:-0}

    while read -r number op key value; do
        # Comments, blank lines and steps already applied
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

# Starts .env from deploy/.env.production, or migrates an existing one: renames and removals from
# deploy/env-migrations first, then the keys a newer template brings. Then it fills empty secrets.
create_or_upgrade_env() {
    local deploy="$INSTALL_DIR/MovieBrowser3_backend/deploy" line key
    local template="$deploy/.env.production"

    if [[ ! -f "$INSTALL_DIR/.env" ]]; then
        info "Writing $INSTALL_DIR/.env"
        (umask 077 && cp "$template" "$INSTALL_DIR/.env")

        # A new .env already matches the template: no migration applies to it
        env_set MOVIEBROWSER3_ENV_MIGRATION \
            "$(awk '$1 ~ /^[0-9]+$/ { last = $1 } END { print last + 0 }' "$deploy/env-migrations")"
    else
        apply_env_migrations "$deploy/env-migrations"

        while IFS= read -r line; do
            [[ "$line" =~ ^([A-Z0-9_]+)= ]] || continue
            key=${BASH_REMATCH[1]}
            env_has "$key" && continue

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

# Brings .env up to date, then saves the installer's answers. Everything else in .env (and any manual edit) stays.
write_env() {
    create_or_upgrade_env

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
