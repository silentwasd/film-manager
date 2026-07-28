<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('films', function (Blueprint $table) {
            $table->unsignedBigInteger('shikimori_id')->nullable()->unique()->after('author_id');
        });
    }

    public function down(): void
    {
        Schema::table('films', function (Blueprint $table) {
            $table->dropUnique(['shikimori_id']);
            $table->dropColumn('shikimori_id');
        });
    }
};
