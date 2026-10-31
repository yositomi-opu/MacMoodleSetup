FROM moodlehq/moodle-php-apache:8.3@sha256:501f19fa75ad3cdc8962f2a742f39d179b8aa8ffdc2ff233d8d27a32f14e0e05
USER root
COPY docker/sources.txt /tmp/sources.txt
RUN set -eu; while read repo revision destination; do \
    mkdir -p "/var/www/html/$destination"; \
    curl -fsSL --retry 3 "https://codeload.github.com/$repo/tar.gz/$revision" -o /tmp/source.tar.gz; \
    tar -xzf /tmp/source.tar.gz --strip-components=1 -C "/var/www/html/$destination"; \
    done < /tmp/sources.txt; rm /tmp/source.tar.gz; \
    mkdir -p /var/moodledata; chown www-data:www-data /var/moodledata
COPY docker/config.php /var/www/html/config.php
COPY docker/*.php /opt/macmoodle/
COPY docker/bootstrap.sh /opt/macmoodle/bootstrap.sh
RUN printf 'log_errors=On\nerror_log=/dev/stderr\nmax_input_vars=5000\nmemory_limit=512M\nupload_max_filesize=128M\npost_max_size=128M\n' > /usr/local/etc/php/conf.d/macmoodle.ini

COPY plugins/local/virtuallearner /var/www/html/local/virtuallearner

COPY docker/parsons /opt/macmoodle/parsons
