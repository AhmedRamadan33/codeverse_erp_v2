<?php

namespace Modules\Products\Livewire\Categories;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Products\Actions\SaveCatalogEntry;
use Modules\Products\Models\ProductCategory;

#[Layout('core::layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        Gate::authorize('products.catalog.manage');
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->form = ['name_ar' => '', 'name_en' => '', 'parent_id' => null, 'is_active' => true];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $category = ProductCategory::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $category->id;
        $this->form = [
            'name_ar' => $category->getTranslation('name', 'ar', false),
            'name_en' => $category->getTranslation('name', 'en', false),
            'parent_id' => $category->parent_id,
            'is_active' => $category->is_active,
        ];
        $this->showForm = true;
    }

    public function save(SaveCatalogEntry $action): void
    {
        $rules = collect(SaveCatalogEntry::categoryRules())->mapWithKeys(fn ($r, $k) => ["form.{$k}" => $r])->all();
        $action->category(auth()->user(), $this->validate($rules)['form'], $this->editingId ? ProductCategory::findOrFail($this->editingId) : null);

        $this->showForm = false;
        session()->flash('status', __('core::ui.saved'));
    }

    public function render()
    {
        return view('products::livewire.categories.index', [
            'categories' => ProductCategory::with('parent')->withCount('products')->orderBy('parent_id')->orderBy('id')->get(),
        ])->title(__('products::catalog.categories'));
    }
}
