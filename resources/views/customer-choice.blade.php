@php($choice = old('customer_choice',isset($currentCustomer) ? 'id:'.$currentCustomer : ($customers->isEmpty() ? 'new' : '')))
<label>ID pelanggan
<select name="customer_choice" id="customer-choice" required>
<option value="">Pilih pelanggan</option>
<option value="new" @selected($choice==='new')>+ Pelanggan baru</option>
@foreach($customers as $id)<option value="id:{{ $id }}" @selected($choice==='id:'.$id)>{{ $id }}</option>@endforeach
</select></label>
<label id="new-customer-field">ID pelanggan baru
<input name="new_customer" maxlength="255" value="{{ old('new_customer') }}"></label>
<script>
(() => {
    const select = document.getElementById('customer-choice');
    const label = document.getElementById('new-customer-field');
    const input = label.querySelector('input');
    function showNewCustomer() {
        const isNew = select.value === 'new';
        label.hidden = !isNew;
        input.disabled = !isNew;
        input.required = isNew;
    }
    select.addEventListener('change', showNewCustomer);
    showNewCustomer();
})();
</script>
