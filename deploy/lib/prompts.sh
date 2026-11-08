# shellcheck shell=bash
# Terminal prompts. They read /dev/tty, because under `curl | bash` stdin is the script itself.
# ASK_SETTINGS=false (--update, --resume, no terminal) makes ask return the default without asking.

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

# Asked even with ASK_SETTINGS=false: the admin password is never saved, so there is no default to fall back to
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
