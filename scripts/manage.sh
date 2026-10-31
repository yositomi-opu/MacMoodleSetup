#!/bin/bash
set -euo pipefail
cd "$(dirname "$0")/.."
[ "$(uname -s)" = Darwin ] || { echo 'This setup supports macOS only.' >&2; exit 1; }
action=${1:-help}
docker_ready() {
  command -v docker >/dev/null || { echo 'Install Docker Desktop for Mac first: https://www.docker.com/products/docker-desktop/'; exit 1; }
  if ! docker info >/dev/null 2>&1; then
    open -a Docker
    echo 'Waiting for Docker Desktop...'
    for ((i=0;i<120;i++)); do
      docker info >/dev/null 2>&1 && break
      sleep 2
    done
  fi
  docker info >/dev/null 2>&1 || { echo 'Docker Desktop did not become ready.'; exit 1; }
  docker compose version >/dev/null
}
# Match the Python manager: this project's .env owns interpolation credentials.
compose() { env -u DB_PASSWORD -u ADMIN_PASSWORD -u MOODLE_PORT docker compose --project-name macmoodlesetup --env-file .env -f compose.yaml "$@"; }
if [ "$action" = setup ] && [ ! -e .env ]; then
  umask 077
  { printf 'MOODLE_PORT=8088\nDB_PASSWORD=%s\nADMIN_PASSWORD=Mm1!%s\n' "$(openssl rand -hex 24)" "$(openssl rand -hex 16)"; } > .env
fi
[ -f .env ] || { echo 'Run make setup first.'; exit 1; }
# Only generated KEY=value settings are supported; never execute .env as a shell script.
port=$(sed -n 's/^MOODLE_PORT=//p' .env)
case "$port" in ''|*[!0-9]*) echo 'MOODLE_PORT must be an integer.'; exit 1;; esac
case "$action" in
  credentials) printf 'URL: http://localhost:%s\nUsername: admin\nInitial password: ' "$port"; sed -n 's/^ADMIN_PASSWORD=//p' .env; exit;;
  open) open "http://localhost:$port"; exit;;
esac
docker_ready
case "$action" in
  setup)
    compose build web
    compose stop cron
    compose up -d db maxima web
    compose exec -T --user www-data web bash /opt/macmoodle/bootstrap.sh
    compose exec -T --user www-data web php /opt/macmoodle/check.php
    echo 'Setup complete. Run make start, then make credentials for login details.'
    ;;
  start)
    compose up -d db maxima web
    compose exec -T --user www-data web php -r 'define("CLI_SCRIPT", true); require "/var/www/html/config.php"; if (empty($CFG->version)) {exit(1);}'
    compose --profile installed up -d cron
    echo "Moodle: http://localhost:$port (make credentials for login details)"
    ;;
  stop) compose --profile installed stop;;
  restart) compose --profile installed stop; bash scripts/manage.sh start;;
  check) compose exec -T --user www-data web php /opt/macmoodle/check.php;;
  logs) compose --profile installed logs --tail=80;;
  status) compose --profile installed ps;;
  backup)
    umask 077
    destination="backups/$(date +%Y%m%d-%H%M%S)"
    mkdir -p "$destination"
    # Stop writers to keep the database dump and uploaded files consistent.
    compose stop web cron
    trap 'echo "Services remain stopped; use make start after checking the backup."' EXIT
    compose exec -T db pg_dump -U moodle -d moodle -Fc > "$destination/database.dump"
    compose run --rm --no-deps -T --user root --entrypoint tar web -czf - -C /var/moodledata . > "$destination/moodledata.tar.gz"
    cp .env "$destination/settings.env"
    cp docker/sources.txt "$destination/sources.txt"
    compose config --images > "$destination/images.txt"
    echo "Backup saved to $destination"
    ;;
  *) echo 'Use make setup/start/stop/restart/check/status/logs/open/credentials/backup'; exit 1;;
esac
