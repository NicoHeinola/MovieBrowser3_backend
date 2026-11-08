# shellcheck shell=bash
# Output helpers and host detection, shared by install.sh and the moviebrowser3 command.

info() { printf '\033[1;34m==>\033[0m %s\n' "$*"; }

warn() { printf '\033[1;33mwarning:\033[0m %s\n' "$*" >&2; }

fail() {
    printf '\033[1;31merror:\033[0m %s\n' "$*" >&2
    exit 1
}

is_wsl() { grep -qi microsoft /proc/sys/kernel/osrelease 2>/dev/null; }
