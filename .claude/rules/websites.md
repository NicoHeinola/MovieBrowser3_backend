---
description: Admin website bookmarks and shared website tags. Use when editing Website or WebsiteTag models, actions, requests, resources, controllers or tests.
paths:
  - 'app/**/Website/**'
  - 'app/**/WebsiteTag/**'
  - 'app/Http/Controllers/Website*Controller.php'
  - 'tests/Feature/Website*ApiTest.php'
applyTo: 'app/**/Website/**,app/**/WebsiteTag/**,app/Http/Controllers/Website*Controller.php,tests/Feature/Website*ApiTest.php'
---

# Websites and Tags

- Every route is admin-only. Tags are a shared resource (`website-tags`) linked through the `website_website_tag` pivot. Tag names are unique ignoring case (`App\Rules\UniqueIgnoringCase` plus a `lower(name)` unique index), and the tag index includes `websites_count`.
- Websites take `tag_ids`: omitted on PATCH keeps them, an array syncs them. They nest `tags: [{id, name}]`. `is_favorite` marks the websites the admin dashboard lists.
- Filters: `filter[is_favorite]=1`, `filter[search]` (every word in title, url, description or a tag name, ignoring case), `filter[tags.name]`, `filter[tags.id]` (all listed, `withTags`) and `filter[exclude_tags]` (none listed, `withoutTags`).
