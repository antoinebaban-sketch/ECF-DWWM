FROM php:8.1-apache

# Extensions PHP : pdo_mysql (base relationnelle) + mongodb (base NoSQL, requiert
# une compilation via PECL car elle n'est pas incluse dans l'image de base) + curl
# (appel de l'API d'envoi d'email, voir helpers.php::sendMail).
RUN apt-get update && apt-get install -y --no-install-recommends \
        libssl-dev \
        libsasl2-dev \
        libcurl4-openssl-dev \
        pkg-config \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && docker-php-ext-install pdo pdo_mysql curl \
    && apt-get purge -y --auto-remove libssl-dev libsasl2-dev libcurl4-openssl-dev pkg-config \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# mod_rewrite pour les .htaccess (routage /api/* vers api/index.php)
# AllowOverride All pour que les .htaccess du projet soient bien lus
# variables_order=EGPCS pour que $_ENV lise les variables injectées par l'hébergeur
RUN a2enmod rewrite headers \
    && sed -ri -e 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && printf 'variables_order = "EGPCS"\ndisplay_errors = Off\n' > /usr/local/etc/php/conf.d/z-vite-et-gourmand.ini

WORKDIR /var/www/html
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
