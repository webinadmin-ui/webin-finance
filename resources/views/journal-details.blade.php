@if($editing)
<p>Transaksi {{ $editing->purpose }} @if($editing->order_id) · Order {{ $editing->order_id }} @endif. Perubahan tanggal dan nominal memperbarui laporan terkait.</p>
@else
<input type="hidden" name="submission" value="{{ old('submission',(string)Illuminate\Support\Str::uuid()) }}">
<label>Tujuan transaksi<select name="purpose" id="purpose">@foreach(['general'=>'Transaksi umum','new'=>'Order baru / repeat order + pembayaran','payment'=>'DP / pelunasan order yang sudah ada','renew'=>'Perpanjangan langganan + pembayaran','cost'=>'HPP untuk order','ad_spend'=>'Pemakaian Google Ads','ad_paid'=>'Pemakaian Google Ads langsung dibayar bank','ad_cash'=>'Pembayaran tagihan Google Ads','ad_promo'=>'Kredit promo Google Ads terpakai'] as $key=>$label)
<option value="{{ $key }}" @selected(old('purpose',$parent?'renew':'general')===$key)>{{ $label }}</option>@endforeach</select></label>
<fieldset data-purposes="renew payment cost"><legend>Order terkait</legend><select name="linked_order" aria-label="Order terkait" required><option value="">Pilih order</option>
@foreach($orderChoices as $choice)<option value="{{ $choice->order_id }}" @selected(old('linked_order',$parent->order_id??'')==$choice->order_id)>{{ $choice->domain }} · {{ $choice->package }} · {{ $choice->order_id }}</option>@endforeach
</select></fieldset>
<fieldset data-purposes="payment"><legend>Lengkapi layanan bila diperlukan</legend><div class="fields">
<label>Mulai layanan<input type="date" name="service[start]" value="{{ old('service.start') }}"></label>
<label>Durasi bulan<input name="service[duration]" list="options-duration" value="{{ old('service.duration') }}"></label>
<label>Kedaluwarsa SSL awal<input type="date" name="service[ssl]" value="{{ old('service.ssl') }}"></label>
</div></fieldset>
<fieldset data-purposes="new"><legend>Pelanggan</legend><div class="fields">
@include('customer-choice')
<label>Nama / domain<input name="order[domain]" value="{{ old('order.domain') }}" required></label>
<label>Jenis order<select name="order[kind]"><option>Baru</option><option @selected(old('order.kind')==='Repeat order')>Repeat order</option></select></label>
</div></fieldset>
<fieldset data-purposes="new renew"><legend>Detail order dan layanan</legend><p>Nominal transaksi adalah pembayaran kali ini. Nilai kontrak adalah total harga order.</p><div class="fields">
<label>Tanggal chat pertama<input type="date" name="order[chat]" value="{{ old('order.chat') }}" required></label>
<label>Sumber<input name="order[source]" list="options-source" value="{{ old('order.source',$parent?'Renewal':'Google Ads') }}" required></label>
<label>Paket<input name="order[package]" value="{{ old('order.package',$parent->package??'') }}" required></label>
<label>Nilai kontrak (Rp)<input type="number" min="0" step="any" name="order[value]" value="{{ old('order.value') }}" required></label>
<label>Durasi bulan<input name="order[duration]" list="options-duration" value="{{ old('order.duration',$parent->duration??'') }}" required></label>
<label>Mulai layanan<input type="date" name="order[start]" value="{{ old('order.start',$parent->expires??'') }}"></label>
<label>HPP awal order (Rp, opsional)<input type="number" min="0" step="any" name="order[cost]" value="{{ old('order.cost') }}" placeholder="Kosongkan jika akan dicatat lewat transaksi HPP"></label>
<label>Jam kerja total (opsional)<input type="number" min="0" step="any" name="order[hours]" value="{{ old('order.hours') }}"></label>
<label>Catatan pelanggan<input name="order[notes]" value="{{ old('order.notes') }}"></label>
<label>Kedaluwarsa SSL awal (opsional)<input type="date" name="order[ssl]" value="{{ old('order.ssl') }}"></label>
</div></fieldset>
<script>
(() => {
    const select = document.getElementById('purpose');
    function showFields() {
        document.querySelectorAll('[data-purposes]').forEach(group => {
            const active = group.dataset.purposes.split(' ').includes(select.value);
            group.disabled = !active;
            group.hidden = !active;
        });
    }
    select.addEventListener('change', showFields);
    showFields();
})();
</script>
@endif
