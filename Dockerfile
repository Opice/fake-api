FROM php:7.4-apache

MAINTAINER radek.labut@gmail.com

RUN a2enmod rewrite

COPY . /var/www/html/

EXPOSE 80

CMD rm -f /var/run/apache2/apache2.pid ; chown www-data:www-data /var/www/html -R ; apachectl -D FOREGROUND