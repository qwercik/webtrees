FROM nathanvaughn/webtrees:2.2.1 AS base

FROM base AS dev
RUN apt-get update -yqq && \
    apt-get install -yqq --no-install-recommends build-essential vim git && \
    pecl install xdebug && \
    docker-php-ext-enable xdebug

FROM base AS prod
COPY app/Factories/RouteFactory.php ./app/Factories/RouteFactory.php
COPY app/Gedcom.php ./app/Gedcom.php
COPY app/Http/RequestHandlers/EditMediaFileAction.php ./app/Http/RequestHandlers/EditMediaFileAction.php
COPY app/Services/MediaFileService.php ./app/Services/MediaFileService.php
COPY resources/views/media-page-details.phtml ./resources/views/media-page-details.phtml
COPY resources/views/modals/media-file-fields.phtml ./resources/views/modals/media-file-fields.phtml
COPY docker/webtrees/policy.xml /etc/ImageMagick-6/policy.xml
COPY modules_v4/ ./modules_v4
