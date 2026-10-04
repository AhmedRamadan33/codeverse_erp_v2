<div class="row justify-content-center">
    <form wire:submit="openShift" class="card col-md-5">
        <div class="card-header">{{ __('pos::shifts.open') }}</div>
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">@foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach</div>
            @endif
            @if ($registers->isEmpty())
                <div class="alert alert-warning mb-0">{{ __('pos::terminal.no_register') }}</div>
            @else
                <div class="mb-3">
                    <label class="form-label">{{ __('pos::shifts.fields.register') }}</label>
                    <select wire:model="opening.register_id" class="form-select">
                        @foreach ($registers as $register) <option value="{{ $register->id }}">{{ $register->code }} — {{ $register->name }}</option> @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">{{ __('pos::shifts.fields.opening_float') }}</label>
                    <input type="text" inputmode="decimal" wire:model="opening.opening_float" class="form-control ltr-value @error('opening.opening_float') is-invalid @enderror">
                </div>
            @endif
        </div>
        @if ($registers->isNotEmpty())
            <div class="card-footer"><button type="submit" class="btn btn-primary">{{ __('pos::shifts.open') }}</button></div>
        @endif
    </form>
</div>
