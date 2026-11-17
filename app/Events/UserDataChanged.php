<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * «У тебя что-то поменялось» — в планах или в друзьях. Данных в событии нет
 * намеренно: клиент перечитывает своё через API, и права проверяются там же,
 * а не повторяются тут.
 *
 * ShouldBroadcastNow — потому что воркера очередей у проекта нет.
 */
class UserDataChanged implements ShouldBroadcastNow
{
    /**
     * @param  array<int, int>  $userIds
     * @param  'plans'|'friends'  $topic
     */
    public function __construct(
        public array $userIds,
        public string $topic,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return array_map(
            fn (int $id) => new PrivateChannel('users.'.$id),
            array_values(array_unique($this->userIds)),
        );
    }

    public function broadcastAs(): string
    {
        return 'changed';
    }

    /** @return array<string, string> */
    public function broadcastWith(): array
    {
        return ['topic' => $this->topic];
    }
}
