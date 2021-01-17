FROM php:7.4-apache

COPY . /var/www/html/
WORKDIR /var/www/html

RUN a2enmod rewrite && chmod 777 logs

EXPOSE 80

CMD rm -f /var/run/apache2/apache2.pid ; chown :www-data /var/www/html -R && chown www-data:www-data /var/www/html/logs -R && apachectl -D FOREGROUND