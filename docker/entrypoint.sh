#!/bin/sh
set -e

# Cache config, routes and events from this container's environment (.env values come in as variables).
# Not `artisan optimize`: laravel-data adds a step to it that writes to the database cache, which the first
# migration run does not have yet. No view:cache either: the API has no views, and a clone has no resources/views.
for cache in config route event; do
    php /app/artisan "$cache:cache" --quiet
done

exec "$@"
