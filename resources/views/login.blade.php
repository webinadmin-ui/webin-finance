@extends('base')
@section('title','Masuk ke ruang kerja')
@section('content')
<form class="panel narrow" method="post">@csrf<label>Password admin<input name="password" type="password" autocomplete="current-password" required autofocus></label><button>Masuk</button></form>
@endsection
