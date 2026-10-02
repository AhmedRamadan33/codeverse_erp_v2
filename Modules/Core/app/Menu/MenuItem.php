<?php

namespace Modules\Core\Menu;

final readonly class MenuItem
{
    /**
     * @param  string  $label  translation key
     * @param  string|null  $permission  shown only to users who have it; null = every signed-in user
     */
    public function __construct(
        public string $group,
        public string $label,
        public string $route,
        public string $icon,
        public ?string $permission = null,
        public int $order = 100,
    ) {}
}
