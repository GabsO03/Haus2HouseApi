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
        Schema::create('clients', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')
                ->constrained(table: 'users', indexName: 'client_user_id')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->string('stripe_customer_id')->nullable();
            $table->decimal('rating', 3, 2)->default(0.0);
            $table->integer('cantidad_ratings')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
