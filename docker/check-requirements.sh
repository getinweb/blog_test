#!/bin/sh
set -eu

fail() {
    printf 'Error: %s\nSee Docker requirements in README.md.\n' "$1" >&2
    exit 1
}

require_version() {
    if ! awk -v actual="$2" -v minimum="$3" 'BEGIN {
        sub(/^v/, "", actual);
        if (actual !~ /^[0-9]+\.[0-9]+\.[0-9]+([+-][[:alnum:].~+-]+)?$/) exit 1;
        split(actual, found, ".");
        split(minimum, required, ".");
        for (i = 1; i <= 3; i++) {
            if (found[i] + 0 > required[i] + 0) exit 0;
            if (found[i] + 0 < required[i] + 0) exit 1;
        }
        exit 0;
    }'; then
        fail "$1 $2 is unsupported; required >= $3. Update Docker and the Compose plugin."
    fi
}

command -v docker >/dev/null 2>&1 || fail 'Docker is not installed or is missing from PATH.'

compose_version=$(docker compose version --short 2>/dev/null) ||
    fail 'Docker Compose plugin >= 2.24.1 is required (docker compose); legacy docker-compose is unsupported.'
require_version 'Docker Compose' "$compose_version" '2.24.1'

docker_versions=$(docker version --format '{{.Client.Version}} {{.Server.Version}}') ||
    fail 'Cannot query the Docker daemon. Check that it is running and accessible to the current user.'
client_version=${docker_versions%% *}
server_version=${docker_versions#* }
require_version 'Docker CLI' "$client_version" '25.0.0'
require_version 'Docker Engine' "$server_version" '25.0.0'

case "${DOCKER_BUILDKIT:-}" in
    0|false|False|FALSE|f|F)
        fail 'BuildKit is required for ADD --checksum. Unset DOCKER_BUILDKIT to restore the default builder.'
        ;;
esac

printf 'OK: Docker CLI %s, Engine %s, Compose %s.\n' "$client_version" "$server_version" "$compose_version"
