<?php
namespace App;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class AdminAccess {
    public function handle(Request $request, Closure $next){
        $hash=DB::table('admins')->value('password');
        if(!$hash || !hash_equals(hash('sha256',$hash),(string)$request->session()->get('admin'))) return redirect('/login');
        return $next($request);
    }
}
