<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Планировщик просмотров: общая очередь «что смотрим» с участниками.
     * Не календарь — фильм смотрят день, а сериал три, поэтому у записи
     * только статус и даты начала и конца.
     *
     * position — общий ключ порядка в «В планах». У каждого участника свой
     * набор записей, переупорядочивание переставляет позиции только внутри
     * этого набора, не задевая чужие.
     */
    public function up(): void
    {
        Schema::create('watch_plans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('film_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('status', 16)->default('planned');
            $table->unsignedBigInteger('position')->default(0);
            $table->string('note', 255)->nullable();
            $table->date('started_at')->nullable();
            $table->date('finished_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'position']);
        });

        Schema::create('watch_plan_user', function (Blueprint $table) {
            $table->foreignId('watch_plan_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->primary(['watch_plan_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watch_plan_user');
        Schema::dropIfExists('watch_plans');
    }
};
