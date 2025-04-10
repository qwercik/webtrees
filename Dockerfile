FROM nathanvaughn/webtrees:2.2.1 AS base

FROM base AS buildable
RUN curl -o /usr/local/bin/composer https://getcomposer.org/composer.phar && \
    chmod +x /usr/local/bin/composer

FROM buildable AS dev
RUN apt-get update -yqq && \
    apt-get install -yqq --no-install-recommends build-essential vim git && \
    pecl install xdebug && \
    docker-php-ext-enable xdebug

FROM buildable AS build-prod
COPY modules_v4/ ./modules_v4
RUN composer install -d ./modules_v4/albums-manager

FROM base AS prod
COPY --from=build-prod /var/www/webtrees/modules_v4/ ./modules_v4
COPY app/Factories/RouteFactory.php ./app/Factories/RouteFactory.php
COPY app/Gedcom.php ./app/Gedcom.php
COPY app/Http/RequestHandlers/EditMediaFileAction.php ./app/Http/RequestHandlers/EditMediaFileAction.php
COPY app/Services/MediaFileService.php ./app/Services/MediaFileService.php
COPY resources/views/media-page-details.phtml ./resources/views/media-page-details.phtml
COPY resources/views/modals/media-file-fields.phtml ./resources/views/modals/media-file-fields.phtml
COPY docker/webtrees/policy.xml /etc/ImageMagick-6/policy.xml
