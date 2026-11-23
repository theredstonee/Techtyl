#!/bin/bash
# Wird von Blueprint beim Entfernen der Extension aufgerufen (als Web-Nutzer, im Panel-Ordner).
# Das Framework macht keinen Rollback: Tabellen und hochgeladene Bilder raeumt dieses Skript weg.

cd "${PTERODACTYL_DIRECTORY:-.}" || exit 0

# Ersetzte Panel-Bausteine zuerst zurueckschreiben, damit der folgende Neubau das Original enthaelt.
php .blueprint/extensions/techtyl/private/patch.php restore \
  || echo "techtyl: Originale konnten nicht vollstaendig wiederhergestellt werden (.blueprint/extensions/techtyl/private/.backup)."

MIGRATION="2026_11_23_170000_create_techtyl_tables"

# Tabellen entfernen und den Eintrag in "migrations" loeschen, damit eine spaetere
# Neuinstallation die Tabellen wieder anlegt. Es werden nur die eigenen Tabellen angefasst.
php artisan tinker --execute="
foreach (['techtyl_folder_servers', 'techtyl_folders', 'techtyl_egg_banners', 'techtyl_server_banners', 'techtyl_user_settings'] as \$table) {
    Illuminate\Support\Facades\Schema::dropIfExists(\$table);
}
Illuminate\Support\Facades\DB::table('migrations')->where('migration', '$MIGRATION')->delete();
Illuminate\Support\Facades\DB::table('migrations')->where('migration', '2026_11_23_180000_import_techtyl_eggs')->delete();
echo 'techtyl: Tabellen entfernt';
" || echo "techtyl: Tabellen konnten nicht entfernt werden, bitte von Hand pruefen (techtyl_*)."

rm -f "database/migrations/$MIGRATION.php"
# Importierte Eggs bleiben bestehen (Server koennten sie verwenden), nur die Import-Migration geht.
rm -f "database/migrations/2026_11_23_180000_import_techtyl_eggs.php"

# Hochgeladene Bilder.
rm -rf "storage/.extensions/techtyl"

exit 0
