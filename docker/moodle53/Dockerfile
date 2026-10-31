FROM moodlehq/moodle-php-apache:8.3@sha256:501f19fa75ad3cdc8962f2a742f39d179b8aa8ffdc2ff233d8d27a32f14e0e05
USER root
COPY docker/moodle53/sources.txt /tmp/sources.txt
RUN set -eu; while read repo revision destination; do \
    mkdir -p "/var/www/html/$destination"; \
    curl -fsSL --retry 3 "https://codeload.github.com/$repo/tar.gz/$revision" -o /tmp/source.tar.gz; \
    tar -xzf /tmp/source.tar.gz --strip-components=1 -C "/var/www/html/$destination"; \
    done < /tmp/sources.txt; rm /tmp/source.tar.gz; \
    mkdir -p /var/moodledata; chown www-data:www-data /var/moodledata
COPY docker/moodle53/config.php /var/www/html/config.php
COPY docker/moodle53/*.php /opt/macmoodle/
COPY docker/moodle53/bootstrap.sh /opt/macmoodle/bootstrap.sh
RUN printf 'log_errors=On\nerror_log=/dev/stderr\nmax_input_vars=5000\nmemory_limit=512M\nupload_max_filesize=128M\npost_max_size=128M\n' > /usr/local/etc/php/conf.d/macmoodle.ini

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

# Moodle 5.3 stores production dependencies in vendor/, pinned by upstream composer.lock.
RUN curl -fsSL --retry 3 https://getcomposer.org/download/2.8.11/composer.phar -o /tmp/composer.phar \
    && echo '257a969e9a9d27e0e45cfe954835c17a76033ba84a388e0f472db83eded65a8b  /tmp/composer.phar' | sha256sum -c - \
    && cd /var/www/html \
    && COMPOSER_ALLOW_SUPERUSER=1 php /tmp/composer.phar install --no-dev --prefer-dist --no-interaction --no-progress --no-plugins --no-scripts \
    && rm /tmp/composer.phar
COPY docker/moodle53/apache.conf /etc/apache2/conf-enabled/macmoodle53.conf


COPY plugins/local/virtuallearner /var/www/html/public/local/virtuallearner

COPY docker/parsons /opt/macmoodle/parsons
