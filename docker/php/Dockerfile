FROM php:8.4-fpm-bookworm

ARG USER_ID=1000
ARG GROUP_ID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libicu-dev libpq-dev libzip-dev libonig-dev librabbitmq-dev libssl-dev \
    && docker-php-ext-install -j$(nproc) intl pdo_pgsql bcmath zip opcache sockets \
    && pecl install redis xdebug amqp \
    && docker-php-ext-enable redis xdebug amqp \
    && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN if [ "$GROUP_ID" != "0" ]; then groupadd -g "$GROUP_ID" appgroup; fi \
    && if [ "$USER_ID" != "0" ]; then useradd -m -u "$USER_ID" -g "$GROUP_ID" -s /bin/bash appuser; fi

WORKDIR /var/www/html
