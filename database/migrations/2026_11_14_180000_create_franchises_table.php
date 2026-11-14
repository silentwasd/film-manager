<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Франшиза — сквозная линейка тайтлов поверх студий: у «Черепашек-ниндзя»
 * права переходили от Mirage к Nickelodeon, и ни одна компания не покрывает
 * всё. Фильм может входить в несколько франшиз (кроссоверы).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('franchises', function (Blueprint $table) {
            $table->id();

            $table->foreignId('author_id')
                  ->nullable()
                  ->constrained('users')
                  ->cascadeOnUpdate()
                  ->nullOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('link')->nullable();

            $table->timestamps();
        });

        Schema::create('film_franchise', function (Blueprint $table) {
            $table->id();

            $table->foreignId('franchise_id')
                  ->constrained()
                  ->cascadeOnUpdate()
                  ->cascadeOnDelete();

            $table->foreignId('film_id')
                  ->constrained()
                  ->cascadeOnUpdate()
                  ->cascadeOnDelete();

            $table->unique(['franchise_id', 'film_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('film_franchise');
        Schema::dropIfExists('franchises');
    }
};
