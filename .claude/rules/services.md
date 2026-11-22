---
description: Service conventions for clients of outside systems (torrent sites and other websites or APIs). Use when creating or editing files under app/Services.
paths:
  - 'app/Services/**/*.php'
applyTo: 'app/Services/**/*.php'
---

# Service Rules

- A service is the one place that talks to one outside system: `app/Services/<System>/<System>Service.php`, e.g. `NekoBtService`. Actions call it; nothing else sends it requests.
- Its public methods are the questions the app asks (`getTorrents(NekoBtSearchData)`), take DTOs or scalars and return DTOs. URLs, query names, timeouts and parsing stay inside it.
- Data that is the system's own (a torrent site's listings, its levels and tags) keeps the system's shape: its own DTOs (`App\Dtos\NekoBt`), enums (`NekoBtLevel`), endpoint (`nekobt-torrents`) and resource. Don't force two systems into one shape that fits neither. A shared interface fits only when every system answers in the app's own terms, such as filling the app's own show fields.
- A failure the caller can't fix throws a domain exception that renders its own code (`TorrentSiteUnavailableException('nekobt')`, 502), after a `Log::warning` that says what the system answered.
- Settings of the system (base URL, timeout) live in `config/<system>.php`.
- Services are adapters only: no models, actions or HTTP layer classes (`tests/Unit/ArchitectureTest.php`). Caching results and other application policy belong to the calling action.
- Tests fake the system with `Http::preventStrayRequests()` and `Http::fake()`, feeding a saved, trimmed real answer from `tests/Fixtures/<system>/`.
