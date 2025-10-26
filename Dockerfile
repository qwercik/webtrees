FROM php:8.4-apache AS base
RUN apt-get update -yqq && \
    apt-get install -yqq --no-install-recommends curl libmagickwand-dev libzip-dev mariadb-client patch python3 unzip && \
    pecl install imagick && \
    docker-php-ext-enable imagick && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j"$(nproc)" pdo pdo_mysql zip intl gd exif && \
    apt-get autoremove -y && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /var/tmp/* /etc/apache2/sites-enabled/000-*.conf && \
    a2enmod rewrite headers remoteip && \
    usermod -u 1000 www-data && \
    chown www-data:www-data /var/www/html


FROM base AS buildable
RUN curl -o /usr/local/bin/composer https://getcomposer.org/composer.phar && \
    chmod +x /usr/local/bin/composer


FROM buildable AS dev
ENV NVM_DIR /root/.nvm
RUN curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.3/install.sh | bash \
    && . $NVM_DIR/nvm.sh \
    && nvm install 24.10.0
RUN apt-get update -yqq && \
    apt-get install -yqq --no-install-recommends build-essential vim git && \
    pecl install xdebug && \
    docker-php-ext-enable xdebug
COPY composer.json composer.lock package.json package-lock.json ./

FROM buildable AS build-prod
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    docker-php-ext-install opcache
USER www-data

COPY .htaccess composer.json composer.lock favicon.ico index.php package-lock.json package.json webpack.mix.js ./
COPY app ./app
COPY modules_v4 ./modules_v4
COPY public ./public
COPY resources ./resources
COPY data/.htaccess data/index.php ./data/
RUN find . -maxdepth 2 -name composer.json -execdir composer install --prefer-dist --no-progress --no-dev --no-scripts --optimize-autoloader \;


FROM base AS prod
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    docker-php-ext-install opcache
RUN apt-get purge gcc g++ make -y
COPY docker/www/policy.xml /etc/ImageMagick-6/policy.xml
COPY docker/www/remoteip.conf /etc/apache2/conf-enabled/
COPY docker/www/prod/security.conf /etc/apache2/conf-enabled/
COPY --from=build-prod /var/www/html .
RUN chown -R www-data:www-data .
USER www-data