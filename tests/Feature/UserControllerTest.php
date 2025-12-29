<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_success_returns_id_name_email_token_and_creates_user(): void
    {
        $payload = [
            'name' => 'German',
            'email' => 'german@gmail.com',
            'password' => 'qwert1234',
            'password_confirmation' => 'qwert1234',
        ];

        $res = $this->postJson('/api/auth/register', $payload);

        $res->assertCreated()
            ->assertJsonStructure(['id', 'name', 'email', 'token'])
            ->assertJsonMissing(['password', 'token_type', 'user'])
            ->assertJson([
                'name' => 'German',
                'email' => 'german@gmail.com',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'german@gmail.com',
            'name' => 'German',
        ]);

        $user = User::where('email', 'german@gmail.com')->firstOrFail();
        $this->assertTrue(Hash::check('qwert1234', $user->password));
    }

    public function test_register_validation_error_returns_422_json(): void
    {
        $res = $this->postJson('/api/auth/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => '123',
            'password_confirmation' => '456',
        ]);

        $res->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_register_duplicate_email_returns_422(): void
    {
        User::factory()->create(['email' => 'german@gmail.com']);

        $res = $this->postJson('/api/auth/register', [
            'name' => 'German',
            'email' => 'german@gmail.com',
            'password' => 'qwert1234',
            'password_confirmation' => 'qwert1234',
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_success_returns_id_name_email_token(): void
    {
        $user = User::factory()->create([
            'name' => 'German',
            'email' => 'german@gmail.com',
            'password' => Hash::make('qwert1234'),
        ]);

        $res = $this->postJson('/api/auth/login', [
            'email' => 'german@gmail.com',
            'password' => 'qwert1234',
        ]);

        $res->assertOk()
            ->assertJsonStructure(['id', 'name', 'email', 'token'])
            ->assertJsonMissing(['password', 'token_type', 'user'])
            ->assertJson([
                'id' => $user->id,
                'name' => 'German',
                'email' => 'german@gmail.com',
            ]);
    }

    public function test_login_invalid_credentials_returns_422(): void
    {
        User::factory()->create([
            'email' => 'german@gmail.com',
            'password' => Hash::make('qwert1234'),
        ]);

        $res = $this->postJson('/api/auth/login', [
            'email' => 'german@gmail.com',
            'password' => 'wrong',
        ]);

        $res->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_validation_error_returns_422_json(): void
    {
        $res = $this->postJson('/api/auth/login', [
            'email' => 'not-an-email',
        ]);

        $res->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_me_requires_auth(): void
    {
        $res = $this->getJson('/api/user');

        $res->assertStatus(401);
    }

    public function test_me_returns_id_name_email_for_authenticated_user(): void
    {
        $user = User::factory()->create([
            'name' => 'German',
            'email' => 'german@gmail.com',
        ]);

        Sanctum::actingAs($user);

        $res = $this->getJson('/api/user');

        $res->assertOk()
            ->assertExactJson([
                'id' => $user->id,
                'name' => 'German',
                'email' => 'german@gmail.com',
            ]);
    }

    public function test_logout_requires_auth(): void
    {
        $res = $this->postJson('/api/auth/logout');
        $res->assertStatus(401);
    }

    public function test_logout_deletes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api');

        $res = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->postJson('/api/auth/logout');

        $res->assertOk()->assertJson(['message' => 'Logged out']);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }
}
