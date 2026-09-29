<?php

namespace App\Support;

use InvalidArgumentException;

/** All business arithmetic uses integer minor units, never floating point. */
final class Money
{
    public static function cents(string|int $amount): int
    {
        if (! preg_match('/^\d{1,10}(\.\d{1,2})?$/D', (string) $amount)) {
            throw new InvalidArgumentException('Invalid monetary amount.');
        }

        [$whole, $fraction] = array_pad(explode('.', (string) $amount), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public static function decimal(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function tax(int $subtotal, int $basisPoints): int
    {
        return intdiv($subtotal, 10000) * $basisPoints + intdiv(($subtotal % 10000) * $basisPoints + 5000, 10000);
    }
}
