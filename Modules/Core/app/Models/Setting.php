<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Written only through Modules\Core\Settings\Settings.
 */
class Setting extends Model
{
    protected $fillable = ['module', 'key', 'value', 'branch_id'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
