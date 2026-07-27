<?php

namespace App\Models;

use App\Enums\CollectionVisibility;
use App\Models\Concerns\HasPublicKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collection extends Model
{
    use HasFactory, HasPublicKey;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'visibility',
    ];

    protected $casts = [
        'visibility' => CollectionVisibility::class,
    ];

    /**
     * Дублирует дефолт из миграции, иначе только что созданная модель отдаёт
     * visibility = null до перечитывания из БД.
     */
    protected $attributes = [
        'visibility' => CollectionVisibility::Hidden->value,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function films(): HasMany
    {
        return $this->hasMany(CollectionFilm::class);
    }

    /** Только то, что индексируется: страница + профиль + sitemap. */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('visibility', CollectionVisibility::Public);
    }

    /** Всё, что вообще видно не владельцу: публичные и личные. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereIn('visibility', CollectionVisibility::visible());
    }

    /**
     * Единственный адрес страницы для данного уровня доступа. Публичная живёт
     * на коротком пути, личная — внутри профиля автора; второго адреса ни у
     * той, ни у другой нет, чужой путь отдаёт 404.
     *
     * Для личной нужна загруженная связь user.
     */
    public function publicPath(): ?string
    {
        return match ($this->visibility) {
            CollectionVisibility::Public => '/collections/'.$this->public_key,
            CollectionVisibility::Personal => '/users/'.$this->user->public_key.'/collections/'.$this->public_key,
            CollectionVisibility::Hidden => null,
        };
    }

    public function publicUrl(): ?string
    {
        $path = $this->publicPath();

        return $path === null ? null : config('app.frontend_url').$path;
    }
}
