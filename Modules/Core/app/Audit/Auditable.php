<?php

namespace Modules\Core\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Modules\Core\Models\AuditLog;

/**
 * Records created/updated/deleted events with the changed attributes.
 * Models may list attributes to leave out in $auditExclude.
 *
 * @mixin Model
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => $model->writeAudit('created', [], $model->auditableAttributes($model->getAttributes())));

        static::updated(function (Model $model) {
            $changed = $model->auditableAttributes($model->getChanges());
            unset($changed['updated_at']);

            if ($changed !== []) {
                $old = array_intersect_key($model->getOriginal(), $changed);
                $model->writeAudit('updated', $model->auditableAttributes($old), $changed);
            }
        });

        static::deleted(fn (Model $model) => $model->writeAudit('deleted', $model->auditableAttributes($model->getAttributes()), []));
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('id');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function auditableAttributes(array $attributes): array
    {
        $exclude = array_merge(['password', 'remember_token'], $this->auditExclude ?? []);

        return array_map(
            fn ($value) => $value instanceof \BackedEnum ? $value->value : (is_object($value) ? (string) $value : $value),
            array_diff_key($attributes, array_flip($exclude)),
        );
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    protected function writeAudit(string $event, array $old, array $new): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => $this->getMorphClass(),
            'auditable_id' => $this->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip' => app()->runningInConsole() ? null : Request::ip(),
        ]);
    }
}
