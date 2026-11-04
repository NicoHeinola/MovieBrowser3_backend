---
description: VLC playback and tracking. Use when editing episode playback actions, the VLC tracking job, VLC config or their tests.
paths:
  - 'app/Actions/EpisodePlayback/**'
  - 'app/Dtos/EpisodePlayback/**'
  - 'app/Http/Controllers/EpisodePlaybackController.php'
  - 'app/Jobs/TrackVlcPlaybackJob.php'
  - 'app/Exceptions/VlcLaunchException.php'
  - 'app/Actions/Episode/VideoFile/ResolveEpisodeFromVideoFileAction.php'
  - 'config/vlc.php'
  - 'tests/Feature/EpisodePlayback/**'
  - 'tests/Feature/EpisodePlaybackApiTest.php'
applyTo: 'app/Actions/EpisodePlayback/**,app/Dtos/EpisodePlayback/**,app/Http/Controllers/EpisodePlaybackController.php,app/Jobs/TrackVlcPlaybackJob.php,app/Exceptions/VlcLaunchException.php,app/Actions/Episode/VideoFile/ResolveEpisodeFromVideoFileAction.php,config/vlc.php,tests/Feature/EpisodePlayback/**,tests/Feature/EpisodePlaybackApiTest.php'
---

# VLC Playback

- `POST episodes/{episode}/playback` opens VLC through `OpenVlcPlaylistAction` with every episode of the entry that has a file, starting at the chosen one, then upserts progress through `UpdateShowWatchProgressAction` and queues `TrackVlcPlaybackJob`.
- VLC has no start-item flag, so it launches detached (`nohup` via `Process`) with its web interface on a free localhost port and a random password (`VlcConnectionData`). The playlist is built with `in_enqueue`/`in_play` requests through `RequestVlcStatusAction`.
- The binary comes from the `vlc_media_player_path` setting (falls back to `vlc`). A Windows `vlc.exe` run from WSL gets `wslpath -w` paths and needs WSL mirrored networking.
- `vlc.launcher` (`VLC_LAUNCHER`) is `local` in dev. In Docker it is `bridge`: `RequestVlcBridgeAction` sends the launch and the `wslpath` conversion to the host-side `deploy/vlc_bridge.py` (`VLC_BRIDGE_URL`, `VLC_BRIDGE_TOKEN`), and the binary is checked there, not with `is_file`. Keep both launchers behaving the same. `CheckVlcBinaryAction` (the setup wizard's check and VLC detection over `vlc.detect_paths`) goes through the bridge's `/check-binary` the same way. Bridge failures are `VlcBridgeException`s: playback shows the message, the setup check sends the `SetupCheckCode`. The bridge's 422 keeps its `message` and adds a `code` for binary problems.
- Under mirrored networking Windows cannot listen on Linux's ephemeral ports or on a port Linux just opened: pick the port from 40000-49151 minus Linux's current ephemeral range (`ResolveEphemeralPortRangeAction`; WSL moves it every boot) and check it by connecting, never by binding.
- The job reads `status.json` every `vlc.track_interval_seconds` (default 5, minimum 1) through `queueNextCheck()`, maps the playing file's `{episodeId}_` prefix back to the episode (`ResolveEpisodeFromVideoFileAction`), saves it with VLC's `time`/`length` as `position_seconds`/`duration_seconds` while VLC is playing, and stops once VLC no longer answers.
- Playing the episode the progress already points at resumes there: `in_play` gets an `option=start-time=N` item option, so later playlist items start at 0. `from_start: true` starts over. Any other episode starts with the position cleared.
- Tests mock `OpenVlcPlaylistAction` and fake `Queue` at the endpoint level; the VLC actions and job are tested with `Http::fake`, `Process::fake` and `Sleep::fake`.
