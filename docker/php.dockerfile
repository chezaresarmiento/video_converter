FROM php:8.2-fpm-alpine

RUN apk update && apk upgrade

RUN apk add vim

RUN set -x && apk add --no-cache freetype-dev libjpeg-turbo-dev libpng-dev libzip-dev bzip2-dev oniguruma-dev bash supervisor \
    && docker-php-ext-configure gd \
    && docker-php-ext-install gd

SHELL ["/bin/bash", "-c"]

ENV PHPGROUP=laravel
ENV PHPUSER=laravel
RUN adduser -g ${PHPGROUP} -s /bin/sh -D ${PHPUSER}
RUN sed -i 's/user = www-data/user = ${PHPUSER}/g' /usr/local/etc/php-fpm.d/www.conf
RUN sed -i 's/group = www-data/group = ${PHPGROUP}/g' /usr/local/etc/php-fpm.d/www.conf
RUN mkdir -p /var/www/html
RUN docker-php-ext-install pdo pdo_mysql bz2 zip

RUN apk add --no-cache ffmpeg

# Install Python3 and pip
RUN apk add --no-cache python3 py3-pip

# Create and activate a virtual environment
RUN python3 -m venv /opt/venv

# Install yt-dlp inside the virtual environment
RUN /opt/venv/bin/pip install --no-cache-dir yt-dlp

# Update PATH so the shell uses the virtual environment
ENV PATH="/opt/venv/bin:$PATH"

# Installing Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy php.ini-production to php.ini if it doesn't exist
RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

# Replace or add upload_max_filesize and post_max_size using sed
RUN sed -i 's/^upload_max_filesize.*/upload_max_filesize = 2000M/' /usr/local/etc/php/php.ini || echo "upload_max_filesize = 2000M" >> /usr/local/etc/php/php.ini
RUN sed -i 's/^post_max_size.*/post_max_size = 2000M/' /usr/local/etc/php/php.ini || echo "post_max_size = 2000M" >> /usr/local/etc/php/php.ini

RUN echo "memory_limit=256M" > /usr/local/etc/php/conf.d/memory-limit.ini

# Install Node.js and npm
RUN apk add --no-cache nodejs npm

COPY ./supervisord.conf /etc/supervisor/conf.d/supervisord.conf

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
