<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SchoolIdentityApiTest extends TestCase
{
    use RefreshDatabase {
        refreshTestDatabase as protected refreshTestDatabaseAfterSafetyCheck;
    }

    private User $superAdmin;

    private User $staffUser;

    private User $memberUser;

    private string $testStoragePath;

    protected function refreshTestDatabase(): void
    {
        if (
            ! app()->environment('testing')
            || config('database.default') !== 'pgsql'
            || config('database.connections.pgsql.database') !== 'testing'
            || DB::connection('pgsql')->getDriverName() !== 'pgsql'
            || DB::connection('pgsql')->selectOne('SELECT current_database() AS name')->name !== 'testing'
        ) {
            throw new \RuntimeException('Tests must run against the isolated PostgreSQL testing database.');
        }

        $this->refreshTestDatabaseAfterSafetyCheck();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->testStoragePath = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR
            .'library-system-school-identity-tests-'.bin2hex(random_bytes(8));
        app()->useStoragePath($this->testStoragePath);

        $this->seed(RolePermissionSeeder::class);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('SUPER_ADMIN');

        $this->staffUser = User::factory()->create();
        $this->staffUser->assignRole('LIBRARY_STAFF');

        $this->memberUser = User::factory()->create();
        $this->memberUser->assignRole('MEMBER');
    }

    protected function tearDown(): void
    {
        if (isset($this->testStoragePath) && File::isDirectory($this->testStoragePath)) {
            File::deleteDirectory($this->testStoragePath);
        }

        parent::tearDown();
    }

    public function test_public_can_fetch_default_school_identity(): void
    {
        $response = $this->getJson('/api/settings/school-identity');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.school_name', 'Perpustakaan Sekolah')
            ->assertJsonPath('data.theme_color', '#1d4ed8')
            ->assertJsonPath('data.has_custom_logo', false)
            ->assertJsonPath('data.logo_url', null);
    }

    public function test_super_admin_can_update_school_identity(): void
    {
        $payload = [
            'school_name' => 'SMA Negeri 1 Prestasi',
            'theme_color' => '#059669',
        ];

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson('/api/settings/school-identity', $payload);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.school_name', 'SMA Negeri 1 Prestasi')
            ->assertJsonPath('data.theme_color', '#059669');

        // Verify persistence via public GET
        $publicGet = $this->getJson('/api/settings/school-identity');
        $publicGet->assertOk()
            ->assertJsonPath('data.school_name', 'SMA Negeri 1 Prestasi')
            ->assertJsonPath('data.theme_color', '#059669');
    }

    public function test_non_admin_cannot_update_school_identity(): void
    {
        $payload = [
            'school_name' => 'Hacked School',
            'theme_color' => '#ff0000',
        ];

        // Guest
        $this->putJson('/api/settings/school-identity', $payload)
            ->assertUnauthorized();

        // Staff
        $this->actingAs($this->staffUser, 'sanctum')
            ->putJson('/api/settings/school-identity', $payload)
            ->assertForbidden();

        // Member
        $this->actingAs($this->memberUser, 'sanctum')
            ->putJson('/api/settings/school-identity', $payload)
            ->assertForbidden();
    }

    public function test_update_validates_required_fields_and_hex_color(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson('/api/settings/school-identity', [
                'school_name' => '',
                'theme_color' => 'invalid-color',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['school_name', 'theme_color']);
    }

    public function test_super_admin_can_upload_serve_and_delete_logo(): void
    {
        $fakeImage = UploadedFile::fake()->image('school_logo.png', 200, 200);

        $uploadResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/settings/school-identity/logo', [
                'logo' => $fakeImage,
            ]);

        $uploadResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_custom_logo', true);

        $this->assertNotNull($uploadResponse->json('data.logo_url'));

        // Serve logo via public endpoint
        $logoResponse = $this->get('/api/settings/school-identity/logo');
        $logoResponse->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        // Delete logo
        $deleteResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->deleteJson('/api/settings/school-identity/logo');

        $deleteResponse->assertOk()
            ->assertJsonPath('data.has_custom_logo', false)
            ->assertJsonPath('data.logo_url', null);

        // Serving deleted logo returns 404
        $this->get('/api/settings/school-identity/logo')
            ->assertNotFound();
    }

    public function test_logo_upload_rejects_disallowed_mime_types(): void
    {
        $fakePdf = UploadedFile::fake()->create('malicious.pdf', 500, 'application/pdf');

        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/settings/school-identity/logo', [
                'logo' => $fakePdf,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logo']);
    }

    public function test_logo_upload_rejects_files_exceeding_size_limit(): void
    {
        // 3 MB (limit is 2 MB)
        $largeImage = UploadedFile::fake()->image('huge.png')->size(3072);

        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/settings/school-identity/logo', [
                'logo' => $largeImage,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logo']);
    }
}
