<?php

namespace App\Enums;

enum NotificationType: string
{
    case PROMO = 'promo';
    case NEWS = 'news';
    case INFO = 'info';
    case ALERT = 'alert';
    case UPDATE = 'update';

    /**
     * Get all values of the enum.
     *
     * @return array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
