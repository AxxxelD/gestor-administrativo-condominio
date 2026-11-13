<?php

namespace App\Services;

class Money
{
    public static function parse(string $value): int
    {
        return self::parsePrecision($value, 2);
    }

    public static function rate(string $value): int
    {
        $rate = self::parsePrecision($value, 6);
        if ($rate <= 0) {
            throw new \InvalidArgumentException('La tasa debe ser mayor que cero.');
        }

        return $rate;
    }

    public static function format(int $cents): string
    {
        return bcdiv((string) $cents, '100', 2);
    }

    public static function bsToUsd(int $bsCents, int $rateMicros): int
    {
        return self::ratio($bsCents, 1000000, $rateMicros);
    }

    public static function usdToBs(int $usdCents, int $rateMicros): int
    {
        if ($rateMicros <= 0) {
            throw new \InvalidArgumentException('La tasa debe ser mayor que cero.');
        }

        return self::ratio($usdCents, $rateMicros, 1000000);
    }

    public static function ratio(int $amount, int $numerator, int $denominator): int
    {
        if ($denominator <= 0) {
            throw new \InvalidArgumentException('El divisor debe ser mayor que cero.');
        }
        $product = bcmul((string) $amount, (string) $numerator, 0);
        $sign = str_starts_with($product, '-') ? -1 : 1;
        $absolute = ltrim($product, '-');
        $quotient = bcdiv($absolute, (string) $denominator, 0);
        $remainder = bcmod($absolute, (string) $denominator, 0);
        if (bccomp(bcmul($remainder, '2', 0), (string) $denominator, 0) >= 0) {
            $quotient = bcadd($quotient, '1', 0);
        }

        return self::checkedInteger(bcmul($quotient, (string) $sign, 0));
    }

    private static function parsePrecision(string $value, int $precision): int
    {
        $normalized = str_replace(',', '.', trim($value));
        if (! preg_match('/^-?\d+(?:\.\d{1,'.$precision.'})?$/D', $normalized)) {
            throw new \InvalidArgumentException('Introduce un número decimal sin separadores de miles, con hasta '.$precision.' decimales.');
        }

        return self::checkedInteger(bcmul($normalized, '1'.str_repeat('0', $precision), 0));
    }

    private static function checkedInteger(string $value): int
    {
        if (bccomp($value, (string) PHP_INT_MAX, 0) > 0 || bccomp($value, (string) PHP_INT_MIN, 0) < 0) {
            throw new \InvalidArgumentException('El importe excede el rango permitido.');
        }

        return (int) $value;
    }
}
