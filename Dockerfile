FROM nathanvaughn/webtrees:2.2.1 AS base

FROM base AS dev
RUN apt-get update -yqq && \
    apt-get install -yqq --no-install-recommends build-essential vim git && \
    pecl install xdebug && \
    docker-php-ext-enable xdebug

FROM base as prod
COPY app/Factories/RouteFactory.php /var/www/webtrees/app/Factories/RouteFactory.php
