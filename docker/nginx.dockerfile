FROM nginx:stable-alpine
RUN apk update && apk upgrade
RUN apt-get install -y net-tools vim bash
ENV NGINXUSER=laravel
ENV NGINXGROUP=laravel
RUN mkdir -p /var/www/html/public
ADD nginx/default.conf /etc/nginx/conf.d/default.conf
RUN sed -i 's/user www-data/user laravel/g' /etc/nginx/nginx.conf
RUN adduser -g ${NGINXGROUP}} -s /bin/sh -D ${NGINXUSER}
