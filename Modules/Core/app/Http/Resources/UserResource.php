<?php

namespace Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'locale' => $this->locale,
            'is_active' => $this->is_active,
            'branches' => $this->branches->map(fn ($branch) => [
                'id' => $branch->id,
                'code' => $branch->code,
                'name' => $branch->name,
                'is_default' => (bool) $branch->pivot->is_default,
            ]),
            'permissions' => $this->isSuperAdmin() ? ['*'] : $this->getAllPermissions()->pluck('name'),
        ];
    }
}
