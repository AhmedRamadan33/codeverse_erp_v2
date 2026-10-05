<?php

namespace Modules\EgyptTax\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Audit\Auditable;
use Modules\Pos\Models\Register;

/**
 * A POS register registered with ETA. Client credentials left empty fall back to the company's.
 *
 * @property int $id
 * @property int $register_id
 * @property string $serial
 * @property string $os_version
 * @property string|null $model_framework
 * @property string|null $pre_shared_key
 * @property string|null $client_id
 * @property string|null $client_secret
 * @property string $branch_code
 * @property array<string, string|null> $address
 * @property bool $is_active
 */
class EtaDevice extends Model
{
    use Auditable;

    // ETA branch address fields, in document order.
    public const ADDRESS_FIELDS = ['country', 'governate', 'regionCity', 'street', 'buildingNumber', 'postalCode', 'floor', 'room', 'landmark', 'additionalInformation'];

    protected $table = 'eta_devices';

    protected $fillable = [
        'register_id', 'serial', 'os_version', 'model_framework', 'pre_shared_key', 'client_id', 'client_secret',
        'branch_code', 'address', 'is_active',
    ];

    protected $hidden = ['pre_shared_key', 'client_secret'];

    /** @var string[] */
    protected array $auditExclude = ['pre_shared_key', 'client_secret'];

    protected function casts(): array
    {
        return [
            'pre_shared_key' => 'encrypted',
            'client_secret' => 'encrypted',
            'address' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(EtaReceipt::class, 'device_id');
    }

    /**
     * @return array{0: string|null, 1: string|null} client id and secret
     */
    public function credentials(EtaSetting $settings): array
    {
        return filled($this->client_id)
            ? [$this->client_id, $this->client_secret]
            : [$settings->client_id, $settings->client_secret];
    }
}
