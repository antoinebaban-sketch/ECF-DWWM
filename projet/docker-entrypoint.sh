#!/bin/sh
# Render (et la plupart des hébergeurs Docker) imposent le port d'écoute via la
# variable d'environnement $PORT plutôt que le port 80 fixe par défaut d'Apache.
set -e
: "${PORT:=80}"

sed -ri "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -ri "s/:80>/:${PORT}>/g" /etc/apache2/sites-available/000-default.conf

exec "$@"
