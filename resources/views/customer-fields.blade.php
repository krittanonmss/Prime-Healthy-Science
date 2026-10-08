<div class="fields section-gap">
    <div><label for="customer-name">Customer name *</label><input id="customer-name" name="name" maxlength="200" value="{{ old('name',$customer->name ?? '') }}" required></div>
    <div><label for="customer-phone">Phone *</label><input id="customer-phone" name="phone" type="tel" maxlength="30" value="{{ old('phone',$customer->phone ?? '') }}" required></div>
    <div><label for="customer-address">Delivery address *</label><textarea id="customer-address" name="address" maxlength="1000" rows="4" required>{{ old('address',$customer->address ?? '') }}</textarea></div>
    <div><label for="customer-tax-id">Tax ID (optional, 13 digits)</label><input id="customer-tax-id" name="tax_id" inputmode="numeric" pattern="[0-9]{13}" maxlength="13" value="{{ old('tax_id',$customer->tax_id ?? '') }}"></div>
    <div><label for="customer-pricing">Default pricing *</label><select id="customer-pricing" name="channel" required>
        @foreach(['retail'=>'Retail','hospital'=>'Hospital','pharmacy'=>'Pharmacy'] as $value=>$label)<option value="{{ $value }}" @selected(old('channel',$customer->channel ?? 'retail')===$value)>{{ $label }}</option>@endforeach
    </select></div>
</div>
