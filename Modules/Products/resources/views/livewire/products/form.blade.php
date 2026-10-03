<form wire:submit="save" class="card">
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger">@foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach</div>
        @endif
        <div class="row g-3">
            <div class="col-md-2">
                <label class="form-label">{{ __('products::products.fields.sku') }}</label>
                <input type="text" wire:model="form.sku" class="form-control ltr-value @error('sku') is-invalid @enderror">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('core::ui.name_ar') }}</label>
                <input type="text" wire:model="form.name_ar" class="form-control @error('name_ar') is-invalid @enderror">
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('core::ui.name_en') }}</label>
                <input type="text" wire:model="form.name_en" class="form-control ltr-value">
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('products::products.fields.category') }}</label>
                <select wire:model="form.category_id" class="form-select">
                    <option value="">—</option>
                    @foreach ($categories as $category) <option value="{{ $category->id }}">{{ $category->name }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('products::products.fields.type') }}</label>
                <select wire:model.live="form.type" class="form-select">
                    @foreach ($types as $type) <option value="{{ $type->value }}">{{ $type->label() }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('products::products.fields.tracking') }}</label>
                <select wire:model="form.tracking" class="form-select" @disabled($form['type'] !== 'stockable')>
                    @foreach ($trackings as $tracking) <option value="{{ $tracking->value }}">{{ $tracking->label() }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('products::products.fields.base_unit') }}</label>
                <select wire:model="form.base_unit_id" class="form-select @error('base_unit_id') is-invalid @enderror">
                    @foreach ($units as $unit) <option value="{{ $unit->id }}">{{ $unit->name }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('products::products.fields.barcodes') }}</label>
                <input type="text" wire:model="form.base_barcodes" class="form-control ltr-value">
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('products::products.fields.sale_price') }}</label>
                <input type="text" inputmode="decimal" wire:model="form.sale_price" class="form-control ltr-value @error('sale_price') is-invalid @enderror">
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('products::products.fields.purchase_price') }}</label>
                <input type="text" inputmode="decimal" wire:model="form.purchase_price" class="form-control ltr-value @error('purchase_price') is-invalid @enderror">
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('products::products.fields.sale_tax') }}</label>
                <select wire:model="form.sale_tax_id" class="form-select">
                    <option value="">{{ __('products::products.no_tax') }}</option>
                    @foreach ($saleTaxes as $tax) <option value="{{ $tax->id }}">{{ $tax->name }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('products::products.fields.purchase_tax') }}</label>
                <select wire:model="form.purchase_tax_id" class="form-select">
                    <option value="">{{ __('products::products.no_tax') }}</option>
                    @foreach ($purchaseTaxes as $tax) <option value="{{ $tax->id }}">{{ $tax->name }}</option> @endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">{{ __('products::products.fields.description') }}</label>
                <textarea wire:model="form.description" class="form-control" rows="2"></textarea>
            </div>
        </div>

        <h6 class="mt-4">{{ __('products::products.fields.units') }}</h6>
        <table class="table table-sm table-bordered align-middle">
            <thead class="table-light">
            <tr>
                <th>{{ __('products::products.fields.unit') }}</th>
                <th>{{ __('products::products.fields.factor') }}</th>
                <th>{{ __('products::products.fields.sale_price') }}</th>
                <th>{{ __('products::products.fields.barcodes') }}</th>
                <th style="width: 3rem"></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($form['units'] as $i => $row)
                <tr wire:key="unit-row-{{ $i }}">
                    <td>
                        <select wire:model="form.units.{{ $i }}.unit_id" class="form-select form-select-sm @error('units.'.$i.'.unit_id') is-invalid @enderror">
                            <option value="">—</option>
                            @foreach ($units as $unit) <option value="{{ $unit->id }}">{{ $unit->name }}</option> @endforeach
                        </select>
                    </td>
                    <td><input type="text" inputmode="decimal" wire:model="form.units.{{ $i }}.factor" class="form-control form-control-sm ltr-value @error('units.'.$i.'.factor') is-invalid @enderror"></td>
                    <td><input type="text" inputmode="decimal" wire:model="form.units.{{ $i }}.sale_price" class="form-control form-control-sm ltr-value"></td>
                    <td><input type="text" wire:model="form.units.{{ $i }}.barcodes" class="form-control form-control-sm ltr-value"></td>
                    <td class="text-center"><button type="button" class="btn btn-sm btn-link text-danger" wire:click="removeUnit({{ $i }})"><i class="bi bi-x-lg"></i></button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addUnit"><i class="bi bi-plus"></i> {{ __('products::products.add_unit') }}</button>

        <div class="form-check form-switch mt-3">
            <input id="product_active" type="checkbox" wire:model="form.is_active" class="form-check-input">
            <label for="product_active" class="form-check-label">{{ __('core::ui.active') }}</label>
        </div>
    </div>
    <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary">{{ __('core::ui.save') }}</button>
        <a href="{{ route('products.products.index') }}" class="btn btn-outline-secondary">{{ __('core::ui.cancel') }}</a>
    </div>
</form>
