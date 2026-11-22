---
description: Service conventions for clients of outside systems (torrent sites and other websites or APIs). Use when creating or editing files under app/Services.
paths:
  - 'app/Services/**/*.php'
applyTo: 'app/Services/**/*.php'
---

# Service Rules

- A service is the one place that talks to one outside system: `app/Services/<System>/<System>Service.php`, e.g. `NekoBtService`. Actions call it; nothing else sends it requests.
- Its public methods are the questions the app asks (`getTorrents(NekoBtSearchData)`), take DTOs or scalars and return DTOs. URLs, query names, timeouts and parsing stay inside it. When translating the system's answers grows big, that goes in final sibling mapper classes that only the service uses (`NekoBtTorrentMapper`, `NekoBtShowTemplateMapper`); the service keeps the requests and the answer checks.
- Data that is the system's own (a torrent site's listings, its levels and tags) keeps the system's shape: its own DTOs (`App\Dtos\NekoBt`), enums (`NekoBtLevel`), endpoint (`nekobt-torrents`) and resource. Don't force two systems into one shape that fits neither. A shared interface fits only when every system answers in the app's own terms: `ShowTemplateSource` (a show from a page link) is one, implemented by each site's service and registered with `$this->app->tag(...)` in `AppServiceProvider`; `ResolveShowTemplateSourceAction` picks the one for a link. Sources report every title they know, their own first; `GetShowTemplateAction` decides which to keep.
- A system without a usable public API is read from its web pages: the mapper parses the HTML with PHP's `Dom\HTMLDocument` and CSS selectors (`MalShowTemplateMapper`).
- A failure the caller can't fix throws a domain exception that renders its own code (`TorrentSiteUnavailableException('nekobt')`, 502), after a `Log::warning` that says what the system answered.
- Settings of the system (base URL, timeout) live in `config/<system>.php`. A system the user runs themselves, such as their qBittorrent, gets its address and login from settings instead: an action reads them and passes them in as a DTO (`QBittorrentConnectionData`), and secrets are `secret` settings, stored encrypted and never sent to the frontend.
- Services are adapters only: no models, actions or HTTP layer classes (`tests/Unit/ArchitectureTest.php`). Caching results and other application policy belong to the calling action.
- Tests fake the system with `Http::preventStrayRequests()` and `Http::fake()`, feeding a saved real answer from `tests/Fixtures/<system>/`, trimmed to the parts the mapper reads and formatted (indented JSON or HTML, never a one-line dump) so a reviewer can read it.
