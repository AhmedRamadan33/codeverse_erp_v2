<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property string $version
 * @property bool $enabled
 */
class InstalledModule extends Model
{
    protected $primaryKey = 'name';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['name', 'version', 'enabled', 'installed_at', 'upgraded_at'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'installed_at' => 'immutable_datetime',
            'upgraded_at' => 'immutable_datetime',
        ];
    }
}
