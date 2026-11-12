<?php
namespace App;

use Illuminate\Support\Facades\DB;

class OrderNumbers
{
    // Called inside the same transaction as the order insert.
    public static function next(): string
    {
        $sequence = DB::table('order_numbers')->where('id', 1);
        $sequence->increment('last_number');
        return (string) $sequence->value('last_number');
    }

    public static function observe(string $id): void
    {
        $digits = ltrim($id, '0');
        if (!ctype_digit($id) || strlen($digits) > 18) return;
        $number = (int) $digits;
        DB::table('order_numbers')->where('id', 1)->where('last_number', '<', $number)
            ->update(['last_number' => $number]);
    }
}
