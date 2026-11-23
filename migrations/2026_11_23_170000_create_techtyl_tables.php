<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        // Persoenliche Einstellungen: Teil-Design als JSON und eigenes Hintergrundbild.
        if (!Schema::hasTable('techtyl_user_settings')) {
            Schema::create('techtyl_user_settings', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id')->unique();
                $table->text('design')->nullable();
                $table->string('background_file', 64)->nullable();
                $table->timestamps();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        // Eigenes Banner je Server (gilt fuer alle, die den Server sehen).
        if (!Schema::hasTable('techtyl_server_banners')) {
            Schema::create('techtyl_server_banners', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('server_id')->unique();
                $table->string('file', 64);
                $table->unsignedInteger('uploaded_by')->nullable();
                $table->timestamps();
                $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
                $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        // Standard-Banner je Egg, vom Admin gesetzt.
        if (!Schema::hasTable('techtyl_egg_banners')) {
            Schema::create('techtyl_egg_banners', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('egg_id')->unique();
                $table->string('file', 64);
                $table->timestamps();
                $table->foreign('egg_id')->references('id')->on('eggs')->cascadeOnDelete();
            });
        }

        // Server-Ordner je Nutzer.
        if (!Schema::hasTable('techtyl_folders')) {
            Schema::create('techtyl_folders', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id');
                $table->string('name', 40);
                $table->unsignedSmallInteger('position')->default(0);
                $table->timestamps();
                $table->index(['user_id', 'position']);
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        // Zuordnung Server -> Ordner. Ein Server liegt je Nutzer in hoechstens einem Ordner.
        if (!Schema::hasTable('techtyl_folder_servers')) {
            Schema::create('techtyl_folder_servers', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('folder_id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('server_id');
                $table->unsignedSmallInteger('position')->default(0);
                $table->unique(['user_id', 'server_id']);
                $table->index(['folder_id', 'position']);
                $table->foreign('folder_id')->references('id')->on('techtyl_folders')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('techtyl_folder_servers');
        Schema::dropIfExists('techtyl_folders');
        Schema::dropIfExists('techtyl_egg_banners');
        Schema::dropIfExists('techtyl_server_banners');
        Schema::dropIfExists('techtyl_user_settings');
    }
};
