<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ID Кинопоиска — ключ импорта из расширения браузера: по нему повторный
 * импорт узнаёт уже заведённую карточку, а персона — уже заведённого человека.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('films', function (Blueprint $table) {
            $table->unsignedBigInteger('kinopoisk_id')->nullable()->unique()->after('shikimori_id');
        });

        Schema::table('people', function (Blueprint $table) {
            $table->unsignedBigInteger('kinopoisk_id')->nullable()->unique()->after('shikimori_id');
        });
    }

    public function down(): void
    {
        Schema::table('films', function (Blueprint $table) {
            $table->dropUnique(['kinopoisk_id']);
            $table->dropColumn('kinopoisk_id');
        });

        Schema::table('people', function (Blueprint $table) {
            $table->dropUnique(['kinopoisk_id']);
            $table->dropColumn('kinopoisk_id');
        });
    }
};
