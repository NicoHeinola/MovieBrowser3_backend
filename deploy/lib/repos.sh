# shellcheck shell=bash
# The backend and frontend clones in the install folder, each on its newest release tag.

# The release tag HEAD sits on, or its short commit when it's between releases
describe_head() {
    git -C "$1" describe --tags --exact-match HEAD 2>/dev/null || git -C "$1" rev-parse --short HEAD
}

# sync_repo NAME URL DIR: clones the repo, or fetches it, and checks out its newest release tag (vX.Y.Z) detached:
# commits on main after the last tag are unreleased work. With no release tag yet it follows main.
# Prints the old and new version. For the backend it also sets BACKEND_FROM, the version it started from.
sync_repo() {
    local name=$1 url=$2 dir=$3 old new tag

    if [[ -d "$dir/.git" ]]; then
        old=$(describe_head "$dir")
        # After a hand-over to a new installer, the backend was already updated once: show where it started
        [[ "$name" == backend && -n "${MOVIEBROWSER3_BACKEND_FROM:-}" ]] && old=$MOVIEBROWSER3_BACKEND_FROM

        # Follow a repo URL changed in the installer
        git -C "$dir" remote set-url origin "$url"
        # --force: a release tag moved on GitHub replaces the local one
        git -C "$dir" fetch --quiet --tags --force origin ||
            fail "$dir can't be fetched from GitHub. Check your connection and re-run."
    else
        old="(new)"
        git clone --quiet "$url" "$dir"
    fi

    # Carried into a hand-over, also "(new)" for a fresh clone, so the resumed run reports the real starting point
    [[ "$name" == backend ]] && BACKEND_FROM=$old

    # for-each-ref --count instead of `tag --list | head`: head closing the pipe early fails it under pipefail
    tag=$(git -C "$dir" for-each-ref --count=1 --sort=-v:refname --format='%(refname:short)' 'refs/tags/v*')
    if [[ -n "$tag" ]]; then
        git -C "$dir" -c advice.detachedHead=false checkout --quiet --detach "$tag" ||
            fail "$dir can't check out release $tag. Check 'git -C $dir status', discard local changes with 'git -C $dir reset --hard' (plus 'git -C $dir clean -fd' for added files), and re-run."
    else
        git -C "$dir" checkout --quiet main && git -C "$dir" merge --ff-only --quiet origin/main ||
            fail "$dir can't be fast-forwarded to GitHub main. Check 'git -C $dir status', fix or reset it, and re-run."
    fi

    new=$(describe_head "$dir")
    if [[ "$old" == "$new" ]]; then
        info "$name: $new (unchanged)"
    else
        info "$name: $old -> $new  $(git -C "$dir" log -1 --format=%s)"
    fi
}
