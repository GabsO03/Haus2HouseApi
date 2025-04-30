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
        Schema::create('workers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('dni')->unique(); // Añadido: `unique` para evitar DNIs duplicados
            $table->foreignId('user_id')
                ->constrained(table: 'users', indexName: 'worker_user_id')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->text('bio')->nullable();
            $table->jsonb('habilidades')->nullable();
            $table->jsonb('disponibilidad')->nullable();
            $table->decimal('rating', 3, 2)->default(0.00);
            $table->integer('cantidad_ratings')->default(0);
            $table->boolean('active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workers');
    }
};
