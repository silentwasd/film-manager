<?php

namespace App\Mcp\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Языковая модель оперирует названиями («драма», «Япония»), а не id из базы,
 * поэтому фильтры принимают строки и разбирают их здесь. Точное совпадение
 * важнее подстроки: «драма» не должна тянуть за собой «мелодраму».
 */
trait ResolvesTaxonomies
{
    /**
     * Ненайденное название даёт пустой список id — фильтр по нему вернёт
     * пустую выдачу, и это честнее, чем молча его отбросить. О таком случае
     * инструмент сообщает отдельно, см. warnings в search_films.
     *
     * @param  class-string<Model>  $model
     * @param  array<int, string>  $names
     * @return array<int, int>
     */
    protected function idsByName(string $model, array $names): array
    {
        $names = array_values(array_filter(array_map('trim', $names)));

        if ($names === []) {
            return [];
        }

        $exact = $model::query()
            ->where(function ($query) use ($names): void {
                foreach ($names as $name) {
                    $query->orWhereRaw('LOWER(name) = ?', [mb_strtolower($name)]);
                }
            })
            ->pluck('id')
            ->all();

        if ($exact !== []) {
            return array_map('intval', $exact);
        }

        // Точного совпадения нет — пробуем подстроку, иначе «фантастика»
        // против жанра «Научная фантастика» вернёт пустоту на ровном месте.
        return array_map('intval', $model::query()
            ->where(function ($query) use ($names): void {
                foreach ($names as $name) {
                    $query->orWhere('name', 'LIKE', '%'.$name.'%');
                }
            })
            ->pluck('id')
            ->all());
    }
}
