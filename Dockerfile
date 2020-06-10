FROM php:7.4-apache

COPY . /var/www/html/
WORKDIR /var/www/html

RUN a2enmod rewrite
RUN chmod 664 logs

EXPOSE 80

CMD rm -f /var/run/apache2/apache2.pid ; chown :www-data /var/www/html -R ; apachectl -D FOREGROUND