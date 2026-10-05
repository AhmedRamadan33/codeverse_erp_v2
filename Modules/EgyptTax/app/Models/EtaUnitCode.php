<?php

namespace Modules\EgyptTax\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $unit_id
 * @property string $unit_type ETA unit type, e.g. EA, KGM
 */
class EtaUnitCode extends Model
{
    protected $table = 'eta_unit_codes';

    protected $fillable = ['unit_id', 'unit_type'];
}
