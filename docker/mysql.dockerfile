ARG MYSQL_VERSION
FROM mysql:${MYSQL_VERSION}

LABEL maintainer="Mahmoud Zalt <mahmoud@zalt.me>"

#####################################
# Set Timezone
#####################################

ARG TZ=UTC
ENV TZ ${TZ}
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone && chown -R mysql:root /var/lib/mysql/

COPY ./mysql/my.cnf /etc/mysql/conf.d/my.cnf

RUN chmod 0444 /etc/mysql/conf.d/my.cnf

# Remove deprecated query_cache variables from any MySQL config files
RUN find /etc/mysql -name "*.cnf" -exec sed -i '/query_cache/d' {} \; 2>/dev/null || true

# Clean up any existing data directory
RUN rm -rf /var/lib/mysql/* 2>/dev/null || true

RUN mysqld --initialize-insecure --user=mysql

CMD ["mysqld"]

EXPOSE 3306
