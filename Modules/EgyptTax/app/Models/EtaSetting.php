<?php

namespace Modules\EgyptTax\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Audit\Auditable;

/**
 * The taxpayer registered with ETA (one row per installation).
 *
 * @property int $id
 * @property string $environment
 * @property string|null $rin
 * @property string|null $company_trade_name
 * @property string|null $activity_code
 * @property string|null $client_id
 * @property string|null $client_secret
 * @property string $exempt_tax_type
 * @property string $exempt_sub_type
 * @property bool $is_active
 */
class EtaSetting extends Model
{
    use Auditable;

    public const ENVIRONMENTS = ['preprod', 'production'];

    protected $table = 'eta_settings';

    protected $fillable = [
        'environment', 'rin', 'company_trade_name', 'activity_code', 'client_id', 'client_secret',
        'exempt_tax_type', 'exempt_sub_type', 'is_active',
    ];

    protected $hidden = ['client_secret'];

    /** @var string[] */
    protected array $auditExclude = ['client_secret'];

    protected function casts(): array
    {
        return [
            'client_secret' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return self::query()->orderBy('id')->first() ?? new self(['environment' => 'preprod', 'exempt_tax_type' => 'T1', 'exempt_sub_type' => 'V003', 'is_active' => false]);
    }

    /**
     * @return array{identity: string, api: string, portal: string}
     */
    public function endpoints(): array
    {
        return config("egypttax.environments.{$this->environment}");
    }

    /**
     * Active and complete enough to issue receipts.
     */
    public function isUsable(): bool
    {
        return $this->is_active && filled($this->rin) && filled($this->company_trade_name) && filled($this->activity_code);
    }
}
