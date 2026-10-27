<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\Major;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\User;
use Database\Seeders\ClassLevelSeeder;
use Database\Seeders\MajorSeeder;
use Database\Seeders\MemberSeeder;
use Database\Seeders\MemberTypeSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use RefreshDatabase;

    private function makeDependencies(): array
    {
        $type = MemberType::create(['code' => 'TEST', 'name' => 'Tes']);
        $cls = ClassLevel::create(['code' => 'TEST', 'name' => 'Tes']);
        $mjr = Major::create(['code' => 'TEST', 'name' => 'Tes']);

        return [$type, $cls, $mjr];
    }

    public function test_members_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('members'));
    }

    public function test_member_number_is_required(): void
    {
        [$type] = $this->makeDependencies();

        $this->expectException(QueryException::class);
        Member::create([
            'name' => 'Budi',
            'member_type_id' => $type->id,
            'joined_at' => '2025-01-01',
        ]);
    }

    public function test_member_number_must_be_unique(): void
    {
        [$type] = $this->makeDependencies();

        Member::create(['member_number' => 'MBR-001', 'name' => 'Budi', 'member_type_id' => $type->id, 'joined_at' => '2025-01-01']);

        $this->expectException(QueryException::class);
        Member::create(['member_number' => 'MBR-001', 'name' => 'Andi', 'member_type_id' => $type->id, 'joined_at' => '2025-01-01']);
    }

    public function test_name_is_required(): void
    {
        [$type] = $this->makeDependencies();

        $this->expectException(QueryException::class);
        Member::create([
            'member_number' => 'MBR-001',
            'member_type_id' => $type->id,
            'joined_at' => '2025-01-01',
        ]);
    }

    public function test_member_type_id_is_required(): void
    {
        $this->expectException(QueryException::class);
        Member::create([
            'member_number' => 'MBR-001',
            'name' => 'Budi',
            'joined_at' => '2025-01-01',
        ]);
    }

    public function test_user_id_can_be_null(): void
    {
        [$type] = $this->makeDependencies();
        $member = Member::create(['member_number' => 'MBR-001', 'name' => 'Budi', 'member_type_id' => $type->id, 'joined_at' => '2025-01-01']);

        $this->assertNull($member->user_id);
    }

    public function test_user_id_must_be_unique_when_filled(): void
    {
        [$type] = $this->makeDependencies();
        $user = User::factory()->create();

        Member::create(['member_number' => 'MBR-001', 'name' => 'Budi', 'member_type_id' => $type->id, 'joined_at' => '2025-01-01', 'user_id' => $user->id]);

        $this->expectException(QueryException::class);
        Member::create(['member_number' => 'MBR-002', 'name' => 'Andi', 'member_type_id' => $type->id, 'joined_at' => '2025-01-01', 'user_id' => $user->id]);
    }

    public function test_identity_number_can_be_null(): void
    {
        [$type] = $this->makeDependencies();
        $member = Member::create(['member_number' => 'MBR-001', 'name' => 'Budi', 'member_type_id' => $type->id, 'joined_at' => '2025-01-01', 'identity_number' => null]);

        $this->assertNull($member->identity_number);
    }

    public function test_identity_number_must_be_unique_when_filled(): void
    {
        [$type] = $this->makeDependencies();

        Member::create(['member_number' => 'MBR-001', 'name' => 'Budi', 'member_type_id' => $type->id, 'joined_at' => '2025-01-01', 'identity_number' => 'ID-001']);

        $this->expectException(QueryException::class);
        Member::create(['member_number' => 'MBR-002', 'name' => 'Andi', 'member_type_id' => $type->id, 'joined_at' => '2025-01-01', 'identity_number' => 'ID-001']);
    }

    public function test_class_level_and_major_can_be_null(): void
    {
        [$type] = $this->makeDependencies();
        $member = Member::create(['member_number' => 'MBR-001', 'name' => 'Budi', 'member_type_id' => $type->id, 'joined_at' => '2025-01-01']);

        $this->assertNull($member->class_level_id);
        $this->assertNull($member->major_id);
    }

    public function test_joined_at_is_required(): void
    {
        [$type] = $this->makeDependencies();

        $this->expectException(QueryException::class);
        Member::create([
            'member_number' => 'MBR-001',
            'name' => 'Budi',
            'member_type_id' => $type->id,
        ]);
    }

    public function test_status_defaults_to_active(): void
    {
        [$type] = $this->makeDependencies();
        $member = Member::create(['member_number' => 'MBR-001', 'name' => 'Budi', 'member_type_id' => $type->id, 'joined_at' => '2025-01-01']);

        $this->assertSame('ACTIVE', $member->fresh()->status);
    }

    public function test_valid_status_active_inactive_suspended(): void
    {
        [$type] = $this->makeDependencies();

        $m1 = Member::create(['member_number' => 'MBR-01', 'name' => 'A', 'member_type_id' => $type->id, 'joined_at' => '2025-01-01', 'status' => 'ACTIVE']);
        $m2 = Member::create(['member_number' => 'MBR-02', 'name' => 'B', 'member_type_id' => $type->id, 'joined_at' => '2025-01-01', 'status' => 'INACTIVE']);
        $m3 = Member::create(['member_number' => 'MBR-03', 'name' => 'C', 'member_type_id' => $type->id, 'joined_at' => '2025-01-01', 'status' => 'SUSPENDED']);

        $this->assertSame('ACTIVE', $m1->status);
        $this->assertSame('INACTIVE', $m2->status);
        $this->assertSame('SUSPENDED', $m3->status);
    }

    public function test_member_belongs_to_user_and_user_has_one_member(): void
    {
        [$type] = $this->makeDependencies();
        $user = User::factory()->create();

        $member = Member::create(['member_number' => 'MBR-001', 'name' => 'Budi', 'member_type_id' => $type->id, 'joined_at' => '2025-01-01', 'user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $member->user);
        $this->assertInstanceOf(Member::class, $user->member);
        $this->assertSame($member->id, $user->member->id);
    }

    public function test_member_relationships(): void
    {
        [$type, $cls, $mjr] = $this->makeDependencies();
        $member = Member::create([
            'member_number' => 'MBR-001',
            'name' => 'Budi',
            'member_type_id' => $type->id,
            'class_level_id' => $cls->id,
            'major_id' => $mjr->id,
            'joined_at' => '2025-01-01',
        ]);

        $this->assertInstanceOf(MemberType::class, $member->memberType);
        $this->assertInstanceOf(ClassLevel::class, $member->classLevel);
        $this->assertInstanceOf(Major::class, $member->major);

        $this->assertCount(1, $type->members);
        $this->assertCount(1, $cls->members);
        $this->assertCount(1, $mjr->members);
    }

    public function test_member_without_user_can_exist(): void
    {
        [$type] = $this->makeDependencies();
        $member = Member::create(['member_number' => 'MBR-001', 'name' => 'Budi', 'member_type_id' => $type->id, 'joined_at' => '2025-01-01']);

        $this->assertNotNull($member->id);
        $this->assertNull($member->user_id);
    }

    public function test_deleting_user_sets_member_user_id_to_null(): void
    {
        [$type] = $this->makeDependencies();
        $user = User::factory()->create();
        $member = Member::create(['member_number' => 'MBR-001', 'name' => 'Budi', 'member_type_id' => $type->id, 'joined_at' => '2025-01-01', 'user_id' => $user->id]);

        $user->delete();
        $this->assertNull($member->fresh()->user_id);
    }

    public function test_deleting_member_type_is_restricted_when_used(): void
    {
        [$type] = $this->makeDependencies();
        Member::create(['member_number' => 'MBR-001', 'name' => 'Budi', 'member_type_id' => $type->id, 'joined_at' => '2025-01-01']);

        $this->expectException(QueryException::class);
        $type->delete();
    }

    public function test_deleting_class_level_sets_member_class_level_id_to_null(): void
    {
        [$type, $cls, $mjr] = $this->makeDependencies();
        $member = Member::create([
            'member_number' => 'MBR-001',
            'name' => 'Budi',
            'member_type_id' => $type->id,
            'class_level_id' => $cls->id,
            'joined_at' => '2025-01-01',
        ]);

        $cls->delete();
        $this->assertNull($member->fresh()->class_level_id);
    }

    public function test_deleting_major_sets_member_major_id_to_null(): void
    {
        [$type, $cls, $mjr] = $this->makeDependencies();
        $member = Member::create([
            'member_number' => 'MBR-001',
            'name' => 'Budi',
            'member_type_id' => $type->id,
            'major_id' => $mjr->id,
            'joined_at' => '2025-01-01',
        ]);

        $mjr->delete();
        $this->assertNull($member->fresh()->major_id);
    }

    public function test_seeder_creates_members(): void
    {
        $this->seed(MemberTypeSeeder::class);
        $this->seed(ClassLevelSeeder::class);
        $this->seed(MajorSeeder::class);

        $this->seed(MemberSeeder::class);

        $this->assertSame(12, Member::count());
    }

    public function test_seeder_contains_expected_member_types(): void
    {
        $this->seed(MemberTypeSeeder::class);
        $this->seed(ClassLevelSeeder::class);
        $this->seed(MajorSeeder::class);

        $this->seed(MemberSeeder::class);

        $studentTypeId = MemberType::where('code', 'STUDENT')->value('id');
        $teacherTypeId = MemberType::where('code', 'TEACHER')->value('id');

        $this->assertSame(8, Member::where('member_type_id', $studentTypeId)->count());
        $this->assertSame(2, Member::where('member_type_id', $teacherTypeId)->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(MemberTypeSeeder::class);
        $this->seed(ClassLevelSeeder::class);
        $this->seed(MajorSeeder::class);

        $this->seed(MemberSeeder::class);
        $count1 = Member::count();

        $this->seed(MemberSeeder::class);
        $count2 = Member::count();

        $this->assertSame($count1, $count2);
    }
}
