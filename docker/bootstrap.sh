#!/bin/bash
set -euo pipefail
cd /var/www/html
# Test the actual schema, not a host-side marker that could outlive the database.
state=$(php -r '$db=pg_connect("host=db dbname=moodle user=moodle password=" . getenv("DB_PASSWORD")); if (!$db) {exit(1);} echo pg_fetch_result(pg_query($db, "SELECT to_regclass('\''public.mdl_config'\'') IS NOT NULL"), 0, 0) === "t" ? "installed" : "new";')
if [ "$state" = new ]; then
  php admin/cli/install_database.php --agree-license \
    --fullname='Mac Moodle + STACK' --shortname=MacMoodle \
    --adminuser=admin --adminpass="$ADMIN_PASSWORD" --adminemail=admin@example.invalid
else
  php admin/cli/upgrade.php --non-interactive
fi
php /opt/macmoodle/configure.php
