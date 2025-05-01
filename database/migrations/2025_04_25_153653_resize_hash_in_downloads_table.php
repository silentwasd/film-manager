<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('downloads', function (Blueprint $table) {
            $table->string('url', 512)->nullable()->change();
            $table->string('hash', 512)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('downloads', function (Blueprint $table) {
            $table->string('url', 255)->nullable()->change();
            $table->string('hash', 255)->nullable()->change();
        });
    }
};
