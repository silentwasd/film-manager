<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            // Номер сезона, к которому относится отзыв. NULL — отзыв на тайтл
            // целиком: только он участвует в лайках, топе и «Моих фильмах».
            $table->unsignedSmallInteger('season')->nullable()->after('user_id');

            $table->index(['film_id', 'season']);
        });
    }

    public function down(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            $table->dropIndex(['film_id', 'season']);
            $table->dropColumn('season');
        });
    }
};
