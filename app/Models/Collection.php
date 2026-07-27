<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Collection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    /**
     * Дублирует дефолт из миграции, иначе только что созданная модель отдаёт
     * is_public = null до перечитывания из БД.
     */
    protected $attributes = [
        'is_public' => false,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function films(): HasMany
    {
        return $this->hasMany(CollectionFilm::class);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * Декоративная часть публичной ссылки. Не хранится: ключом остаётся id,
     * поэтому переименование коллекции не ломает уже разосланные ссылки.
     */
    protected function slug(): Attribute
    {
        return Attribute::get(fn () => Str::slug($this->name, '-', 'ru'));
    }

    /**
     * Ключ публичной страницы вида "12-luchshie-boeviki".
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
     * Извлекает id из ключа публичной страницы. Слаг игнорируется —
     * он существует только для читаемости ссылки.
     */
    public static function idFromPublicKey(string $key): ?int
    {
        return preg_match('/^(\d+)/', $key, $matches) === 1
            ? (int) $matches[1]
            : null;
    }
}
