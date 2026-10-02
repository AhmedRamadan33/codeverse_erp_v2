<?php

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\AuditLog;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Partner;
use Tests\TestCase;

class PartnerAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_updating_and_deleting_a_partner_is_audited_with_the_acting_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $partner = Partner::factory()->create(['name' => 'Old Name', 'credit_limit' => '5000']);
        $partner->update(['name' => 'New Name']);
        $partner->delete();

        $logs = AuditLog::where('auditable_type', $partner->getMorphClass())->orderBy('id')->get();

        $this->assertSame(['created', 'updated', 'deleted'], $logs->pluck('event')->all());
        $this->assertSame($user->id, $logs[1]->user_id);
        $this->assertSame(['name' => 'Old Name'], $logs[1]->old_values);
        $this->assertSame(['name' => 'New Name'], $logs[1]->new_values);
        $this->assertSame('5000.0000', $logs[0]->new_values['credit_limit']);
    }

    public function test_saving_without_changes_writes_no_audit_entry(): void
    {
        $partner = Partner::factory()->create();
        $partner->touch();

        $this->assertSame(1, AuditLog::where('auditable_type', $partner->getMorphClass())->where('auditable_id', $partner->id)->count());
    }

    public function test_branch_availability_includes_shared_partners(): void
    {
        $cairo = Branch::factory()->create();
        $alex = Branch::factory()->create();
        $shared = Partner::factory()->create();
        $cairoOnly = Partner::factory()->create(['branch_id' => $cairo->id]);
        Partner::factory()->create(['branch_id' => $alex->id]);

        $this->assertEqualsCanonicalizing(
            [$shared->id, $cairoOnly->id],
            Partner::availableIn($cairo->id)->pluck('id')->all(),
        );
    }
}
