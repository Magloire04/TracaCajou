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
        Schema::create('lots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->foreignUlid('producteur_id')->constrained('producteurs');
            $table->foreignUlid('cooperative_id')->constrained('cooperatives');
            $table->decimal('poids_kg', 8, 2);
            $table->decimal('humidite_pct', 5, 2);
            $table->decimal('prix_kg_fcfa', 10, 2);
            $table->decimal('montant_fcfa', 12, 2);
            $table->date('date_pesee');
            $table->string('statut', 20)->default('enregistre');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};
