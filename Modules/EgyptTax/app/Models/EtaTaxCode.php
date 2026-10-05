<?php

namespace Modules\EgyptTax\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tax_id
 * @property string $tax_type ETA tax type, e.g. T1
 * @property string $sub_type ETA subtype, e.g. V009
 */
class EtaTaxCode extends Model
{
    protected $table = 'eta_tax_codes';

    protected $fillable = ['tax_id', 'tax_type', 'sub_type'];
}
