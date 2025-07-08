FROM dunglas/frankenphp:1-php8.3

RUN install-php-extensions \
	opcache

RUN set -eux; \
	install-php-extensions \
		@composer \
		opcache \
	;

COPY . /app