<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create users table
        Schema::create('users', function (Blueprint $table) {
            $table->text('id')->primary(); // Using text for Xata
            $table->text('nombre');
            $table->text('email')->unique();
            $table->timestamp('email_verified_at')->nullable(); // timestamptz in Xata
            $table->text('password');
            $table->text('remember_token')->nullable();
            $table->text('rol')->default('client');
            $table->text('profile_photo')->nullable();
            $table->text('telefono')->nullable();
            $table->text('direccion')->nullable();
            $table->float('latitude', 10, 8)->nullable();
            $table->float('longitude', 11, 8)->nullable();
            $table->timestamps(); // timestamptz in Xata
        });

        // 2. Create password_reset_tokens table
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->text('email')->primary();
            $table->text('token');
            $table->timestamp('created_at')->nullable(); // timestamptz
        });

        // 3. Create sessions table
        Schema::create('sessions', function (Blueprint $table) {
            $table->text('id')->primary();
            $table->text('user_id')->nullable()->index();
            $table->text('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('payload');
            $table->integer('last_activity')->index();
        });

        // 4. Create service_types table
        Schema::create('service_types', function (Blueprint $table) {
            $table->bigIncrements('id'); // Kept as integer per your preference
            $table->text('name');
            $table->text('description')->nullable();
            $table->float('base_rate_per_hour', 8, 2);
            $table->text('adicionales')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps(); // timestamptz
        });

        // 5. Create clients table
        Schema::create('clients', function (Blueprint $table) {
            $table->text('id')->primary();
            $table->text('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->text('stripe_customer_id')->nullable();
            $table->float('rating', 3, 2)->default(0.0);
            $table->integer('cantidad_ratings')->default(0);
            $table->timestamps(); // timestamptz
        });

        // 6. Create workers table
        Schema::create('workers', function (Blueprint $table) {
            $table->text('id')->primary();
            $table->text('dni')->unique();
            $table->text('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->text('bio')->nullable();
            $table->json('services_id')->nullable(); // Changed to json
            $table->json('disponibilidad')->nullable(); // Changed to json
            $table->json('horario_semanal')->nullable(); // Changed to json
            $table->float('rating', 3, 2)->default(0.00);
            $table->integer('cantidad_ratings')->default(0);
            $table->boolean('active')->default(false);
            $table->timestamps(); // timestamptz
        });

        // 7. Create services table
        Schema::create('services', function (Blueprint $table) {
            $table->text('id')->primary();
            $table->text('client_id');
            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->text('worker_id');
            $table->foreign('worker_id')
                ->references('id')
                ->on('workers')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->unsignedBigInteger('service_type_id');
            $table->foreign('service_type_id')
                ->references('id')
                ->on('service_types')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->text('description');
            $table->text('specifications');
            $table->timestamp('request_time'); // timestamptz
            $table->timestamp('start_time'); // timestamptz
            $table->timestamp('end_time')->nullable(); // timestamptz
            $table->float('duration_hours')->nullable();
            $table->text('client_location');
            $table->text('worker_location')->nullable();
            $table->text('status')->default('pending');
            $table->float('total_amount', 8, 2);
            $table->text('payment_stripe_id')->nullable();
            $table->text('payment_method')->nullable();
            $table->text('payment_status')->nullable();
            $table->integer('client_rating')->nullable()->between(1, 5);
            $table->integer('worker_rating')->nullable()->between(1, 5);
            $table->text('client_comments')->nullable();
            $table->text('worker_comments')->nullable();
            $table->text('incident_report')->nullable();
            $table->timestamps(); // timestamptz
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
        Schema::dropIfExists('workers');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('service_types');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};