<?php
namespace App;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerChoice
{
    public static function resolve(Request $request): string
    {
        $choice = $request->validate(['customer_choice'=>'required|string'])['customer_choice'];
        if ($choice === 'new') {
            $id = $request->validate(['new_customer'=>'required|string|max:255'])['new_customer'];
            if (DB::table('orders')->where('customer', $id)->exists()) {
                throw ValidationException::withMessages(['new_customer'=>'ID ini sudah ada. Pilih pelanggan tersebut dari daftar.']);
            }
            return $id;
        }
        $id = substr($choice, 3);
        if (!str_starts_with($choice, 'id:') || !DB::table('orders')->where('customer', $id)->exists()) {
            throw ValidationException::withMessages(['customer_choice'=>'Pilih pelanggan yang tersedia.']);
        }
        return $id;
    }
}
