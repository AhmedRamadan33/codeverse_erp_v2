<?php

namespace Modules\Core\Settings\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Core\Http\Middleware\SetLocale;
use Modules\Core\Settings\Settings;

/**
 * Company profile and installation defaults. The base currency is not editable here:
 * it is fixed at installation and may not change once anything has been posted.
 */
class SaveCompanySettings
{
    public const KEYS = [
        'company_name', 'company_tax_number', 'company_commercial_register',
        'company_address', 'company_phone', 'default_locale',
    ];

    public function __construct(private readonly Settings $settings) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'company_tax_number' => ['nullable', 'string', 'max:32'],
            'company_commercial_register' => ['nullable', 'string', 'max:64'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:32'],
            'default_locale' => ['required', Rule::in(SetLocale::SUPPORTED)],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated against rules()
     */
    public function handle(User $actor, array $data): void
    {
        Gate::forUser($actor)->authorize('core.settings.manage');

        DB::transaction(function () use ($data) {
            foreach (self::KEYS as $key) {
                $this->settings->set("core.{$key}", $data[$key] ?? null);
            }
        });
    }
}
