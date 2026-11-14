<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Charge;
use App\Models\Delivery;
use App\Models\ExchangeRate;
use App\Models\ExpenseTemplate;
use App\Models\Occupancy;
use App\Models\PeriodItem;
use App\Models\Person;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_resident_workspace_and_documents_are_scoped_to_their_property(): void
    {
        $mine = Property::factory()->create();
        $other = Property::factory()->create();
        $resident = User::factory()->create(['permissions' => ['community.view', 'finance.view', 'reports.view'], 'property_ids' => [$mine->id]]);
        $person = Person::factory()->create(['name' => 'Persona privada']);
        Occupancy::factory()->create(['property_id' => $other->id, 'person_id' => $person->id]);
        $response = $this->actingAs($resident)->getJson('/api/workspace')->assertOk();
        $response->assertJsonCount(1, 'properties')->assertJsonPath('properties.0.id', $mine->id)->assertJsonCount(0, 'people');
        $this->getJson('/api/properties/'.$other->id.'/statement')->assertForbidden();
        $this->get('/documents/properties/'.$other->id)->assertForbidden();
        $this->postJson('/api/periods', ['month' => '2026-01', 'reserve_basis_points' => 1000])->assertForbidden();
    }

    public function test_recurring_load_and_issue_are_idempotent_and_produce_pdf(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Property::factory()->count(90)->create();
        $template = ExpenseTemplate::create(['description' => 'Jardinero', 'category' => 'sector_fixed', 'amount_usd_cents' => 90000, 'recurring' => true, 'active' => true, 'is_provision' => false]);
        $this->actingAs($admin);
        $period = $this->postJson('/api/periods', ['month' => now()->format('Y-m'), 'reserve_basis_points' => 1000])->assertCreated()->json('id');
        $this->postJson('/api/periods/'.$period.'/load-recurring')->assertOk();
        $this->postJson('/api/periods/'.$period.'/load-recurring')->assertOk();
        $this->assertDatabaseCount('period_items', 1);
        $this->assertDatabaseHas('period_items', ['expense_template_id' => $template->id]);
        $this->postJson('/api/periods/'.$period.'/issue')->assertOk();
        $this->postJson('/api/periods/'.$period.'/issue')->assertOk();
        $this->assertDatabaseCount('charges', 90);
        $item = PeriodItem::first();
        $this->deleteJson('/api/items/'.$item->id)->assertUnprocessable();
        $charge = Charge::first();
        $this->assertNotNull($charge->document_snapshot);
        $response = $this->get('/documents/charges/'.$charge->id)->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_log_mailer_creates_private_previews_without_queuing_real_email(): void
    {
        config(['mail.default' => 'log']);
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $property = Property::factory()->create();
        $owner = Person::factory()->create(['email' => 'persona@example.test']);
        Occupancy::factory()->create(['property_id' => $property->id, 'person_id' => $owner->id, 'relationship' => 'owner', 'starts_on' => today()->toDateString(), 'ends_on' => null]);
        $this->actingAs($admin)->postJson('/api/communications', ['subject' => 'Comunicado', 'body' => 'Prueba local', 'kind' => 'announcement', 'property_ids' => [$property->id]])->assertOk()->assertJsonCount(1, 'deliveries');
        $delivery = Delivery::first();
        $this->assertSame('preview', $delivery->status);
        $this->get('/api/deliveries/'.$delivery->id.'/preview')->assertOk()->assertSee('Prueba local');
        Queue::assertNothingPushed();
    }

    public function test_manual_rate_permission_and_input_validation(): void
    {
        $resident = User::factory()->create(['permissions' => ['finance.view']]);
        $this->actingAs($resident)->postJson('/api/rates', ['date' => today()->toDateString(), 'rate' => '100', 'reason' => 'Consulta BCV'])->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->postJson('/api/rates', ['date' => today()->toDateString(), 'rate' => '100.123456', 'reason' => 'Consulta BCV'])->assertOk()->assertJsonPath('rate_micros', 100123456);
        $this->postJson('/api/rates', ['date' => today()->toDateString(), 'rate' => '0', 'reason' => 'Consulta BCV'])->assertUnprocessable();
    }

    public function test_scoped_communications_user_cannot_read_other_users_deliveries(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $assistant = User::factory()->create(['permissions' => ['communications.manage']]);
        $delivery = Delivery::create(['recipient' => 'privado@example.test', 'subject' => 'Privado', 'body' => 'Datos privados', 'kind' => 'announcement', 'status' => 'preview', 'created_by' => $admin->id]);
        $this->actingAs($assistant)->getJson('/api/workspace')->assertOk()->assertJsonCount(0, 'deliveries');
        $this->get('/api/deliveries/'.$delivery->id.'/preview')->assertForbidden();
    }

    public function test_transfer_api_preserves_verified_rate_after_later_rate_edits(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $property = Property::factory()->create();
        $bank = BankAccount::factory()->create(['opening_balance_bs_cents' => 500000]);
        $date = today()->toDateString();
        $rate = ExchangeRate::create(['date' => $date, 'rate_micros' => 100000000, 'source' => 'BCV verificado', 'reference_date' => $date]);
        $this->actingAs($admin);
        $transfer = ['bank_account_id' => $bank->id, 'paid_on' => $date, 'amount_bs' => '1000.00', 'rate' => '100', 'idempotency_key' => (string) Str::uuid()];
        $payment = $this->postJson('/api/payments', $transfer + ['reference' => 'PRUEBA-123', 'splits' => [['property_id' => $property->id, 'amount_usd' => '10.00']]])
            ->assertCreated()->assertJsonPath('rate_source', 'BCV verificado')->assertJsonPath('rate_reference_date', $date)->json('id');
        $transfer['idempotency_key'] = (string) Str::uuid();
        $this->postJson('/api/expenses', $transfer + ['reference' => 'EGRESO-123', 'description' => 'Servicio', 'fund' => 'operating'])
            ->assertCreated()->assertJsonPath('reference', 'EGRESO-123')->assertJsonPath('rate_reference_date', $date);
        $rate->update(['rate_micros' => 200000000]);
        $this->assertDatabaseHas('payments', ['id' => $payment, 'rate_micros' => 100000000, 'amount_usd_cents' => 1000]);
    }
}
