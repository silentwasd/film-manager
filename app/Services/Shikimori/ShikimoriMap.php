<?php

namespace App\Services\Shikimori;

use App\Enums\FilmFormat;
use App\Enums\PersonRole;

/**
 * Таблицы соответствия между терминологией Шикимори и справочниками проекта.
 */
class ShikimoriMap
{
    /**
     * Роли персонала. Ключи — английские названия из roles[], они у API стабильны
     * (русские roles_russian меняются и для части ролей вообще не переведены).
     *
     * Намеренно НЕ полный список: у среднего тайтла в /roles больше сотни человек,
     * из них 70+ — рядовые аниматоры («Ключевая анимация», «Промежуточ. анимация»,
     * «Второстепен. анимация»). В справочнике people им места нет, поэтому берём
     * только то, что ложится на PersonRole.
     */
    public const array STAFF_ROLES = [
        'Director'                 => PersonRole::Director,
        'Chief Director'           => PersonRole::Director,
        'Series Director'          => PersonRole::Director,

        'Producer'                 => PersonRole::Producer,
        'Executive Producer'       => PersonRole::Producer,
        'Assistant Producer'       => PersonRole::Producer,
        'Animation Producer'       => PersonRole::Producer,

        'Script'                   => PersonRole::Screenwriter,
        'Screenplay'               => PersonRole::Screenwriter,
        'Series Composition'       => PersonRole::Screenwriter,
        'Original Creator'         => PersonRole::Screenwriter,
        'Original Story'           => PersonRole::Screenwriter,

        'Music'                    => PersonRole::Composer,
        'Theme Song Composition'   => PersonRole::Composer,
        'Theme Song Arrangement'   => PersonRole::Composer,

        'Sound Director'           => PersonRole::SoundDirector,
        'Sound Effects'            => PersonRole::SoundDirector,

        'Character Design'         => PersonRole::Artist,
        'Original Character Design' => PersonRole::Artist,
        'Art Director'             => PersonRole::Artist,

        'Editing'                  => PersonRole::Editor,

        'Director of Photography'  => PersonRole::Operator,
        'Photography'              => PersonRole::Operator,

        'Translation Director'     => PersonRole::Translator,
    ];

    /**
     * Порядок ролей в карточке — тот же, что в FilmController::show.
     */
    public const array ROLE_ORDER = [
        PersonRole::Director->value,
        PersonRole::Screenwriter->value,
        PersonRole::Producer->value,
        PersonRole::Composer->value,
        PersonRole::Artist->value,
        PersonRole::SoundDirector->value,
        PersonRole::Operator->value,
        PersonRole::Editor->value,
        PersonRole::Translator->value,
        PersonRole::VoiceActor->value,
    ];

    /**
     * kind Шикимори → формат карточки.
     *
     * Аниме — всегда анимация, поэтому film/series здесь не используются:
     * полнометражки идут в cartoon, сериальные форматы — в cartoon-series.
     */
    public static function format(?string $kind, ?int $episodes): FilmFormat
    {
        return match ($kind) {
            'movie'  => FilmFormat::Cartoon,
            'music'  => FilmFormat::Cartoon,
            default  => ($episodes ?? 0) > 1
                ? FilmFormat::CartoonSeries
                : FilmFormat::Cartoon
        };
    }

    /**
     * Транслитерация названия жанра в slug — заводимые жанры должны выглядеть
     * так же, как заведённые руками (у существующих 19 slug латиницей).
     */
    public static function slug(string $name): string
    {
        $map = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e',
            'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k',
            'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r',
            'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts',
            'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya'
        ];

        $slug = strtr(mb_strtolower($name), $map);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        return trim($slug, '-');
    }

    /**
     * Описания Шикимори размечены BB-кодами со ссылками на их же сущности:
     * [character=118717]Симамурой[/character], [spoiler]…[/spoiler], [b]…[/b].
     * В text-поле описания это мусор, поэтому разметку снимаем, текст оставляем.
     */
    public static function description(?string $raw): ?string
    {
        if (!$raw)
            return null;

        // Хвост вида "Источник: Wikipedia" и парная разметка со ссылкой на сущность.
        $text = preg_replace('/\[(character|anime|manga|ranobe|person|club|user)=[^\]]*\]/ui', '', $raw);
        $text = preg_replace('/\[\/(character|anime|manga|ranobe|person|club|user)\]/ui', '', $text);

        // [spoiler], [b], [i], [url=…] и прочее — снимаем теги, содержимое оставляем.
        $text = preg_replace('/\[\/?[a-z_]+(=[^\]]*)?\]/ui', '', $text);

        return trim(html_entity_decode($text)) ?: null;
    }
}
