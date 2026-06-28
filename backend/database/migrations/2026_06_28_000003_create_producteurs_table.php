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
        Schema::create('producteurs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->string('prenom');
            $table->string('nom');
            $table->string('sexe', 10)->nullable();
            $table->string('localite')->nullable();
            $table->foreignUlid('cooperative_id')->constrained('cooperatives');
            $table->timestamp('consentement_le')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('producteurs');
    }
};
