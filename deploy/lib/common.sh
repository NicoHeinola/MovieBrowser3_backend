# shellcheck shell=bash
# Output helpers and host detection, shared by install.sh and the moviebrowser3 command.
#
# The installer's output reads as: banner, a numbered header per phase (step), short lines under it (info, warn, and
# run_quiet's spinner that turns into ✔ with the time taken), then a summary. Noisy tools (npm, docker build, compose,
# migrations, bridge registration) go through run_quiet: their output lands in INSTALL_LOG and only shows when they
# fail. MOVIEBROWSER3_VERBOSE=1 streams it live instead.

# Colors only on a terminal, and never with NO_COLOR (https://no-color.org)
if [[ -t 1 && -z "${NO_COLOR:-}" ]]; then
    BOLD=$'\033[1m' DIM=$'\033[2m' RED=$'\033[31m' GREEN=$'\033[32m' YELLOW=$'\033[33m' BLUE=$'\033[34m'
    CYAN=$'\033[36m' RESET=$'\033[0m'
else
    BOLD="" DIM="" RED="" GREEN="" YELLOW="" BLUE="" CYAN="" RESET=""
fi

# Set by install.sh once the install folder is known. Empty: run_quiet just runs the command.
INSTALL_LOG=""
STEP_TOTAL=5
STEP_NUMBER=0
# A resumed installer re-runs the steps before the hand-over silently: the previous one already printed them
MUTED=false

# Lines under a step are indented to start below its title: "[1/5] " is 6 wide
INDENT='      '

# step TITLE: starts the next phase of the install or update
step() {
    STEP_NUMBER=$((STEP_NUMBER + 1))
    [[ "$MUTED" == false ]] || return 0
    printf '\n%s[%d/%d]%s %s%s%s\n' "$BLUE$BOLD" "$STEP_NUMBER" "$STEP_TOTAL" "$RESET" "$BOLD" "$*" "$RESET"
}

info() {
    [[ "$MUTED" == false ]] || return 0
    printf '%s%s\n' "$INDENT" "$*"
}

# done_line TEXT [DETAIL]: a finished piece of work, with a dimmed detail such as the time it took
done_line() {
    [[ "$MUTED" == false ]] || return 0
    printf '%s%s✔%s %s%s\n' "$INDENT" "$GREEN" "$RESET" "$1" "${2:+  $DIM$2$RESET}"
}

warn() {
    [[ "$MUTED" == false ]] || return 0
    printf '%s%s! %s%s\n' "$INDENT" "$YELLOW" "$*" "$RESET" >&2
}

fail() {
    printf '\n%s✖ %s%s\n' "$RED$BOLD" "$*" "$RESET" >&2
    [[ -z "$INSTALL_LOG" ]] || printf '  %sFull log: %s%s\n' "$DIM" "$INSTALL_LOG" "$RESET" >&2
    exit 1
}

# Seconds since the epoch, from the printf builtin (no date process)
now() { printf '%(%s)T' -1; }

# 42 -> "42s", 185 -> "3m 05s", under a second -> nothing
format_duration() {
    local seconds=$1
    if ((seconds < 1)); then
        return 0
    elif ((seconds < 60)); then
        printf '%ds' "$seconds"
    else
        printf '%dm %02ds' $((seconds / 60)) $((seconds % 60))
    fi
}

# spinner LABEL PARENT_PID: redraws "⠋ LABEL 12s" until killed. Runs in the background, where bash ignores Ctrl+C,
# so it also stops by itself once the installer (PARENT_PID) is gone.
spinner() {
    local label=$1 parent=$2 started i=0
    started=$(now)
    local frames=(⠋ ⠙ ⠹ ⠸ ⠼ ⠴ ⠦ ⠧ ⠇ ⠏)

    # Hide the cursor while spinning; TERM is how run_quiet stops it
    trap 'printf "\033[?25h"; exit 0' TERM
    printf '\033[?25l'
    while kill -0 "$parent" 2>/dev/null; do
        printf '\r%s%s%s%s %s  %s%s%s' "$INDENT" "$CYAN" "${frames[i++ % ${#frames[@]}]}" "$RESET" "$label" \
            "$DIM" "$(format_duration $(($(now) - started)))" "$RESET"
        sleep 0.1
    done
    printf '\033[?25h'
}

# run_quiet LABEL COMMAND...: runs COMMAND with its output in the log, behind a spinner, and leaves "✔ LABEL 1m 05s".
# On failure it shows "✖ LABEL" and the end of the log, and returns the command's status: the caller says what failed.
run_quiet() {
    local label=$1 status=0 started spinner_pid="" log_start
    shift
    started=$(now)

    # No log yet, or verbose: let the tool print straight to the terminal
    if [[ -z "$INSTALL_LOG" || "${MOVIEBROWSER3_VERBOSE:-}" == 1 ]]; then
        info "$label"
        "$@" || return
        return 0
    fi

    printf '\n$ %s\n' "$*" >>"$INSTALL_LOG"
    # Where this command's output starts, so a failure shows its lines and not the previous command's
    log_start=$(($(wc -l <"$INSTALL_LOG") + 1))
    if [[ -t 1 && "$MUTED" == false ]]; then
        spinner "$label" "$$" &
        spinner_pid=$!
    fi

    # stdin from /dev/null: under `curl | bash` stdin is the script itself, which a tool must not read
    "$@" >>"$INSTALL_LOG" 2>&1 </dev/null || status=$?

    if [[ -n "$spinner_pid" ]]; then
        kill "$spinner_pid" 2>/dev/null || true
        wait "$spinner_pid" 2>/dev/null || true
        # Clear the spinner's line, so the result replaces it
        printf '\r\033[K'
    fi

    if ((status == 0)); then
        done_line "$label" "$(format_duration $(($(now) - started)))"
        return 0
    fi

    printf '%s%s✖ %s%s\n\n' "$INDENT" "$RED$BOLD" "$label" "$RESET"
    # The failing command's last 30 lines, in a dimmed gutter so they read as quoted tool output
    tail -n "+$log_start" "$INSTALL_LOG" | tail -n 30 | sed "s/^/$INDENT$DIM│$RESET /" >&2
    return "$status"
}

is_wsl() { grep -qi microsoft /proc/sys/kernel/osrelease 2>/dev/null; }
