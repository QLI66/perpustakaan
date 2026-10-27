<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_valid_login(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'device_name' => 'test_device',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'email',
                ],
                'token',
            ],
        ]);

        $this->assertArrayNotHasKey('password', $response->json('data.user'));
    }

    public function test_login_accepts_case_insensitive_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'login' => 'TEST@EXAMPLE.COM',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
    }

    public function test_login_accepts_case_insensitive_username(): void
    {
        User::factory()->create([
            'email' => 'member@example.com',
            'username' => 'member123',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login' => 'MEMBER123',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
    }

    public function test_invalid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'login' => 'TEST@EXAMPLE.COM',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['login']);
    }

    public function test_missing_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_me_without_token(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    public function test_me_with_token(): void
    {
        $token = $this->user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user' => [
                    'id',
                    'email',
                ],
            ],
        ]);
    }

    public function test_logout(): void
    {
        $token = $this->user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals('Berhasil logout', $response->json('message'));
        $this->assertCount(0, $this->user->tokens);
    }

    public function test_login_accepts_member_identity_number(): void
    {
        $memberType = \App\Models\MemberType::firstOrCreate(
            ['code' => 'STUDENT'],
            ['name' => 'Siswa', 'is_active' => true]
        );

        $studentUser = User::factory()->create([
            'name' => 'Student Test',
            'email' => null,
            'username' => '0107324894',
            'password' => Hash::make('0107324894'),
        ]);

        \App\Models\Member::create([
            'user_id' => $studentUser->id,
            'member_number' => '131232040009250005',
            'name' => 'Student Test',
            'member_type_id' => $memberType->id,
            'identity_number' => '0107324894',
            'joined_at' => now()->format('Y-m-d'),
            'status' => 'ACTIVE',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login' => '0107324894',
            'password' => '0107324894',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $studentUser->id,
                    'username' => '0107324894',
                ],
            ],
        ]);
    }

    public function test_login_accepts_member_number(): void
    {
        $memberType = \App\Models\MemberType::firstOrCreate(
            ['code' => 'STUDENT'],
            ['name' => 'Siswa', 'is_active' => true]
        );

        $studentUser = User::factory()->create([
            'name' => 'Student Test 2',
            'email' => null,
            'username' => '0107324895',
            'password' => Hash::make('password123'),
        ]);

        \App\Models\Member::create([
            'user_id' => $studentUser->id,
            'member_number' => '131232040009250006',
            'name' => 'Student Test 2',
            'member_type_id' => $memberType->id,
            'identity_number' => '0107324895',
            'joined_at' => now()->format('Y-m-d'),
            'status' => 'ACTIVE',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login' => '131232040009250006',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $studentUser->id,
                ],
            ],
        ]);
    }

    public function test_login_accepts_case_insensitive_member_number(): void
    {
        $memberType = \App\Models\MemberType::firstOrCreate(
            ['code' => 'GENERAL'],
            ['name' => 'Umum', 'is_active' => true]
        );

        $memberUser = User::factory()->create([
            'name' => 'Member Test 3',
            'email' => 'member3@example.com',
            'username' => 'member3',
            'password' => Hash::make('password123'),
        ]);

        \App\Models\Member::create([
            'user_id' => $memberUser->id,
            'member_number' => 'MEM-TEST-999',
            'name' => 'Member Test 3',
            'member_type_id' => $memberType->id,
            'joined_at' => now()->format('Y-m-d'),
            'status' => 'ACTIVE',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login' => 'mem-test-999',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $this->assertEquals($memberUser->id, $response->json('data.user.id'));
    }

    public function test_login_accepts_identifier_in_email_field(): void
    {
        $memberType = \App\Models\MemberType::firstOrCreate(
            ['code' => 'STUDENT'],
            ['name' => 'Siswa', 'is_active' => true]
        );

        $studentUser = User::factory()->create([
            'name' => 'Student Test 4',
            'email' => null,
            'username' => '0107324897',
            'password' => Hash::make('password123'),
        ]);

        \App\Models\Member::create([
            'user_id' => $studentUser->id,
            'member_number' => '131232040009250008',
            'name' => 'Student Test 4',
            'member_type_id' => $memberType->id,
            'identity_number' => '0107324897',
            'joined_at' => now()->format('Y-m-d'),
            'status' => 'ACTIVE',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => '0107324897',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $this->assertEquals($studentUser->id, $response->json('data.user.id'));
    }

    public function test_invalid_password_for_member_identifier_is_rejected(): void
    {
        $memberType = \App\Models\MemberType::firstOrCreate(
            ['code' => 'STUDENT'],
            ['name' => 'Siswa', 'is_active' => true]
        );

        $studentUser = User::factory()->create([
            'name' => 'Student Test 5',
            'email' => null,
            'username' => '0107324898',
            'password' => Hash::make('correctpassword'),
        ]);

        \App\Models\Member::create([
            'user_id' => $studentUser->id,
            'member_number' => '131232040009250009',
            'name' => 'Student Test 5',
            'member_type_id' => $memberType->id,
            'identity_number' => '0107324898',
            'joined_at' => now()->format('Y-m-d'),
            'status' => 'ACTIVE',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login' => '0107324898',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['login']);
    }

    public function test_nonexistent_identifier_is_rejected(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'login' => 'nonexistent_identifier_99999',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['login']);
    }
}
