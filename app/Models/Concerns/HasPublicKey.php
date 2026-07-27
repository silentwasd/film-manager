<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

/**
 * Читаемый ключ публичной страницы вида "12-luchshie-boeviki",
 * собранный из id и слага атрибута name.
 */
trait HasPublicKey
{
    /**
     * Декоративная часть ключа. Не хранится: искать всё равно по id,
     * поэтому переименование не ломает уже разосланные ссылки.
     */
    protected function slug(): Attribute
    {
        return Attribute::get(fn () => Str::slug($this->name, '-', 'ru'));
    }

    /**
     * Названия без латинской транслитерации (иероглифы, эмодзи) дают пустой
     * слаг — тогда ключом остаётся голый id.
     */
    protected function publicKey(): Attribute
    {
        return Attribute::get(fn () => $this->slug === ''
            ? (string) $this->id
            : $this->id.'-'.$this->slug);
    }

    /**
     * Извлекает id из ключа. Слаг игнорируется — он существует только
     * для читаемости ссылки, поэтому устаревший всё равно откроет страницу.
     */
    public static function idFromPublicKey(string $key): ?int
    {
        return preg_match('/^(\d+)/', $key, $matches) === 1
            ? (int) $matches[1]
            : null;
    }
}
