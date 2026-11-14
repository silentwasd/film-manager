<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Превью постера для карточек и сеток: исходник с Кинопоиска в среднем 670 КБ
 * при показе шириной 130 px. Пусто — превью нет, фронт берёт `cover`.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('films', function (Blueprint $table) {
            $table->string('cover_thumb')->nullable()->after('cover');
        });
    }

    public function down(): void
    {
        Schema::table('films', function (Blueprint $table) {
            $table->dropColumn('cover_thumb');
        });
    }
};
