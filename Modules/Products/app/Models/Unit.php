<?php

namespace Modules\Products\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $name
 * @property string $symbol
 * @property bool $is_active
 */
class Unit extends Model
{
    use HasTranslations;

    /** @var string[] */
    public array $translatable = ['name', 'symbol'];

    protected $fillable = ['name', 'symbol', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
