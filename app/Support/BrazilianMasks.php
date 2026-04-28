<?php

namespace App\Support;

class BrazilianMasks
{
    public static function onlyDigits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    public static function formatCpf(?string $value): ?string
    {
        $digits = self::onlyDigits($value);

        if ($digits === '') {
            return null;
        }

        $digits = substr($digits, 0, 11);

        if (strlen($digits) !== 11) {
            return $value;
        }

        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits);
    }

    public static function formatCnpj(?string $value): ?string
    {
        $digits = self::onlyDigits($value);

        if ($digits === '') {
            return null;
        }

        $digits = substr($digits, 0, 14);

        if (strlen($digits) !== 14) {
            return $value;
        }

        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits);
    }

    public static function formatCpfOrCnpj(?string $value): ?string
    {
        $digits = self::onlyDigits($value);

        return strlen($digits) > 11 ? self::formatCnpj($digits) : self::formatCpf($digits);
    }

    public static function formatPhone(?string $value): ?string
    {
        $digits = self::onlyDigits($value);

        if ($digits === '') {
            return null;
        }

        $digits = substr($digits, 0, 11);

        if (strlen($digits) === 11) {
            return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $digits);
        }

        if (strlen($digits) === 10) {
            return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $digits);
        }

        return $value;
    }

    public static function hasCpfLength(?string $value): bool
    {
        $digits = self::onlyDigits($value);

        return $digits === '' || strlen($digits) === 11;
    }

    public static function hasCnpjLength(?string $value): bool
    {
        $digits = self::onlyDigits($value);

        return $digits === '' || strlen($digits) === 14;
    }

    public static function hasCpfOrCnpjLength(?string $value): bool
    {
        $digits = self::onlyDigits($value);

        return $digits === '' || in_array(strlen($digits), [11, 14], true);
    }

    public static function hasPhoneLength(?string $value): bool
    {
        $digits = self::onlyDigits($value);

        return $digits === '' || in_array(strlen($digits), [10, 11], true);
    }
}
