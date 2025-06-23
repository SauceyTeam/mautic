FROM --platform=linux/amd64 php:8.3-apache

# Copy everything from common for building
COPY ./.build-config/common/ /common/

# Install PHP extensions
RUN apt-get update \
    && apt-get upgrade -y \
    && apt-get install --no-install-recommends -y \
    build-essential  \
    ca-certificates \
    curl \
    git \
    graphicsmagick \
    imagemagick \
    libaprutil1-dev \
    libc-client-dev \
    libcurl4-gnutls-dev \
    libfreetype6-dev \
    libgif-dev \
    libicu-dev \
    libjpeg-dev \
    libjpeg62-turbo-dev \
    libkrb5-dev \
    libmagickwand-dev \
    libmcrypt-dev \
    libonig-dev \
    libpng-dev \
    libpq-dev \
    librabbitmq-dev \
    libssl-dev \
    libtiff-dev \
    libwebp-dev \
    libxml2-dev \
    libxpm-dev \
    libz-dev \
    libzip-dev \
    nodejs \
    npm \
    unzip

RUN curl -L -o /tmp/amqp.tar.gz "https://github.com/php-amqp/php-amqp/archive/refs/tags/v2.1.2.tar.gz" \
    && mkdir -p /usr/src/php/ext/amqp \
    && tar -C /usr/src/php/ext/amqp -zxvf /tmp/amqp.tar.gz --strip 1 \
    && rm /tmp/amqp.tar.gz

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-configure opcache --enable-opcache \
    && docker-php-ext-install intl mbstring mysqli curl pdo_mysql zip bcmath sockets exif amqp gd imap opcache \
    && docker-php-ext-enable intl mbstring mysqli curl pdo_mysql zip bcmath sockets exif amqp gd imap opcache

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer

RUN echo "memory_limit = -1" > /usr/local/etc/php/php.ini

RUN apt-get update \
    && apt-get upgrade -y \
    && apt-get install --no-install-recommends -y \
    cron \
    git \
    libc-client-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    librabbitmq4 \
    libwebp-dev \
    libzip-dev \
    mariadb-client \
    supervisor \
    unzip \
    && apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && rm /etc/cron.daily/*

# Install Node.JS (LTS)
RUN curl -fsSL https://deb.nodesource.com/setup_lts.x | bash - && \
    apt-get install -y nodejs && \
    npm install -g npm@latest