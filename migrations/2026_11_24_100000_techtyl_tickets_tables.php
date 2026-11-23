<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('techtyl_tickets_categories')) {
            Schema::create('techtyl_tickets_categories', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 60)->unique();
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();
            });

            $now = now();
            foreach (['Technik', 'Abrechnung', 'Allgemein'] as $position => $name) {
                DB::table('techtyl_tickets_categories')->insert([
                    'name' => $name,
                    'position' => $position,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (!Schema::hasTable('techtyl_tickets_tickets')) {
            Schema::create('techtyl_tickets_tickets', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('category_id')->nullable();
                $table->unsignedInteger('server_id')->nullable();
                $table->string('subject', 120);
                // open = liegt beim Team, answered = Antwort vom Team, waiting = wartet auf den Nutzer, closed
                $table->string('status', 16)->default('open');
                // Ungelesene Nachrichten der jeweils anderen Seite.
                $table->unsignedInteger('user_unread')->default(0);
                $table->unsignedInteger('team_unread')->default(0);
                // Seit wann der Nutzer am Zug ist (Grundlage fuer das automatische Schliessen).
                $table->timestamp('awaiting_user_since')->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->string('closed_by', 8)->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('category_id')->references('id')->on('techtyl_tickets_categories')->nullOnDelete();
                $table->foreign('server_id')->references('id')->on('servers')->nullOnDelete();
                $table->index(['user_id', 'status']);
                $table->index(['status', 'awaiting_user_since']);
                $table->index('last_message_at');
            });
        }

        if (!Schema::hasTable('techtyl_tickets_messages')) {
            Schema::create('techtyl_tickets_messages', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('ticket_id');
                $table->unsignedInteger('user_id')->nullable();
                $table->boolean('is_team')->default(false);
                // message = Nachricht; closed, reopened, autoclosed, status = Ereignis im Verlauf
                $table->string('type', 16)->default('message');
                $table->text('body')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->foreign('ticket_id')->references('id')->on('techtyl_tickets_tickets')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                $table->index(['ticket_id', 'id']);
            });
        }

        if (!Schema::hasTable('techtyl_tickets_attachments')) {
            Schema::create('techtyl_tickets_attachments', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('ticket_id');
                $table->unsignedInteger('message_id');
                $table->string('file', 64)->unique();
                $table->unsignedInteger('width');
                $table->unsignedInteger('height');
                $table->unsignedInteger('size');
                $table->timestamp('created_at')->nullable();

                $table->foreign('ticket_id')->references('id')->on('techtyl_tickets_tickets')->cascadeOnDelete();
                $table->foreign('message_id')->references('id')->on('techtyl_tickets_messages')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('techtyl_tickets_attachments');
        Schema::dropIfExists('techtyl_tickets_messages');
        Schema::dropIfExists('techtyl_tickets_tickets');
        Schema::dropIfExists('techtyl_tickets_categories');
    }
};
