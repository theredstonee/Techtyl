<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        // Gespraeche je Nutzer. Der gewaehlte Server und die Zustimmung zum Mitschicken von
        // Server-Daten gelten nur fuer dieses eine Gespraech.
        if (!Schema::hasTable('techtyl_assistant_conversations')) {
            Schema::create('techtyl_assistant_conversations', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id');
                $table->string('title', 120);
                $table->unsignedInteger('server_id')->nullable();
                $table->boolean('share_context')->default(false);
                $table->timestamps();
                $table->index(['user_id', 'updated_at']);
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('server_id')->references('id')->on('servers')->nullOnDelete();
            });
        }

        // Nachrichten eines Gespraechs. Mitgeschickte Server-Daten werden nicht gespeichert,
        // nur ihr Umfang (context_lines).
        if (!Schema::hasTable('techtyl_assistant_messages')) {
            Schema::create('techtyl_assistant_messages', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('conversation_id');
                $table->string('role', 12);
                $table->mediumText('content');
                $table->string('status', 12)->default('ok');
                $table->unsignedSmallInteger('context_lines')->nullable();
                $table->string('provider', 24)->nullable();
                $table->string('model', 120)->nullable();
                $table->unsignedInteger('tokens_in')->nullable();
                $table->unsignedInteger('tokens_out')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['conversation_id', 'id']);
                $table->foreign('conversation_id')->references('id')->on('techtyl_assistant_conversations')->cascadeOnDelete();
            });
        }

        // Verbrauch je Nutzer und Tag (Zaehler fuer das Tageslimit und die Admin-Uebersicht).
        if (!Schema::hasTable('techtyl_assistant_usage')) {
            Schema::create('techtyl_assistant_usage', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id');
                $table->date('day');
                $table->unsignedInteger('requests')->default(0);
                $table->unsignedBigInteger('tokens_in')->default(0);
                $table->unsignedBigInteger('tokens_out')->default(0);
                $table->timestamps();
                $table->unique(['user_id', 'day']);
                $table->index('day');
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('techtyl_assistant_usage');
        Schema::dropIfExists('techtyl_assistant_messages');
        Schema::dropIfExists('techtyl_assistant_conversations');
    }
};
