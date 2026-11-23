#!/bin/bash
# Wird von Blueprint am Ende der Installation aufgerufen (als Web-Nutzer, im Panel-Ordner).
# Legt die Tabellen an (Blueprint ueberspringt das bei Docker-Installationen) und importiert
# die mitgelieferten Eggs. Beides darf mehrfach laufen und laesst die Installation nie scheitern.

cd "${PTERODACTYL_DIRECTORY:-.}" || exit 0

php artisan migrate --force || echo "techtyl: Tabellen konnten nicht angelegt werden, bitte 'php artisan migrate --force' von Hand ausfuehren."

php artisan tinker --execute="echo 'techtyl eggs: ' . json_encode(\\Pterodactyl\\BlueprintFramework\\Extensions\\techtyl\\Eggs::importOnce());" \
  || echo "techtyl: Eggs konnten nicht importiert werden; der Import wird beim Oeffnen der Techtyl-Einstellungen nachgeholt."

# German account language: the panel validates users.language against resources/lang/*, so the
# folder must exist before any account can be switched to German.
mkdir -p resources/lang/de

# Eigene Bausteine statt der Panel-Komponenten einsetzen (Originale werden gesichert).
# Laeuft vor dem Neubau der Oberflaeche, damit die Ersetzungen im Bundle landen.
php .blueprint/extensions/techtyl/private/patch.php apply \
  || echo "techtyl: Bausteine konnten nicht ersetzt werden; das Panel nutzt an diesen Stellen seine normalen Komponenten."

exit 0
