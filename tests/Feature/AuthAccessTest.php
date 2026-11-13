<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Route::has('auth.login')) {
            Route::middleware('web')->group(base_path('routes/auth.php'));
        }
    }

    public function test_login_returns_effective_permissions_and_logout_ends_session(): void
    {
        $user = User::factory()->create(['permissions' => ['payments.manage']]);

        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertOk()->assertJsonPath('user.id', $user->id)
            ->assertJsonFragment(['capabilities' => ['payments.view', 'payments.manage']])
            ->assertJsonStructure(['csrf_token']);
        $this->assertAuthenticatedAs($user);
        $this->assertArrayNotHasKey('password', $response->json('user'));

        $this->postJson('/api/logout')->assertOk()->assertJsonStructure(['csrf_token']);
        $this->assertGuest();
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_invalid_and_disabled_accounts_cannot_authenticate(): void
    {
        $user = User::factory()->create(['active' => false]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', ['email' => 'blocked@example.test', 'password' => 'wrong'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/login', ['email' => 'blocked@example.test', 'password' => 'wrong'])
            ->assertTooManyRequests();
    }

    public function test_disabling_an_account_revokes_an_existing_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->update(['active' => false]);

        $this->getJson('/api/me')->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_resident_can_only_access_assigned_properties(): void
    {
        $resident = User::factory()->create([
            'permissions' => ['community.view', 'finance.view'],
            'property_ids' => [4, 7],
        ]);

        $this->assertTrue($resident->canAccessProperty(4));
        $this->assertFalse($resident->canAccessProperty(5));
        $this->assertFalse($resident->allows('finance.manage'));
        $this->actingAs($resident)->getJson('/api/users')->assertForbidden();

        $assistant = User::factory()->create(['permissions' => ['community.manage']]);
        $this->assertTrue($assistant->canAccessProperty(5));
        $this->assertFalse($assistant->allows('finance.manage'));
    }

    public function test_administrator_creates_user_with_hashed_password_but_cannot_assign_elevated_roles(): void
    {
        $administrator = User::factory()->administrator()->create();
        $payload = [
            'name' => 'Asistente de prueba',
            'email' => 'assistant@example.test',
            'password' => 'UnaClaveSegura123',
            'role' => 'user',
            'permissions' => ['community.manage', 'payments.manage'],
            'property_ids' => [],
        ];

        $this->actingAs($administrator)->postJson('/api/users', $payload)->assertCreated();
        $created = User::query()->where('email', $payload['email'])->firstOrFail();
        $this->assertTrue(Hash::check($payload['password'], $created->password));

        foreach (['admin', 'superuser'] as $role) {
            $this->postJson('/api/users', [...$payload, 'email' => $role.'@example.test', 'role' => $role])
                ->assertForbidden();
        }
    }

    public function test_delegated_user_cannot_grant_permissions_beyond_their_own(): void
    {
        $assistant = User::factory()->create(['permissions' => ['users.manage', 'community.view']]);

        $this->actingAs($assistant)->postJson('/api/users', [
            'name' => 'Otra cuenta',
            'email' => 'other@example.test',
            'password' => 'OtraClaveSegura123',
            'permissions' => ['finance.manage'],
        ])->assertForbidden();
    }

    public function test_administrator_cannot_modify_superuser_and_self_access_changes_are_rejected(): void
    {
        $superuser = User::factory()->superuser()->create();
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)->putJson('/api/users/'.$superuser->id, [
            'name' => 'Cambio no autorizado', 'email' => $superuser->email,
        ])->assertForbidden();

        $this->putJson('/api/users/'.$administrator->id, [
            'name' => $administrator->name, 'email' => $administrator->email, 'role' => 'superuser',
        ])->assertUnprocessable()->assertJsonValidationErrors('role');

        $this->actingAs($superuser)->putJson('/api/users/'.$superuser->id, [
            'name' => $superuser->name, 'email' => $superuser->email, 'active' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('active');
    }

    public function test_password_update_preserves_access_and_old_password_stops_working(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)->putJson('/api/users/'.$administrator->id, [
            'name' => $administrator->name,
            'email' => $administrator->email,
            'password' => 'ClaveNuevaSegura123',
        ])->assertOk();

        $administrator->refresh();
        $this->assertTrue(Hash::check('ClaveNuevaSegura123', $administrator->password));
        $this->assertFalse(Hash::check('password', $administrator->password));
        $this->assertSame('admin', $administrator->role);
    }

    public function test_unknown_permissions_and_short_passwords_are_rejected(): void
    {
        $superuser = User::factory()->superuser()->create();

        $this->actingAs($superuser)->postJson('/api/users', [
            'name' => 'Cuenta inválida',
            'email' => 'invalid@example.test',
            'password' => '123',
            'permissions' => ['anything.manage'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['password', 'permissions.0']);
    }
}
