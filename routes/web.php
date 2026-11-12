<?php
use App\{CustomerChoice,OrderNumbers,JournalEntry,Records,Reports,SubscriptionAlerts,Workbook};
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash,Route};

Route::view('/login','login')->name('login');
Route::post('/login',function(Request $r){
    $hash=DB::table('admins')->value('password');
    if(!$hash || !Hash::check((string)$r->input('password'),$hash)) return back()->withErrors(['password'=>'Password tidak cocok.']);
    $r->session()->regenerate(); $r->session()->put('admin',hash('sha256',$hash));
    return redirect('/');
})->middleware('throttle:6,1');

Route::middleware(\App\AdminAccess::class)->group(function(){
    Route::post('/logout',function(Request $r){$r->session()->invalidate(); $r->session()->regenerateToken(); return redirect('/login');});
    Route::get('/',function(Request $r,Reports $reports){
        $r->validate(['from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d|after_or_equal:from']);
        $from=$r->input('from') ?: now()->startOfMonth()->toDateString(); $to=$r->input('to') ?: now()->endOfMonth()->toDateString();
        return view('dashboard',['from'=>$from,'to'=>$to,'alerts'=>SubscriptionAlerts::counts(CarbonImmutable::today()),'acquisition'=>$reports->acquisition($from,$to)]+$reports->finance($from,$to));
    });
    Route::view('/import','import');
    Route::post('/import',function(Request $r,Workbook $reader){
        $r->validate(['file'=>'required|file|extensions:xlsx|max:16384']);
        try{$counts=$reader->import($r->file('file')->getRealPath());}
        catch(\DomainException $e){return back()->withErrors(['file'=>$e->getMessage()]);}
        catch(\PhpOffice\PhpSpreadsheet\Reader\Exception $e){return back()->withErrors(['file'=>'File Excel tidak dapat dibaca.']);}
        return back()->with('message',"Disimpan: {$counts['saved']}. Sudah ada: {$counts['skipped']}.");
    });
    Route::get('/subscriptions',function(Request $r){
        $today=now('Asia/Jakarta')->toDateString();
        $q=SubscriptionAlerts::current();
        match($r->input('filter')){
            'missing'=>$q->whereNull('expires'),'overdue'=>$q->where('expires','<',$today),
            'soon'=>$q->whereBetween('expires',[$today,CarbonImmutable::parse($today)->addDays(14)->toDateString()]),default=>null};
        return view('subscriptions',['rows'=>$q->orderBy('expires')->paginate(50)->withQueryString(),'today'=>$today,'alerts'=>SubscriptionAlerts::counts(CarbonImmutable::today())]);
    });
    Route::get('/ssl',function(Request $r){
        $r->validate(['month'=>'nullable|date_format:Y-m','filter'=>'nullable|in:due,missing,overdue,all']);
        $month=$r->input('month') ?: now()->format('Y-m');
        [$start,$end]=SubscriptionAlerts::window(CarbonImmutable::parse($month.'-01'));
        $filter=$r->input('filter') ?: 'due'; $sites=SubscriptionAlerts::websites();
        $counts=['due'=>(clone $sites)->whereBetween('ssl.expiry',[$start,$end])->count(),
            'missing'=>(clone $sites)->whereNull('ssl.id')->count(),'overdue'=>(clone $sites)->where('ssl.expiry','<',$start)->count()];
        match($filter){
            'due'=>$sites->whereBetween('ssl.expiry',[$start,$end]),'missing'=>$sites->whereNull('ssl.id'),
            'overdue'=>$sites->where('ssl.expiry','<',$start),default=>null};
        return view('ssl',compact('month','start','end','filter','counts')+['rows'=>$sites->orderBy('ssl.expiry')->orderBy('sites.domain')->paginate(50)->withQueryString()]);
    });
    Route::post('/ssl',function(Request $r){
        $v=$r->validate(['domain'=>'required|string','expiry'=>'required|date_format:Y-m-d','version'=>'required|integer|min:0']);
        DB::transaction(function() use($v){
            abort_unless(SubscriptionAlerts::websites()->where('sites.domain',$v['domain'])->exists(),404);
            SubscriptionAlerts::save($v['domain'],$v['expiry'],(int)$v['version']);
        },3);
        return back()->with('message','Tanggal kedaluwarsa SSL disimpan.');
    });
    Route::get('/records/{table}',function(Request $r,string $table){
        $layout=Records::layouts()[$table]; $key=match($table){'journal'=>'id','orders'=>'order_id','ads'=>'month'};
        $q=DB::table($table);
        if($term=$r->input('q')) $q->where(function($q)use($layout,$term){
            foreach(array_diff($layout['fields'],['_']) as $field) $q->orWhere($field,'like','%'.$term.'%');
        });
        $editing=$r->filled('edit')?$q->newQuery()->from($table)->where($key,$r->input('edit'))->first():null;
        $parent=in_array($table,['orders','journal'],true) && $r->filled('renew')?DB::table('orders')->where('order_id',$r->input('renew'))->first():null;
        return view('records',compact('layout','table','key','editing','parent')+['rows'=>$q->orderByDesc(match($table){'orders'=>'chat','journal'=>'date','ads'=>'month'})->paginate(30)->withQueryString(),
            'customers'=>in_array($table,['journal','orders'],true) ? DB::table('orders')->distinct()->orderBy('customer')->pluck('customer') : collect(),
            'orderChoices'=>$table==='journal' ? DB::table('orders')->select('order_id','domain','package','customer')->orderBy('domain')->get() : [],
            'accounts'=>DB::table('accounts')->orderBy('code')->get()]);
    })->whereIn('table',['journal','orders','ads']);
    Route::post('/records/{table}',function(Request $r,string $table){
        $key=match($table){'journal'=>'id','orders'=>'order_id','ads'=>'month'};
        $input=$r->all();
        if($table==='orders' && !$r->filled('edit')){
            $id=DB::transaction(function() use($r,$input){
                $input['order_id']=OrderNumbers::next();
                if($r->has('customer_choice')) $input['customer']=CustomerChoice::resolve($r);
                $data=Records::validate('orders',$input);
                if($r->filled('renews')){
                    $p=SubscriptionAlerts::current()->where('o.order_id',$r->input('renews'))->first();
                    if(!$p || $p->customer!==$data['customer'] || $data['kind']!=='Renewal' || $data['status']!=='Bayar' || empty($data['expires']))
                        throw \Illuminate\Validation\ValidationException::withMessages(['renews'=>'Periksa pelanggan, tanggal mulai, durasi dan status perpanjangan.']);
                    $data['renews']=$p->order_id;
                }
                DB::table('orders')->insert($data);
                return $data['order_id'];
            },3);
            return redirect('/records/orders')->with('message',"Order #$id tersimpan.");
        }
        if($table==='orders'){
            $input['order_id']=(string)$r->input('edit');
            if($r->has('customer_choice')) $input['customer']=CustomerChoice::resolve($r);
        }
        $data=Records::validate($table,$input);
        if($table==='journal'){
            JournalEntry::save($r,$data);
            return redirect('/records/journal')->with('message','Transaksi dan data terkait tersimpan.');
        }
        if($r->filled('edit')){
            $id=$r->input('edit'); abort_unless(DB::table($table)->where($key,$id)->exists(),404);
            unset($data[$key]); DB::table($table)->where($key,$id)->update($data);
        }else{
            try{DB::table($table)->insert($data);}catch(\Illuminate\Database\UniqueConstraintViolationException $e){
                return back()->withInput()->withErrors('ID, bulan, atau perpanjangan sudah tercatat.');
            }
        }
        return redirect('/records/'.$table)->with('message','Data tersimpan.');
    })->whereIn('table',['journal','orders','ads']);
});
Route::get('/ledger/{code}',function(Request $r,string $code,Reports $reports){
    $period=$r->validate(['from'=>'required|date_format:Y-m-d','to'=>'required|date_format:Y-m-d|after_or_equal:from']);
    $account=DB::table('accounts')->where('code',$code)->first(); abort_unless($account,404);
    $trial=$reports->finance($period['from'],$period['to'])['trial'];
    $rows=DB::table('journal')->whereBetween('date',array_values($period))->where(fn($q)=>$q->where('debit',$code)->orWhere('credit',$code))->orderBy('date')->orderBy('id')->get();
    return view('ledger',$period+compact('rows','account')+['running'=>$trial[$code]['opening']]);
})->middleware(\App\AdminAccess::class);
Route::get('/simulation',function(Request $r){
    $labels=['budget'=>'Budget iklan bruto','cac'=>'CAC','price'=>'Nilai rata-rata order','cost'=>'HPP per order',
        'opex'=>'Operasional lainnya','promo'=>'Kredit promo terpakai','target'=>'Target laba','fund'=>'Dana iklan tersedia'];
    $result=[];
    if($r->has('budget')){
        $rules=array_fill_keys(array_keys($labels),'required|numeric|min:0'); $rules['cac']='required|numeric|gt:0';
        $v=$r->validate($rules); $n=floor($v['budget']/$v['cac']);
        $gross=$n*($v['price']-$v['cost']); $net=max(0,$v['budget']-$v['promo']);
        $margin=$v['price']-$v['cost']-$v['cac']; $needed=$margin>0?ceil(($v['target']+$v['opex'])/$margin):null;
        $result=['Perkiraan pelanggan baru'=>$n,'Omzet order (Rp)'=>$n*$v['price'],'HPP total (Rp)'=>$n*$v['cost'],
            'Laba tanpa bonus (Rp)'=>$gross-$v['budget']-$v['opex'],'Laba dengan bonus (Rp)'=>$gross-$net-$v['opex'],
            'Pelanggan untuk target'=>$needed,'Budget untuk target (Rp)'=>$needed===null?null:$needed*$v['cac'],
            'Kekurangan dana iklan (Rp)'=>max(0,$net-$v['fund'])];
    }
    return view('simulation',compact('labels','result'));
})->middleware(\App\AdminAccess::class);
