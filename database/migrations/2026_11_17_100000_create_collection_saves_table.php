<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Чужие коллекции, которые пользователь добавил к себе. Это закладка, а не
     * копия: состав и название остаются у автора, удаление строки убирает
     * коллекцию только из списка этого пользователя.
     */
    public function up(): void
    {
        Schema::create('collection_saves', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('collection_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['user_id', 'collection_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_saves');
    }
};
