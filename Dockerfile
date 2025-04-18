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
RUN composer install -d ./modules_v4/job-queue
RUN composer install -d ./modules_v4/albums-manager

FROM base AS prod
COPY --from=build-prod /var/www/webtrees/modules_v4/ ./modules_v4
COPY app/Factories/RouteFactory.php ./app/Factories/RouteFactory.php
COPY app/Gedcom.php ./app/Gedcom.php
COPY app/Http/RequestHandlers/EditMediaFileAction.php ./app/Http/RequestHandlers/EditMediaFileAction.php
COPY app/Services/MediaFileService.php ./app/Services/MediaFileService.php
COPY app/Contracts/ImageFactoryInterface.php ./app/Contracts/ImageFactoryInterface.php
COPY app/Factories/ImageFactory.php ./app/Factories/ImageFactory.php
COPY app/Http/RequestHandlers/MediaFileThumbnail.php ./app/Http/RequestHandlers/MediaFileThumbnail.php
COPY app/Individual.php ./app/Individual.php
COPY app/MediaFile.php ./app/MediaFile.php
COPY docker/webtrees/policy.xml /etc/ImageMagick-6/policy.xml
COPY resources/views/media-page-details.phtml ./resources/views/media-page-details.phtml
COPY resources/views/individual-page-images.phtml ./resources/views/individual-page-images.phtml
COPY resources/views/modals/media-file-fields.phtml ./resources/views/modals/media-file-fields.phtml
COPY resources/views/selects/individual.phtml ./resources/views/selects/individual.phtml

