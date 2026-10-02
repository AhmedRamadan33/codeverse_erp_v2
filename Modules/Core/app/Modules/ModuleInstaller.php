<?php

namespace Modules\Core\Modules;

/**
 * Optional per-module hooks, declared in module.json as "installer".
 * Both hooks run inside a DB transaction, after the module's migrations.
 */
interface ModuleInstaller
{
    /**
     * Seed the data the module needs to run: settings defaults, sequences, mappings.
     */
    public function install(): void;

    /**
     * Run data changes needed when moving from one released version to the next.
     */
    public function upgrade(string $fromVersion, string $toVersion): void;
}
