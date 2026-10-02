<?php

namespace Modules\Core\Livewire\Modules;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Models\InstalledModule;
use Modules\Core\Modules\ModuleException;
use Modules\Core\Modules\ModuleManager;
use Nwidart\Modules\Contracts\RepositoryInterface;
use Nwidart\Modules\Module;

#[Layout('core::layouts.app')]
class Index extends Component
{
    public function mount(): void
    {
        Gate::authorize('core.modules.manage');
    }

    public function enable(string $name, ModuleManager $manager): void
    {
        $this->change(fn () => $manager->enable($name), __('core::modules.enabled', ['module' => $name]));
    }

    public function disable(string $name, ModuleManager $manager): void
    {
        $this->change(fn () => $manager->disable($name), __('core::modules.disabled', ['module' => $name]));
    }

    private function change(callable $operation, string $message): void
    {
        Gate::authorize('core.modules.manage');

        try {
            $operation();
        } catch (ModuleException $e) {
            $this->addError('module', $e->getMessage());

            return;
        }

        session()->flash('status', $message);
        // Full reload: the enabled set changes routes, menu items and translations.
        $this->redirectRoute('core.modules.index');
    }

    public function render(RepositoryInterface $modules, ModuleManager $manager)
    {
        $installed = InstalledModule::all()->keyBy('name');
        $alwaysEnabled = config('modules.activators.database.always-enabled', []);

        $rows = collect($modules->all())
            ->sortBy(fn (Module $m) => [! in_array($m->getName(), $alwaysEnabled, true), $m->getName()])
            ->map(fn (Module $m) => [
                'name' => $m->getName(),
                'label' => $this->translated($m->getLowerName(), 'name', $m->getName()),
                'description' => $this->translated($m->getLowerName(), 'description', (string) $m->getDescription()),
                'version' => (string) $m->get('version', '0.0.0'),
                'installed_version' => $installed->get($m->getName())?->version,
                'requires' => $manager->requires($m),
                'enabled' => $manager->isEnabled($m->getName()),
                'locked' => in_array($m->getName(), $alwaysEnabled, true),
            ]);

        return view('core::livewire.modules.index', ['modules' => $rows])->title(__('core::menu.modules'));
    }

    private function translated(string $module, string $key, string $fallback): string
    {
        $translation = "{$module}::module.{$key}";

        return __($translation) === $translation ? $fallback : __($translation);
    }
}
