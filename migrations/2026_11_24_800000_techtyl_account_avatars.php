<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        // Profilbild je Nutzer: zufaelliger Dateiname der neu kodierten Fassung (256 x 256).
        // Die Datei liegt unter storage/.extensions/techtyl/avatars und geht mit dem Nutzer weg.
        if (!Schema::hasTable('techtyl_account_avatars')) {
            Schema::create('techtyl_account_avatars', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id')->unique();
                $table->string('file', 64);
                $table->timestamps();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('techtyl_account_avatars');
    }
};
