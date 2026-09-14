<?php

namespace losthost\OberdeskAPIv1;

class Auth
{
    private static ?array $current = null;
    private static bool $checked = false;

    public static function check(): void
    {
        if (self::$checked) {
            return;
        }

        self::$checked = true;

        // пока хардкод
        self::$current = [
            'id' => 1,
            'telegram_user_id' => 203645978,
            'name' => 'Петр Иоаннидис',
        ];
    }

    public static function get(): array
    {
        if (!self::$checked) {
            self::check();
        }

        if (self::$current === null) {
            throw new \RuntimeException('User not authorized');
        }

        return self::$current;
    }
}