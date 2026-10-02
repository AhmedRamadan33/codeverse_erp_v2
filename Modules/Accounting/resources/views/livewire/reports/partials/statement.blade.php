{{-- Shared by the general ledger and the partner statement. Expects $statement and $showPartner. --}}
<table class="table table-sm table-bordered mb-0">
    <thead class="table-light">
    <tr>
        <th>{{ __('accounting::entries.fields.date') }}</th>
        <th>{{ __('accounting::entries.fields.number') }}</th>
        <th>{{ __('accounting::entries.fields.description') }}</th>
        @if ($showPartner) <th>{{ __('accounting::entries.fields.partner') }}</th> @else <th>{{ __('accounting::accounts.fields.code') }}</th> @endif
        <th class="text-end">{{ __('accounting::entries.fields.debit') }}</th>
        <th class="text-end">{{ __('accounting::entries.fields.credit') }}</th>
        <th class="text-end">{{ __('accounting::accounts.fields.balance') }}</th>
    </tr>
    </thead>
    <tbody>
    <tr class="table-secondary">
        <td colspan="6">{{ __('accounting::reports.opening_balance') }}</td>
        <td class="text-end ltr-value">@money($statement['opening'])</td>
    </tr>
    @foreach ($statement['rows'] as $row)
        <tr>
            <td class="ltr-value">{{ $row->date }}</td>
            <td class="ltr-value"><a href="{{ route('accounting.entries.show', $row->entryId) }}">{{ $row->number }}</a></td>
            <td>{{ $row->description }}</td>
            <td>{{ $showPartner ? $row->partnerName : $row->accountCode }}</td>
            <td class="text-end ltr-value">{{ $row->debit->isZero() ? '' : \Modules\Core\Support\Money::format($row->debit) }}</td>
            <td class="text-end ltr-value">{{ $row->credit->isZero() ? '' : \Modules\Core\Support\Money::format($row->credit) }}</td>
            <td class="text-end ltr-value">@money($row->balance)</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot class="table-light fw-semibold">
    <tr>
        <td colspan="4">{{ __('accounting::reports.closing_balance') }}</td>
        <td class="text-end ltr-value">@money($statement['debit'])</td>
        <td class="text-end ltr-value">@money($statement['credit'])</td>
        <td class="text-end ltr-value">@money($statement['closing'])</td>
    </tr>
    </tfoot>
</table>
