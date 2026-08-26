<?php

namespace App\Support;

/**
 * Small helper for realistic Philippine demo data in factories/seeders.
 * Faker's en_PH locale only overrides Address/PhoneNumber, not Person names,
 * so this fills that gap with a compact real-name pool.
 */
class PhilippineFaker
{
    private const FIRST_NAMES = [
        'Juan', 'Jose', 'Maria', 'Ana', 'Pedro', 'Rosario', 'Ramon', 'Cristina',
        'Antonio', 'Ligaya', 'Ricardo', 'Ma. Theresa', 'Eduardo', 'Josefina',
        'Manuel', 'Corazon', 'Rodrigo', 'Imelda', 'Ferdinand', 'Leni',
        'Angelo', 'Marites', 'Jomar', 'Rowena', 'Kevin', 'Jasmine', 'Mark',
        'Angelica', 'Christian', 'Kimberly', 'Rafael', 'Precious', 'Dennis',
        'Grace', 'Michael', 'Joy', 'Paolo', 'Nicole', 'Vincent', 'Angel',
    ];

    private const LAST_NAMES = [
        'Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza',
        'Torres', 'Gonzales', 'Villanueva', 'Ramos', 'Aquino', 'Del Rosario',
        'Fernandez', 'Castillo', 'Flores', 'Rivera', 'Salazar', 'Tolentino',
        'Domingo', 'Pascual', 'Manalo', 'Navarro', 'Aguilar', 'Marquez',
    ];

    private const MOBILE_PREFIXES = [
        '905', '906', '907', '908', '909', '910', '912', '915', '916', '917',
        '918', '919', '920', '921', '922', '923', '925', '926', '927', '928',
        '929', '930', '939', '946', '947', '948', '949', '950', '951', '956',
    ];

    public static function fullName(): string
    {
        return self::FIRST_NAMES[array_rand(self::FIRST_NAMES)].' '.self::LAST_NAMES[array_rand(self::LAST_NAMES)];
    }

    /** +63 followed by exactly 10 digits — matches the normalized mobile column. */
    public static function mobile(): string
    {
        $prefix = self::MOBILE_PREFIXES[array_rand(self::MOBILE_PREFIXES)];

        return '+63'.$prefix.str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
    }

    public static function tin(): string
    {
        return implode('', array_map(fn () => (string) random_int(0, 9), range(1, 15)));
    }
}
