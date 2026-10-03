<div class="card">
    <div class="card-header d-flex">
        <a href="{{ route('sales.price-lists.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> {{ __('sales::price_lists.new') }}</a>
    </div>
    <div class="card-body p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
            <tr>
                <th>{{ __('sales::price_lists.fields.name') }}</th>
                <th class="text-end">{{ __('sales::price_lists.fields.items') }}</th>
                <th class="text-end">{{ __('sales::price_lists.fields.customers') }}</th>
                <th>{{ __('core::ui.status') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($lists as $list)
                <tr wire:key="plist-{{ $list->id }}">
                    <td><a href="{{ route('sales.price-lists.edit', $list->id) }}">{{ $list->name }}</a></td>
                    <td class="text-end ltr-value">{{ $list->items_count }}</td>
                    <td class="text-end ltr-value">{{ $customers[$list->id] ?? 0 }}</td>
                    <td><span @class(['badge', 'text-bg-success' => $list->is_active, 'text-bg-secondary' => ! $list->is_active])>{{ $list->is_active ? __('core::ui.active') : __('core::ui.inactive') }}</span></td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-body-secondary py-4">{{ __('core::ui.no_records') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
