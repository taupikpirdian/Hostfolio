<?php

namespace App\Actions\Billing;

use Illuminate\Validation\ValidationException;

class Money
{
    public static function amount(string|int $value): string
    {
        if (! preg_match('/^\d{1,16}(\.\d{1,2})?$/', (string) $value)) {
            throw ValidationException::withMessages(['amount' => 'Use a nonnegative monetary amount with at most two decimal places.']);
        }

        return bcadd((string) $value, '0', 2);
    }

    public static function line(string $quantity, string $price, string $discount = '0', string $tax = '0'): array
    {
        if (! preg_match('/^\d{1,8}(\.\d{1,4})?$/', $quantity) || bccomp($quantity, '0', 4) <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be greater than zero.']);
        }
        $subtotal = bcadd(bcmul($quantity, self::amount($price), 6), '0.005', 2);
        $discount = self::amount($discount);
        $tax = self::amount($tax);
        if (bccomp($discount, $subtotal, 2) > 0) {
            throw ValidationException::withMessages(['discount' => 'Discount exceeds line subtotal.']);
        }
        $total = bcadd(bcsub($subtotal, $discount, 2), $tax, 2);
        self::amount($total);

        return ['subtotal' => $subtotal, 'discount' => $discount, 'tax' => $tax, 'total' => $total];
    }
}
