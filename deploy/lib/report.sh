# shellcheck shell=bash
# The installer's title card and closing summary, around the steps that common.sh prints.

RULE='────────────────────────────────────────────────────'

# The title card. Not on a resumed run, which continues the previous installer's output.
banner() {
    [[ "$MUTED" == false ]] || return 0

    local action="Installing"
    [[ "$UPDATE" == true ]] && action="Updating to the newest release"
    printf '\n  %sMovieBrowser3%s   %s%s%s\n' "$BOLD$CYAN" "$RESET" "$DIM" "$action" "$RESET"
    printf '  %s%s%s\n' "$DIM" "$RULE" "$RESET"
}

# The closing card: total time, what changed, where to open it and how to update next time.
# Reads BACKEND_CHANGE and FRONTEND_CHANGE from sync_repo, STARTED from main.
summary() {
    local kind="Install" label var
    [[ "$UPDATE" == true ]] && kind="Update"

    printf '\n  %s%s%s\n' "$DIM" "$RULE" "$RESET"
    printf '  %s✔ %s finished%s  %sin %s%s\n\n' "$GREEN$BOLD" "$kind" "$RESET" "$DIM" \
        "$(format_duration $(($(now) - STARTED)))" "$RESET"

    for label in Backend Frontend; do
        var="${label^^}_CHANGE"
        summary_row "$label" "${!var:-?}"
    done
    summary_row Open "$BOLD${CYAN}http://localhost:$WEB_PORT$RESET"
    summary_row "Next update" "moviebrowser3 update"
    if [[ -n "$UPDATE_BAT" ]]; then
        summary_row "" "${DIM}or double-click$RESET $UPDATE_BAT"
        # The first install also says where the shortcut can go
        [[ "$UPDATE" == true ]] || summary_row "" "${DIM}(copy it anywhere you like, e.g. your desktop)$RESET"
    fi
    echo
}

# summary_row LABEL VALUE: one line of the summary, labels padded to one column
summary_row() { printf '    %s%-12s%s %s\n' "$DIM" "$1" "$RESET" "$2"; }
