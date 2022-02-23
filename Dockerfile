ARG PHP_VERSION=8.0
ARG APCU_VERSION=5.1.18

FROM php:${PHP_VERSION}-fpm-alpine

# Install composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=symfonycorp/cli /symfony /usr/bin/symfony

# Install make
RUN apk add --no-cache make

# Install php extensions
# persistent / runtime deps
RUN apk add --no-cache \
        acl \
        file \
        gettext \
        git \
        mariadb-client \
    ;

ARG APCU_VERSION=5.1.17
RUN set -eux; \
	apk add --no-cache --virtual .build-deps \
		$PHPIZE_DEPS \
		coreutils \
		freetype-dev \
		icu-dev \
		libjpeg-turbo-dev \
		libpng-dev \
		libtool \
		libwebp-dev \
		libzip-dev \
		mariadb-dev \
		zlib-dev \
	; \
	\
	docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype; \
	docker-php-ext-configure zip --with-zip; \
	docker-php-ext-install -j$(nproc) \
		exif \
		gd \
		intl \
		pdo_mysql \
		zip \
	; \
	pecl install \
		apcu-${APCU_VERSION} \
	; \
	pecl clear-cache; \
	docker-php-ext-enable \
		apcu \
		opcache \
	; \
	\
	runDeps="$( \
		scanelf --needed --nobanner --format '%n#p' --recursive /usr/local/lib/php/extensions \
			| tr ',' '\n' \
			| sort -u \
			| awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
	)"; \
	apk add --no-cache --virtual .sylius-phpexts-rundeps $runDeps; \
	\
	apk del .build-deps; \
    \
    cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini; \
    \
    cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php-cli.ini; \
    \
    sed -i 's/memory_limit = .*/memory_limit = 256M/' /usr/local/etc/php/php.ini; \
    \
    sed -i 's/memory_limit = .*/memory_limit = -1/' /usr/local/etc/php/php-cli.ini;


EXPOSE 8080
WORKDIR /srv/sylius

ARG APP_ENV=test

CMD ["symfony", "server:start", "--dir=tests/Application/public", "--port=8080", "--no-tls" ]
