FROM php:8-apache
COPY index.php /var/www/html/
COPY sample.txt /var/www/html/
RUN cd /usr/local/etc/php; \
    sed -e 's@;open_basedir =@open_basedir = /var/www/html@' -i.bak php.ini-*
RUN cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini
