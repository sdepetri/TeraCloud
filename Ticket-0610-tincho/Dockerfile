FROM php:7.4-apache
COPY index.php /var/www/html/

# iniciar Apache y ejecutar PHP
ENTRYPOINT ["apachectl", "-D", "FOREGROUND"]