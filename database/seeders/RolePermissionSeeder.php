<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Define all permissions
        $permissions = [
            'books.view', 'books.create', 'books.update', 'books.delete',
            'categories.view', 'categories.create', 'categories.update', 'categories.delete',
            'authors.view', 'authors.create', 'authors.update', 'authors.delete',
            'publishers.view', 'publishers.create', 'publishers.update', 'publishers.delete',
            'locations.view', 'locations.create', 'locations.update', 'locations.delete',
            'collection_types.view', 'collection_types.create', 'collection_types.update', 'collection_types.delete',
            'class_levels.view', 'class_levels.create', 'class_levels.update', 'class_levels.delete',
            'majors.view', 'majors.create', 'majors.update', 'majors.delete',
            'member_types.view', 'member_types.create', 'member_types.update', 'member_types.delete',
            'book_copies.view', 'book_copies.create', 'book_copies.update', 'book_copies.delete',
            'book_targets.view', 'book_targets.create', 'book_targets.update', 'book_targets.delete',
            'members.view', 'members.create', 'members.update', 'members.delete',
            'users.view', 'users.create', 'users.update', 'users.delete',
            'roles.view', 'roles.create', 'roles.update', 'roles.delete',
            'loans.view', 'loans.create', 'loans.return', 'loans.renew',
            'fines.view', 'fines.payment',
            'book_incidents.view', 'book_incidents.create', 'book_incidents.resolve',
            'reservations.view', 'reservations.create', 'reservations.cancel', 'reservations.manage',
            'acquisitions.view', 'acquisitions.create', 'acquisitions.update', 'acquisitions.delete',
            'dashboard.view',
            'stock_opnames.view', 'stock_opnames.create', 'stock_opnames.update', 'stock_opnames.delete',
        ];

        // 2. Create permissions idempotently
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // 3. Create roles idempotently
        $superAdmin = Role::firstOrCreate(['name' => 'SUPER_ADMIN', 'guard_name' => 'web']);
        $libraryStaff = Role::firstOrCreate(['name' => 'LIBRARY_STAFF', 'guard_name' => 'web']);
        $member = Role::firstOrCreate(['name' => 'MEMBER', 'guard_name' => 'web']);

        // 4. Assign permissions to roles
        // SUPER_ADMIN gets all permissions
        $superAdmin->syncPermissions($permissions);

        // LIBRARY_STAFF gets all except 'users.create', 'users.update', 'users.delete', 'roles.*'
        // According to user requirements: library staff has CRUD for everything else and ONLY 'users.view'
        $libraryStaffPermissions = [
            'books.view', 'books.create', 'books.update', 'books.delete',
            'categories.view', 'categories.create', 'categories.update', 'categories.delete',
            'authors.view', 'authors.create', 'authors.update', 'authors.delete',
            'publishers.view', 'publishers.create', 'publishers.update', 'publishers.delete',
            'locations.view', 'locations.create', 'locations.update', 'locations.delete',
            'collection_types.view', 'collection_types.create', 'collection_types.update', 'collection_types.delete',
            'class_levels.view', 'class_levels.create', 'class_levels.update', 'class_levels.delete',
            'majors.view', 'majors.create', 'majors.update', 'majors.delete',
            'member_types.view', 'member_types.create', 'member_types.update', 'member_types.delete',
            'book_copies.view', 'book_copies.create', 'book_copies.update', 'book_copies.delete',
            'book_targets.view', 'book_targets.create', 'book_targets.update', 'book_targets.delete',
            'members.view', 'members.create', 'members.update', 'members.delete',
            'users.view', // Only view for users
            'loans.view', 'loans.create', 'loans.return', 'loans.renew',
            'fines.view', 'fines.payment',
            'book_incidents.view', 'book_incidents.create', 'book_incidents.resolve',
            'reservations.view', 'reservations.create', 'reservations.cancel', 'reservations.manage',
            'acquisitions.view', 'acquisitions.create', 'acquisitions.update', 'acquisitions.delete',
            'dashboard.view',
            'stock_opnames.view', 'stock_opnames.create', 'stock_opnames.update', 'stock_opnames.delete',
        ];
        $libraryStaff->syncPermissions($libraryStaffPermissions);

        // MEMBER gets view only for specified resources
        $memberPermissions = [
            'books.view',
            'categories.view',
            'authors.view',
            'publishers.view',
            'locations.view',
            'collection_types.view',
            'class_levels.view',
            'majors.view',
            'reservations.view',
            'reservations.create',
            'reservations.cancel',
        ];
        $member->syncPermissions($memberPermissions);
    }
}
