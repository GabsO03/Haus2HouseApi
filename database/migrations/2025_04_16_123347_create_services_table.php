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
        Schema::create('services', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('client_id')
                ->constrained(table: 'clients', indexName: 'service_client_id')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('worker_id')
                ->constrained(table: 'workers', indexName: 'service_worker_id')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('service_type_id')
                ->constrained(table: 'service_types', indexName: 'service_service_type_id')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->string('description');
            $table->string('specifications');
            $table->timestamp('request_time');
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->float('duration_hours')->nullable();
            $table->string('client_location');
            $table->string('worker_location')->nullable();
            $table->string('status')->default('pending');
            $table->decimal('total_amount', 8, 2);
            $table->string('payment_stripe_id')->nullable();
            $table->integer('client_rating')->nullable()->between(1, 5);
            $table->integer('worker_rating')->nullable()->between(1, 5);
            $table->text('client_comments')->nullable();
            $table->text('worker_comments')->nullable();
            $table->text('incident_report')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_services');
    }
};
