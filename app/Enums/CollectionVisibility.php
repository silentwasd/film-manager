<?php

namespace App\Enums;

enum CollectionVisibility: string
{
    /** Своя страница, видна в профиле автора, попадает в sitemap. */
    case Public = 'public';

    /** Своя страница и строка в профиле автора, но вне sitemap. */
    case Personal = 'personal';

    /** Доступна только владельцу. */
    case Hidden = 'hidden';

    /**
     * Уровни, при которых коллекцию видит кто угодно, кроме владельца.
     *
     * @return array<int, self>
     */
    public static function visible(): array
    {
        return [self::Public, self::Personal];
    }
}
