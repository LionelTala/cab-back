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
        Schema::create('formations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();           // Code unique (ex: DEV-FS-PHP)
            $table->string('title');                    // Titre de la formation
            $table->string('slug')->unique();           // Pour URLs propres
            $table->text('description')->nullable();    // Description complète
            $table->integer('duree_formation');         // Durée en mois ⭐
            $table->decimal('prix', 10, 2)->default(0);   // Prix total ⭐
            $table->decimal('frais_scolarite', 10, 2)->default(0);  // Frais de scolarité ⭐
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('formations');
    }
};
