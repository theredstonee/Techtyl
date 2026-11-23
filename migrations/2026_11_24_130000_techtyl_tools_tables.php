<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        // Zuordnung Egg -> Servertyp (vom Admin gepflegt; ohne Eintrag gilt die Heuristik).
        if (!Schema::hasTable('techtyl_tools_egg_types')) {
            Schema::create('techtyl_tools_egg_types', function (Blueprint $table) {
                $table->unsignedInteger('egg_id')->primary();
                $table->string('type', 20);
                $table->timestamps();
                $table->foreign('egg_id')->references('id')->on('eggs')->cascadeOnDelete();
            });
        }

        // Was Techtyl auf einem Server installiert hat (Plugins und Mods aus Modrinth).
        if (!Schema::hasTable('techtyl_tools_installed_content')) {
            Schema::create('techtyl_tools_installed_content', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('server_id');
                $table->string('kind', 12);
                $table->string('directory', 20);
                $table->string('project_id', 16);
                $table->string('title', 160);
                $table->string('icon_url', 255)->nullable();
                $table->string('version_id', 16);
                $table->string('version_number', 80);
                $table->string('filename', 191);
                $table->char('sha1', 40);
                $table->unsignedBigInteger('size');
                $table->unsignedInteger('installed_by')->nullable();
                $table->timestamps();
                $table->unique(['server_id', 'directory', 'filename'], 'techtyl_tools_content_file');
                $table->index(['server_id', 'project_id'], 'techtyl_tools_content_project');
                $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
                $table->foreign('installed_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        // Lange Vorgaenge (Installieren, Aktualisieren, Versionswechsel) mit Status und Fortschritt.
        if (!Schema::hasTable('techtyl_tools_tasks')) {
            Schema::create('techtyl_tools_tasks', function (Blueprint $table) {
                $table->increments('id');
                $table->char('uuid', 36)->unique();
                $table->unsignedInteger('server_id');
                $table->unsignedInteger('user_id')->nullable();
                $table->string('kind', 40);
                $table->string('status', 12);
                $table->string('step', 40)->default('');
                $table->unsignedTinyInteger('progress')->default(0);
                $table->string('title', 191)->default('');
                $table->mediumText('payload');
                $table->text('result')->nullable();
                $table->string('error', 255)->nullable();
                $table->char('runner', 16)->nullable();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('locked_until')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
                $table->index(['server_id', 'status'], 'techtyl_tools_tasks_active');
                $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('techtyl_tools_tasks');
        Schema::dropIfExists('techtyl_tools_installed_content');
        Schema::dropIfExists('techtyl_tools_egg_types');
    }
};
