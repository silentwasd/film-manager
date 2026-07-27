<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->string('visibility', 16)->default('hidden')->after('description');
            $table->index('visibility');
        });

        // Прежние приватные коллекции становятся личными: они и так были
        // «обычными» для своих авторов, а hidden — это новый, более закрытый
        // уровень, в который никто не переводил их осознанно.
        //
        // Побочный эффект: после миграции они появятся в публичных профилях
        // авторов, хотя раньше не были видны никому.
        DB::table('collections')->where('is_public', true)->update(['visibility' => 'public']);
        DB::table('collections')->where('is_public', false)->update(['visibility' => 'personal']);

        Schema::table('collections', function (Blueprint $table) {
            $table->dropIndex(['is_public']);
            $table->dropColumn('is_public');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('description');
            $table->index('is_public');
        });

        DB::table('collections')->where('visibility', 'public')->update(['is_public' => true]);

        Schema::table('collections', function (Blueprint $table) {
            $table->dropIndex(['visibility']);
            $table->dropColumn('visibility');
        });
    }
};
