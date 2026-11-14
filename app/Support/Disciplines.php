<?php

namespace App\Support;

/**
 * Las seis disciplinas del reglamento IPSC combinado.
 * La letra es la que usa el reglamento en sus códigos entre corchetes, p. ej. [HRSP].
 */
final class Disciplines
{
    public const ALL = [
        'H' => 'Pistola (Handgun)',
        'R' => 'Rifle',
        'S' => 'Escopeta (Shotgun)',
        'M' => 'Mini rifle',
        'P' => 'Carabina de calibre pistola (PCC)',
        'T' => 'Pistola .22LR',
    ];

    public static function codes(): array
    {
        return array_keys(self::ALL);
    }

    public static function valid(string $code): bool
    {
        return isset(self::ALL[$code]);
    }

    public static function name(string $code): string
    {
        return self::ALL[$code] ?? $code;
    }
}
