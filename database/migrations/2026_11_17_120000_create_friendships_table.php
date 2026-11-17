<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Дружба взаимная: одна строка на пару. user_id — кто позвал, friend_id —
     * кого. Пока accepted_at пуст, это заявка; принята — друзья в обе стороны.
     *
     * friend_invite_token — личная ссылка-приглашение: открывший её сразу
     * становится другом, без заявки.
     */
    public function up(): void
    {
        Schema::create('friendships', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('friend_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'friend_id']);
            $table->index(['friend_id', 'accepted_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('friend_invite_token', 40)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['friend_invite_token']);
            $table->dropColumn('friend_invite_token');
        });

        Schema::dropIfExists('friendships');
    }
};
