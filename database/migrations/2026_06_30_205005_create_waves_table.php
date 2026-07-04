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
        Schema::create('waves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formation_id')->constrained()->onDelete('cascade');

            $table->string('code_vague')->unique();        // ⭐ NOUVEAU - Code unique (ex: WAVE-JANV-2026)
            $table->string('name');                        // Nom de la cohorte (ex: Vague de Mars, Cohorte B)
            $table->date('start_date');
            $table->date('end_date');
             $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('draft');
            $table->boolean('is_active')->default(true);   // ⭐ NOUVEAU - Pour activer/désactiver
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waves');
    }
};
