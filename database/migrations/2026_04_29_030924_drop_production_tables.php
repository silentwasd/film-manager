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
        Schema::dropIfExists('film_video_variants');
        Schema::dropIfExists('film_audio_variants');

        Schema::table('films', function (Blueprint $table) {
            $table->dropForeign(['download_id']);
            $table->dropColumn('download_id');
        });

        Schema::dropIfExists('downloads');
    }

    public function down(): void
    {
        // Intentionally left empty — production pipeline was removed permanently
    }
};
