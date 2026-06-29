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
        Schema::create('certificats', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('lot_id')->constrained('lots');
            $table->ulid('public_uuid')->unique();
            $table->text('payload_hash');
            $table->text('signature');
            $table->string('statut', 20)->default('certifie');
            $table->timestamp('emis_le')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificats');
    }
};
