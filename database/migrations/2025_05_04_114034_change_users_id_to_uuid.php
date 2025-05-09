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
        // 2. Users: Cambiar id a uuid
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('id');
            $table->uuid('id')->primary();
        });

        // 3. Clients: Cambiar id y user_id a uuid
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('id');
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // 4. Workers: Cambiar id y user_id a uuid
        Schema::table('workers', function (Blueprint $table) {
            $table->dropColumn('id');
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // 5. Services: Cambiar id, client_id y worker_id a uuid
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('id');
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('worker_id');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('worker_id')->references('id')->on('workers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir services
        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['worker_id']);
            $table->dropColumn('id');
            $table->dropColumn('client_id');
            $table->dropColumn('worker_id');
            $table->bigIncrements('id')->primary();
            $table->bigInteger('client_id')->unsigned();
            $table->bigInteger('worker_id')->unsigned();
            $table->foreign('client_id')->references('id')->on('clients');
            $table->foreign('worker_id')->references('id')->on('workers');
        });

        // Revertir workers
        Schema::table('workers', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('id');
            $table->dropColumn('user_id');
            $table->bigIncrements('id')->primary();
            $table->bigInteger('user_id')->unsigned();
            $table->foreign('user_id')->references('id')->on('users');
        });

        // Revertir clients
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('id');
            $table->dropColumn('user_id');
            $table->bigIncrements('id')->primary();
            $table->bigInteger('user_id')->unsigned();
            $table->foreign('user_id')->references('id')->on('users');
        });

        // Revertir users
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('id');
            $table->bigIncrements('id')->primary();
        });
    }
};