<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('films', function (Blueprint $table) {
            $table->string('moderation_status')
                  ->default('published')
                  ->after('cinema_status');

            $table->string('moderation_comment')
                  ->nullable()
                  ->after('moderation_status');
        });
    }

    public function down(): void
    {
        Schema::table('films', function (Blueprint $table) {
            $table->dropColumn(['moderation_status', 'moderation_comment']);
        });
    }
};
