<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('films', function (Blueprint $table) {
            // Сколько сезонов у сериала. NULL — не указано: тогда отзывы
            // можно оставить только на сериал целиком.
            $table->unsignedSmallInteger('seasons')->nullable()->after('format');
        });
    }

    public function down(): void
    {
        Schema::table('films', function (Blueprint $table) {
            $table->dropColumn('seasons');
        });
    }
};
