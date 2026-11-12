@extends('base')
@section('title','Simulasi laba bulanan')
@section('content')
<form class="panel"><div class="fields">@foreach($labels as $key=>$label)<label>{{ $label }} (Rp)<input type="number" name="{{ $key }}" value="{{ request($key) }}" min="{{ $key==='cac'?1:0 }}" step="any" required></label>@endforeach</div><button>Hitung simulasi</button></form>
@if($result)<section class="panel"><h2>Hasil skenario</h2>@foreach($result as $label=>$v)<div class="line">{{ $label }}<b>{{ $v===null?'Target tidak tercapai dengan margin ini':number_format($v,0,',','.') }}</b></div>@endforeach</section>@endif
@endsection
