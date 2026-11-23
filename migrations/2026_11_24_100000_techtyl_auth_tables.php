<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        // Einladungscodes: nur der SHA-256-Wert liegt in der Datenbank, der Klartext wird einmalig angezeigt.
        if (!Schema::hasTable('techtyl_auth_invites')) {
            Schema::create('techtyl_auth_invites', function (Blueprint $table) {
                $table->increments('id');
                $table->char('code_hash', 64)->unique();
                $table->string('hint', 8);
                $table->string('label', 80)->nullable();
                $table->unsignedInteger('max_uses')->default(1);
                $table->unsignedInteger('uses')->default(0);
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        // Ausstehende Registrierungen: ein Nutzerkonto entsteht erst nach dem Klick auf den Link in der E-Mail.
        if (!Schema::hasTable('techtyl_auth_pending')) {
            Schema::create('techtyl_auth_pending', function (Blueprint $table) {
                $table->increments('id');
                $table->string('email', 191)->unique();
                $table->string('username', 191);
                $table->string('name_first', 191);
                $table->string('name_last', 191);
                $table->char('token_hash', 64)->unique();
                $table->unsignedInteger('invite_id')->nullable();
                $table->string('locale', 5)->default('en');
                $table->unsignedSmallInteger('sent_count')->default(0);
                $table->timestamp('window_started_at')->nullable();
                $table->timestamp('last_sent_at')->nullable();
                $table->timestamp('expires_at')->index();
                $table->timestamps();
                $table->foreign('invite_id')->references('id')->on('techtyl_auth_invites')->cascadeOnDelete();
            });
        }

        // Verknuepfte Discord-/Google-Konten. Angemeldet wird nur ueber Anbieter + Anbieter-ID.
        if (!Schema::hasTable('techtyl_auth_identities')) {
            Schema::create('techtyl_auth_identities', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id');
                $table->string('provider', 16);
                $table->string('provider_user_id', 64);
                $table->string('label', 191)->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->timestamps();
                $table->unique(['provider', 'provider_user_id']);
                $table->unique(['user_id', 'provider']);
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('techtyl_auth_identities');
        Schema::dropIfExists('techtyl_auth_pending');
        Schema::dropIfExists('techtyl_auth_invites');
    }
};
