<?php

namespace App\Http\Controllers\Management;

use App\Enums\WatchPlanStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Management\WatchPlanResource;
use App\Models\User;
use App\Models\WatchPlan;
use App\Services\WatchPlans\WatchlistSync;
use App\Support\Live;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Планировщик просмотров: общая очередь с участниками. Каждый участник видит
 * запись у себя и может её двигать — права у всех участников равные.
 */
class WatchPlanController extends Controller
{
    /** Сколько последних досмотренных отдавать — колонка не бесконечная. */
    private const WATCHED_LIMIT = 50;

    public function index(Request $request)
    {
        $data = $request->validate([
            // Только планы, где участвует этот друг.
            'friend_id' => 'nullable|integer',
        ]);

        $me = $request->user();

        $base = fn () => WatchPlan::query()
            ->withParticipant($me->id)
            ->when($data['friend_id'] ?? null, fn (Builder $q, int $friendId) => $q->withParticipant($friendId))
            ->with(['film', 'participants']);

        return response()->json([
            'planned' => WatchPlanResource::collection(
                $base()->where('status', WatchPlanStatus::Planned)->orderBy('position')->orderBy('id')->get()
            ),
            'watching' => WatchPlanResource::collection(
                $base()->where('status', WatchPlanStatus::Watching)->orderBy('started_at')->orderBy('id')->get()
            ),
            'watched' => WatchPlanResource::collection(
                $base()->where('status', WatchPlanStatus::Watched)
                    ->orderByDesc('finished_at')
                    ->orderByDesc('updated_at')
                    ->limit(self::WATCHED_LIMIT)
                    ->get()
            ),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'film_id' => 'required|integer|exists:films,id',
            'participants' => 'nullable|array|max:20',
            'participants.*' => 'integer',
            'note' => 'nullable|string|max:255',
        ]);

        $me = $request->user();
        $others = $this->validParticipants($me, $data['participants'] ?? []);

        $plan = DB::transaction(function () use ($me, $data, $others) {
            $plan = WatchPlan::create([
                'film_id' => $data['film_id'],
                'created_by' => $me->id,
                'note' => $data['note'] ?? null,
                'position' => WatchPlan::nextPosition(),
            ]);

            $plan->participants()->sync([$me->id, ...$others]);

            return $plan;
        });

        Live::notify([$me->id, ...$others], 'plans');

        return new WatchPlanResource($plan->load(['film', 'participants']));
    }

    /**
     * Заметка и состав. Себя из состава так не убрать — для этого leave;
     * добавить можно только своих друзей, уже участвующих — оставить.
     */
    public function update(Request $request, WatchPlan $watchPlan)
    {
        $me = $request->user();
        $this->authorizeParticipant($me, $watchPlan);

        $data = $request->validate([
            'note' => 'sometimes|nullable|string|max:255',
            'participants' => 'sometimes|array|max:20',
            'participants.*' => 'integer',
        ]);

        // И выкинутые из состава должны узнать, что запись у них пропала.
        $before = $watchPlan->participants()->pluck('users.id')->all();

        DB::transaction(function () use ($me, $watchPlan, $data) {
            if (array_key_exists('note', $data)) {
                $watchPlan->update(['note' => $data['note']]);
            }

            if (array_key_exists('participants', $data)) {
                $current = $watchPlan->participants()->pluck('users.id')->all();
                $others = $this->validParticipants($me, $data['participants'], $current);

                $watchPlan->participants()->sync([$me->id, ...$others]);
            }
        });

        Live::notify([...$before, ...$watchPlan->participants()->pluck('users.id')->all()], 'plans');

        return new WatchPlanResource($watchPlan->load(['film', 'participants']));
    }

    /**
     * Смена колонки. Даты ставятся сами: «Смотрим» — с сегодня, «Посмотрели» —
     * сегодня. Вперёд статус уходит и в «Мои фильмы» всех участников.
     */
    public function status(Request $request, WatchPlan $watchPlan, WatchlistSync $sync)
    {
        $me = $request->user();
        $this->authorizeParticipant($me, $watchPlan);

        $data = $request->validate([
            'status' => ['required', Rule::enum(WatchPlanStatus::class)],
            // Статус, который видел клиент. Друг успел переложить запись —
            // 409 вместо молчаливой перезаписи его действия.
            'from' => ['nullable', Rule::enum(WatchPlanStatus::class)],
        ]);

        $status = WatchPlanStatus::from($data['status']);

        if (isset($data['from']) && $data['from'] !== $watchPlan->status->value) {
            abort(409, 'Запись уже переложили — список обновлён.');
        }

        if ($status === $watchPlan->status) {
            return new WatchPlanResource($watchPlan->load(['film', 'participants']));
        }

        DB::transaction(function () use ($watchPlan, $status, $sync) {
            $today = now()->toDateString();

            $watchPlan->fill(match ($status) {
                WatchPlanStatus::Planned => [
                    'started_at' => null,
                    'finished_at' => null,
                    'position' => WatchPlan::nextPosition(),
                ],
                WatchPlanStatus::Watching => [
                    'started_at' => $watchPlan->started_at ?? $today,
                    'finished_at' => null,
                ],
                WatchPlanStatus::Watched => [
                    'started_at' => $watchPlan->started_at ?? $today,
                    'finished_at' => $today,
                ],
            });

            $watchPlan->status = $status;
            $watchPlan->save();

            $sync->apply($watchPlan);
        });

        Live::notify($watchPlan->participants()->pluck('users.id')->all(), 'plans');

        return new WatchPlanResource($watchPlan->load(['film', 'participants']));
    }

    /**
     * Порядок «В планах». Приходят id в нужном порядке — ровно тот набор,
     * что сейчас виден пользователю (с учётом фильтра по другу). Позиции
     * этих записей переставляются между собой, чужие записи не сдвигаются.
     */
    public function reorder(Request $request)
    {
        $data = $request->validate([
            'plans' => 'required|array|min:1',
            'plans.*' => 'integer|distinct',
        ]);

        $me = $request->user();

        $plans = WatchPlan::query()
            ->withParticipant($me->id)
            ->where('status', WatchPlanStatus::Planned)
            ->whereKey($data['plans'])
            ->get()
            ->keyBy('id');

        if ($plans->count() !== count($data['plans'])) {
            throw ValidationException::withMessages([
                'plans' => 'Список устарел — обновите страницу.',
            ]);
        }

        $positions = $plans->pluck('position')->sort()->values();

        // Совпадающие позиции (старые данные) развели бы порядок непредсказуемо.
        if ($positions->unique()->count() !== $positions->count()) {
            $start = WatchPlan::nextPosition();
            $positions = collect(range($start, $start + $positions->count() - 1));
        }

        DB::transaction(function () use ($data, $plans, $positions) {
            foreach ($data['plans'] as $index => $id) {
                $plans[$id]->update(['position' => $positions[$index]]);
            }
        });

        Live::notify(
            DB::table('watch_plan_user')->whereIn('watch_plan_id', $plans->keys())->pluck('user_id')->all(),
            'plans'
        );

        return response()->noContent();
    }

    /** Выйти из плана; ушёл последний участник — запись удаляется. */
    public function leave(Request $request, WatchPlan $watchPlan)
    {
        $me = $request->user();
        $this->authorizeParticipant($me, $watchPlan);

        $participants = $watchPlan->participants()->pluck('users.id')->all();

        DB::transaction(function () use ($me, $watchPlan) {
            $watchPlan->participants()->detach($me->id);

            if (! $watchPlan->participants()->exists()) {
                $watchPlan->delete();
            }
        });

        Live::notify($participants, 'plans');

        return response()->noContent();
    }

    /** Удалить план целиком — у всех участников. */
    public function destroy(Request $request, WatchPlan $watchPlan)
    {
        $this->authorizeParticipant($request->user(), $watchPlan);

        $participants = $watchPlan->participants()->pluck('users.id')->all();

        $watchPlan->delete();

        Live::notify($participants, 'plans');

        return response()->noContent();
    }

    /**
     * Чужой план неотличим от несуществующего.
     */
    private function authorizeParticipant(User $user, WatchPlan $plan): void
    {
        if (! $plan->hasParticipant($user)) {
            abort(404);
        }
    }

    /**
     * Остальные участники: друзья пользователя, плюс те, кто уже в плане
     * (друга мог позвать другой участник, выкидывать его при правке заметки
     * нельзя).
     *
     * @param  array<int, int>  $requested
     * @param  array<int, int>  $current
     * @return array<int, int>
     */
    private function validParticipants(User $me, array $requested, array $current = []): array
    {
        $requested = array_values(array_unique(array_map('intval', $requested)));
        $requested = array_values(array_diff($requested, [$me->id]));

        $allowed = [...$me->friendIds(), ...$current];
        $foreign = array_values(array_diff($requested, $allowed));

        if ($foreign !== []) {
            throw ValidationException::withMessages([
                'participants' => 'Позвать в просмотр можно только друзей.',
            ]);
        }

        return $requested;
    }
}
