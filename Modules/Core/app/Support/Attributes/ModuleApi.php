<?php

namespace Modules\Core\Support\Attributes;

use Attribute;

/**
 * Marks a class as part of its module's public API: other modules may call it.
 * Anything not marked is internal to its module (docs/architecture/core-design.md §3.1).
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ModuleApi {}
