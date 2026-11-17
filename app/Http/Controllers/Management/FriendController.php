<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Resources\Management\UserCardResource;
use App\Models\Friendship;
use App\Models\User;
use App\Support\Live;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Взаимная дружба: заявка из профиля + личная ссылка-приглашение.
 * Друзья нужны планировщику — звать в совместный просмотр можно только их.
 */
class FriendController extends Controller
{
    /** Друзья, входящие и исходящие заявки — одним ответом для страницы «Друзья». */
    public function index(Request $request)
    {
        $me = $request->user();

        $rows = Friendship::query()
            ->where(fn ($q) => $q->where('user_id', $me->id)->orWhere('friend_id', $me->id))
            ->with(['user', 'friend'])
            ->orderByDesc('accepted_at')
            ->orderByDesc('id')
            ->get();

        $other = fn (Friendship $f) => $f->user_id == $me->id ? $f->friend : $f->user;

        return response()->json([
            'friends' => UserCardResource::collection(
                $rows->filter->isAccepted()->map($other)->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()
            ),
            'incoming' => UserCardResource::collection(
                $rows->reject->isAccepted()->where('friend_id', $me->id)->map($other)->values()
            ),
            'outgoing' => UserCardResource::collection(
                $rows->reject->isAccepted()->where('user_id', $me->id)->map($other)->values()
            ),
        ]);
    }

    /**
     * Заявка. Если он уже звал меня — это встречная заявка, сразу дружба.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $me = $request->user();
        $target = User::findOrFail($data['user_id']);

        if ($target->id == $me->id) {
            abort(422, 'Нельзя добавить в друзья самого себя.');
        }

        $existing = Friendship::query()->between($me->id, $target->id)->first();

        if ($existing === null) {
            Friendship::create(['user_id' => $me->id, 'friend_id' => $target->id]);
        } elseif (! $existing->isAccepted() && $existing->friend_id == $me->id) {
            $existing->update(['accepted_at' => now()]);
        }

        Live::notify([$me->id, $target->id], 'friends');

        return response()->json(['state' => $me->friendshipStateWith($target)]);
    }

    /** Принять входящую заявку. */
    public function accept(Request $request, User $user)
    {
        $me = $request->user();

        $pending = Friendship::query()
            ->where('user_id', $user->id)
            ->where('friend_id', $me->id)
            ->whereNull('accepted_at')
            ->firstOrFail();

        $pending->update(['accepted_at' => now()]);

        Live::notify([$me->id, $user->id], 'friends');

        return response()->json(['state' => 'friends']);
    }

    /**
     * Одна кнопка на три случая: удалить из друзей, отклонить входящую,
     * отозвать исходящую. Совместные планы при этом остаются как есть.
     */
    public function destroy(Request $request, User $user)
    {
        if (Friendship::query()->between($request->user()->id, $user->id)->delete() > 0) {
            Live::notify([$request->user()->id, $user->id], 'friends');
        }

        return response()->json(['state' => 'none']);
    }

    /** Личная ссылка-приглашение; заводится при первом запросе. */
    public function invite(Request $request)
    {
        $me = $request->user();

        if ($me->friend_invite_token === null) {
            $me->forceFill(['friend_invite_token' => Str::random(32)])->save();
        }

        return response()->json(['url' => $this->inviteUrl($me->friend_invite_token)]);
    }

    /** Новая ссылка, старая перестаёт работать. */
    public function resetInvite(Request $request)
    {
        $me = $request->user();
        $me->forceFill(['friend_invite_token' => Str::random(32)])->save();

        return response()->json(['url' => $this->inviteUrl($me->friend_invite_token)]);
    }

    /** Кто зовёт — для страницы приглашения перед подтверждением. */
    public function previewInvite(Request $request, string $token)
    {
        $inviter = $this->inviter($token);

        return response()->json([
            'user' => new UserCardResource($inviter),
            'state' => $request->user()->friendshipStateWith($inviter),
        ]);
    }

    /** Принять приглашение: дружба сразу, без заявки. */
    public function acceptInvite(Request $request, string $token)
    {
        $me = $request->user();
        $inviter = $this->inviter($token);

        if ($inviter->id == $me->id) {
            abort(422, 'Это ваша собственная ссылка — отправьте её другу.');
        }

        DB::transaction(function () use ($me, $inviter) {
            $existing = Friendship::query()->between($me->id, $inviter->id)->lockForUpdate()->first();

            if ($existing === null) {
                Friendship::create([
                    'user_id' => $inviter->id,
                    'friend_id' => $me->id,
                    'accepted_at' => now(),
                ]);
            } elseif (! $existing->isAccepted()) {
                $existing->update(['accepted_at' => now()]);
            }
        });

        Live::notify([$me->id, $inviter->id], 'friends');

        return response()->json([
            'user' => new UserCardResource($inviter),
            'state' => 'friends',
        ]);
    }

    private function inviter(string $token): User
    {
        if (strlen($token) !== 32) {
            abort(404);
        }

        return User::query()->where('friend_invite_token', $token)->firstOrFail();
    }

    private function inviteUrl(string $token): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/friends/invite/'.$token;
    }
}
