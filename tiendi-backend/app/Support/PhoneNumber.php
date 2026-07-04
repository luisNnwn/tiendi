<?php

namespace App\Support;

class PhoneNumber
{
    public const EL_SALVADOR_COUNTRY_CODE = '503';

    public static function normalize(?string $phone): ?string
    {
        return self::normalizeElSalvador($phone);
    }

    public static function normalizeElSalvador(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, self::EL_SALVADOR_COUNTRY_CODE)) {
            $national = substr($digits, 3);

            if (strlen($national) === 8) {
                return self::EL_SALVADOR_COUNTRY_CODE.$national;
            }
        }

        if (strlen($digits) === 8) {
            return self::EL_SALVADOR_COUNTRY_CODE.$digits;
        }

        return $digits;
    }

    public static function isValidElSalvador(?string $phone): bool
    {
        return is_string($phone) && preg_match('/^503[0-9]{8}$/', $phone) === 1;
    }

    public static function toLocal(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $normalized = self::normalizeElSalvador($phone);

        if (! self::isValidElSalvador($normalized)) {
            return null;
        }

        return substr($normalized, 3);
    }

    public static function formatDisplay(?string $phone): ?string
    {
        $local = self::toLocal($phone);

        if ($local === null) {
            return null;
        }

        return '+'.self::EL_SALVADOR_COUNTRY_CODE.' '.substr($local, 0, 4).'-'.substr($local, 4);
    }
}
