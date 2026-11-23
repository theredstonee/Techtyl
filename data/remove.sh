#!/bin/bash
# Wird von Blueprint beim Entfernen der Extension aufgerufen (als Web-Nutzer, im Panel-Ordner).
# Das Framework macht keinen Rollback: Tabellen und hochgeladene Bilder raeumt dieses Skript weg.

cd "${PTERODACTYL_DIRECTORY:-.}" || exit 0

# Ersetzte Panel-Bausteine zuerst zurueckschreiben, damit der folgende Neubau das Original enthaelt.
php .blueprint/extensions/techtyl/private/patch.php restore \
  || echo "techtyl: Originale konnten nicht vollstaendig wiederhergestellt werden (.blueprint/extensions/techtyl/private/.backup)."

# Alle Tabellen mit dem Praefix "techtyl_" entfernen (Kern und alle Module) und ihre Eintraege in
# "migrations" loeschen, damit eine spaetere Neuinstallation sie wieder anlegt. Andere Tabellen
# werden nicht angefasst. Importierte Eggs bleiben bestehen, Server koennten sie verwenden.
php artisan tinker --execute="
\$tables = array_values(array_filter(array_map(fn (\$t) => \$t['name'], Illuminate\Support\Facades\Schema::getTables()), fn (\$n) => str_starts_with(\$n, 'techtyl_')));
Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
foreach (\$tables as \$table) {
    Illuminate\Support\Facades\Schema::dropIfExists(\$table);
}
Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
Illuminate\Support\Facades\DB::table('migrations')->where('migration', 'like', '%techtyl%')->delete();
echo 'techtyl: ' . count(\$tables) . ' Tabellen entfernt';
" || echo "techtyl: Tabellen konnten nicht entfernt werden, bitte von Hand pruefen (techtyl_*)."

rm -f database/migrations/*techtyl*.php

# Hochgeladene Bilder.
rm -rf "storage/.extensions/techtyl"

exit 0
