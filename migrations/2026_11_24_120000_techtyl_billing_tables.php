<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Tabellen des Moduls "Abrechnung". Geldbeträge sind immer ganze Cent (Integer).
 */
return new class () extends Migration {
    public function up(): void
    {
        // Pakete, die Nutzer bestellen können.
        if (!Schema::hasTable('techtyl_billing_packages')) {
            Schema::create('techtyl_billing_packages', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 60);
                $table->string('description', 300)->default('');
                $table->unsignedInteger('memory');
                $table->unsignedInteger('cpu');
                $table->unsignedInteger('disk');
                $table->unsignedSmallInteger('databases')->default(0);
                $table->unsignedSmallInteger('backups')->default(0);
                $table->unsignedSmallInteger('allocations')->default(0);
                $table->text('egg_ids');
                $table->text('node_ids');
                $table->text('location_ids');
                $table->unsignedInteger('price_month');
                $table->unsignedInteger('price_day')->nullable();
                $table->boolean('active')->default(true);
                $table->integer('position')->default(0);
                $table->timestamps();
                $table->index(['active', 'position']);
            });
        }

        // Ein Konto je Nutzer: Stripe-Kunde und aktueller Guthabenstand (wird nur zusammen mit
        // einer Buchungszeile und unter Zeilensperre verändert).
        if (!Schema::hasTable('techtyl_billing_accounts')) {
            Schema::create('techtyl_billing_accounts', function (Blueprint $table) {
                $table->unsignedInteger('user_id')->primary();
                $table->string('stripe_customer_id', 80)->nullable()->unique();
                $table->bigInteger('balance')->default(0);
                $table->timestamps();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        // Unveränderliche Buchungszeilen. "reference" ist eindeutig und verhindert Doppelbuchungen.
        if (!Schema::hasTable('techtyl_billing_ledger')) {
            Schema::create('techtyl_billing_ledger', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('user_id');
                $table->bigInteger('amount');
                $table->bigInteger('balance_after');
                $table->string('reason', 24);
                $table->string('reference', 120)->unique();
                $table->string('note', 300)->default('');
                $table->unsignedInteger('actor_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['user_id', 'id']);
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        // Abos: Stripe-Abo (kind "stripe") oder tägliche Abbuchung vom Guthaben (kind "balance").
        if (!Schema::hasTable('techtyl_billing_subscriptions')) {
            Schema::create('techtyl_billing_subscriptions', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('package_id')->nullable();
                $table->string('package_name', 60);
                $table->text('spec');
                $table->string('kind', 12);
                $table->string('status', 16);
                $table->string('provision_state', 16)->default('none');
                $table->string('provision_error', 300)->default('');
                $table->timestamp('provision_started_at')->nullable();
                $table->unsignedInteger('server_id')->nullable();
                $table->string('server_name', 60);
                $table->unsignedInteger('egg_id');
                $table->unsignedInteger('price');
                $table->string('currency', 3);
                $table->string('stripe_checkout_id', 120)->nullable()->unique();
                $table->string('stripe_subscription_id', 80)->nullable()->unique();
                $table->timestamp('current_period_end')->nullable();
                $table->boolean('cancel_at_period_end')->default(false);
                $table->unsignedBigInteger('stripe_event_time')->default(0);
                $table->timestamp('past_due_since')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->date('last_charged_on')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->timestamp('server_deleted_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status']);
                $table->index(['kind', 'status']);
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('package_id')->references('id')->on('techtyl_billing_packages')->nullOnDelete();
                $table->foreign('server_id')->references('id')->on('servers')->nullOnDelete();
            });
        }

        // Falls die Tabelle aus einem früheren Entwicklungsstand ohne diese Spalte stammt.
        if (!Schema::hasColumn('techtyl_billing_subscriptions', 'stripe_event_time')) {
            Schema::table('techtyl_billing_subscriptions', function (Blueprint $table) {
                $table->unsignedBigInteger('stripe_event_time')->default(0);
            });
        }

        // Zahlungen über Stripe (Aufladungen, Abo-Rechnungen, Erstattungen).
        if (!Schema::hasTable('techtyl_billing_payments')) {
            Schema::create('techtyl_billing_payments', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('subscription_id')->nullable();
                $table->string('kind', 12);
                $table->string('status', 12);
                $table->unsignedInteger('amount');
                $table->unsignedInteger('refunded')->default(0);
                $table->string('currency', 3);
                $table->string('stripe_ref', 120)->nullable()->unique();
                $table->string('stripe_payment_intent', 80)->nullable()->index();
                $table->timestamps();
                $table->index(['user_id', 'id']);
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('subscription_id')->references('id')->on('techtyl_billing_subscriptions')->nullOnDelete();
            });
        }

        // Bereits verarbeitete Stripe-Ereignisse (jedes genau einmal).
        if (!Schema::hasTable('techtyl_billing_events')) {
            Schema::create('techtyl_billing_events', function (Blueprint $table) {
                $table->string('id', 100)->primary();
                $table->string('type', 80);
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // Protokoll: Bereitstellung, Sperren, Entsperren, Löschen, manuelle Buchungen.
        if (!Schema::hasTable('techtyl_billing_log')) {
            Schema::create('techtyl_billing_log', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('action', 32);
                $table->unsignedInteger('subscription_id')->nullable();
                $table->unsignedInteger('user_id')->nullable();
                $table->unsignedInteger('server_id')->nullable();
                $table->unsignedInteger('actor_id')->nullable();
                $table->string('detail', 400)->default('');
                $table->timestamp('created_at')->useCurrent();
                $table->index('subscription_id');
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        foreach (['log', 'events', 'payments', 'subscriptions', 'ledger', 'accounts', 'packages'] as $name) {
            Schema::dropIfExists('techtyl_billing_' . $name);
        }
    }
};
