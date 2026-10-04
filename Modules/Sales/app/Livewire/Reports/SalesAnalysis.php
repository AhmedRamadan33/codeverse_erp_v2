<?php

namespace Modules\Sales\Livewire\Reports;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Partner;
use Modules\Products\Models\Product;
use Modules\Products\Models\ProductCategory;
use Modules\Sales\Reports\SalesLineSources;

/**
 * Net sales (after returns) of every channel, grouped by product, customer, category, day or
 * channel; cost and margin for users who may see costs.
 */
#[Layout('core::layouts.app')]
class SalesAnalysis extends Component
{
    public const GROUPS = ['product', 'customer', 'category', 'day', 'channel'];

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public ?int $branchId = null;

    #[Url]
    public string $groupBy = 'product';

    public function mount(): void
    {
        Gate::authorize('sales.reports.view');

        $this->from = $this->from ?: now()->startOfMonth()->toDateString();
        $this->to = $this->to ?: now()->toDateString();
        $this->groupBy = in_array($this->groupBy, self::GROUPS, true) ? $this->groupBy : 'product';
    }

    /**
     * @return Collection<int, object{key: mixed, quantity: string, net: string, cost: string|null}>
     */
    private function rows(SalesLineSources $sources, ?int $branchId): Collection
    {
        $union = $sources->union(CarbonImmutable::parse($this->from), CarbonImmutable::parse($this->to), $branchId);
        if ($union === null) {
            return collect();
        }

        $key = match ($this->groupBy) {
            'customer' => 's.partner_id',
            'category' => 'p.category_id',
            'day' => 's.date',
            'channel' => 's.channel',
            default => 's.product_id',
        };

        return DB::query()->fromSub($union, 's')
            ->when($this->groupBy === 'category', fn ($q) => $q->join('products as p', 'p.id', '=', 's.product_id'))
            ->groupBy(DB::raw($key))
            ->selectRaw("{$key} as `key`, sum(s.quantity) as quantity, sum(s.net) as net, sum(s.cost) as cost")
            ->orderBy($this->groupBy === 'day' ? 'key' : 'net', $this->groupBy === 'day' ? 'asc' : 'desc')
            ->get();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string|int, string>
     */
    private function labels(Collection $rows): array
    {
        $keys = $rows->pluck('key')->filter()->all();

        return match ($this->groupBy) {
            'product' => Product::whereKey($keys)->get()->mapWithKeys(fn (Product $p) => [$p->id => $p->label()])->all(),
            'customer' => Partner::whereKey($keys)->pluck('name', 'id')->all(),
            'category' => ProductCategory::whereKey($keys)->get()->mapWithKeys(fn ($c) => [$c->id => $c->name])->all(),
            'channel' => collect($keys)->mapWithKeys(fn ($k) => [$k => __("sales::reports.channels.{$k}")])->all(),
            default => array_combine($keys, $keys) ?: [],
        };
    }

    public function render(SalesLineSources $sources)
    {
        $this->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);
        $user = auth()->user();
        abort_if($this->branchId && ! $user->canAccessBranch($this->branchId), 403);
        $branchId = $this->branchId ?? ($user->can('core.branches.all_access') ? null : $user->branches()->value('branches.id'));

        $rows = $this->rows($sources, $branchId);
        $sum = fn (string $column) => $rows->reduce(fn (BigDecimal $c, $r) => $c->plus(BigDecimal::of($r->{$column} ?? 0)), BigDecimal::zero());

        return view('sales::livewire.reports.sales-analysis', [
            'rows' => $rows,
            'labels' => $this->labels($rows),
            'totals' => ['net' => $sum('net'), 'cost' => $sum('cost')],
            'showCost' => $user->can('sales.invoices.view_cost'),
            'groups' => self::GROUPS,
            'branches' => $user->can('core.branches.all_access') ? Branch::orderBy('code')->get() : $user->branches,
        ])->title(__('sales::reports.title'));
    }
}
