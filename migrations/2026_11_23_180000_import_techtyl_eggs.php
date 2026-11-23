<?php

use Illuminate\Database\Migrations\Migration;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Eggs;

return new class () extends Migration {
    /**
     * Importiert die mitgelieferten Eggs bei der Installation. Schlaegt nie fehl: gelingt der
     * Import hier nicht, holt ihn install.sh oder der erste Aufruf der Admin-Seite nach.
     */
    public function up(): void
    {
        if (class_exists(Eggs::class)) {
            Eggs::importOnce();
        }
    }

    public function down(): void
    {
        // Importierte Eggs bleiben bestehen; Server koennten sie verwenden.
    }
};
