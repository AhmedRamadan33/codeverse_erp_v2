<?php

namespace Modules\Core\Menu;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/**
 * Sidebar registry. Each enabled module adds its items from its service provider's boot().
 * Hiding an item is a convenience only; every route still authorizes on its own.
 */
class Menu
{
    /** @var array<string, array{label: string, icon: string, order: int}> */
    private array $groups = [];

    /** @var MenuItem[] */
    private array $items = [];

    public function group(string $key, string $label, string $icon, int $order = 100): void
    {
        $this->groups[$key] = ['label' => $label, 'icon' => $icon, 'order' => $order];
    }

    public function add(MenuItem $item): void
    {
        $this->items[] = $item;
    }

    /**
     * Groups visible to the user, each with its visible items, both sorted by order.
     *
     * @return array<int, array{key: string, label: string, icon: string, items: MenuItem[]}>
     */
    public function for(Authenticatable $user): array
    {
        $visible = array_filter(
            $this->items,
            fn (MenuItem $item) => $item->permission === null || Gate::forUser($user)->allows($item->permission),
        );
        usort($visible, fn (MenuItem $a, MenuItem $b) => $a->order <=> $b->order);

        $groups = [];
        foreach ($this->groups as $key => $group) {
            $items = array_values(array_filter($visible, fn (MenuItem $item) => $item->group === $key));

            if ($items !== []) {
                $groups[] = ['key' => $key, 'label' => $group['label'], 'icon' => $group['icon'], 'items' => $items, 'order' => $group['order']];
            }
        }
        usort($groups, fn ($a, $b) => $a['order'] <=> $b['order']);

        return $groups;
    }
}
