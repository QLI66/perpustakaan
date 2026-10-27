<?php

namespace Tests\Feature;

use App\Models\MemberType;
use Database\Seeders\MemberTypeSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MemberTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_types_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('member_types'));
    }

    public function test_code_must_be_unique(): void
    {
        MemberType::create(['code' => 'TEST', 'name' => 'Tes']);

        $this->expectException(QueryException::class);
        MemberType::create(['code' => 'TEST', 'name' => 'Tes 2']);
    }

    public function test_name_is_required(): void
    {
        $this->expectException(QueryException::class);
        MemberType::create(['code' => 'TEST']); // name missing
    }

    public function test_description_is_nullable(): void
    {
        $type = MemberType::create(['code' => 'TEST', 'name' => 'Tes', 'description' => null]);
        $this->assertNull($type->description);
    }

    public function test_is_active_defaults_to_true(): void
    {
        $type = MemberType::create(['code' => 'TEST', 'name' => 'Tes']);
        $this->assertTrue($type->fresh()->is_active);
    }

    public function test_sort_order_defaults_to_zero(): void
    {
        $type = MemberType::create(['code' => 'TEST', 'name' => 'Tes']);
        $this->assertSame(0, $type->fresh()->sort_order);
    }

    public function test_active_scope_returns_only_active_records(): void
    {
        MemberType::create(['code' => 'ACTIVE', 'name' => 'Active', 'is_active' => true]);
        MemberType::create(['code' => 'INACTIVE', 'name' => 'Inactive', 'is_active' => false]);

        $activeTypes = MemberType::active()->get();

        $this->assertCount(1, $activeTypes);
        $this->assertSame('ACTIVE', $activeTypes->first()->code);
    }

    public function test_casts_is_active_to_boolean(): void
    {
        $type = MemberType::create(['code' => 'TEST', 'name' => 'Tes', 'is_active' => 0]);
        $this->assertIsBool($type->is_active);
        $this->assertFalse($type->is_active);
    }

    public function test_seeder_creates_4_member_types(): void
    {
        $this->seed(MemberTypeSeeder::class);
        $this->assertSame(4, MemberType::count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(MemberTypeSeeder::class);
        $count1 = MemberType::count();

        $this->seed(MemberTypeSeeder::class);
        $count2 = MemberType::count();

        $this->assertSame($count1, $count2);
    }

    public function test_codes_are_correct(): void
    {
        $this->seed(MemberTypeSeeder::class);

        $codes = MemberType::pluck('code')->toArray();

        $this->assertContains('STUDENT', $codes);
        $this->assertContains('TEACHER', $codes);
        $this->assertContains('EDUCATION_STAFF', $codes);
        $this->assertContains('OTHER', $codes);
    }
}
