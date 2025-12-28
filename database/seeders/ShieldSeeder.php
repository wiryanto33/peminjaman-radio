<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use BezhanSalleh\FilamentShield\Support\Utils;
use Spatie\Permission\PermissionRegistrar;

class ShieldSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $rolesWithPermissions = '[{"name":"super_admin","guard_name":"web","permissions":["view_book","view_any_book","create_book","update_book","restore_book","restore_any_book","replicate_book","reorder_book","delete_book","delete_any_book","force_delete_book","force_delete_any_book","book:create_book","book:update_book","book:delete_book","book:pagination_book","book:detail_book","view_role","view_any_role","create_role","update_role","delete_role","delete_any_role","view_token","view_any_token","create_token","update_token","restore_token","restore_any_token","replicate_token","reorder_token","delete_token","delete_any_token","force_delete_token","force_delete_any_token","view_user","view_any_user","create_user","update_user","restore_user","restore_any_user","replicate_user","reorder_user","delete_user","delete_any_user","force_delete_user","force_delete_any_user","page_ManageSetting","page_Themes","page_MyProfilePage","view_kategori::radio","view_any_kategori::radio","create_kategori::radio","update_kategori::radio","restore_kategori::radio","restore_any_kategori::radio","replicate_kategori::radio","reorder_kategori::radio","delete_kategori::radio","delete_any_kategori::radio","force_delete_kategori::radio","force_delete_any_kategori::radio","view_peminjaman","view_any_peminjaman","create_peminjaman","update_peminjaman","restore_peminjaman","restore_any_peminjaman","replicate_peminjaman","reorder_peminjaman","delete_peminjaman","delete_any_peminjaman","force_delete_peminjaman","force_delete_any_peminjaman","view_pengembalian","view_any_pengembalian","create_pengembalian","update_pengembalian","restore_pengembalian","restore_any_pengembalian","replicate_pengembalian","reorder_pengembalian","delete_pengembalian","delete_any_pengembalian","force_delete_pengembalian","force_delete_any_pengembalian","view_radio","view_any_radio","create_radio","update_radio","restore_radio","restore_any_radio","replicate_radio","reorder_radio","delete_radio","delete_any_radio","force_delete_radio","force_delete_any_radio"]},{"name":"peminjam","guard_name":"web","permissions":["view_peminjaman","view_any_peminjaman","view_radio","view_any_radio"]}]';
        $directPermissions = '[]';

        static::makeRolesWithPermissions($rolesWithPermissions);
        static::makeDirectPermissions($directPermissions);

        $this->command->info('Shield Seeding Completed.');
    }

    protected static function makeRolesWithPermissions(string $rolesWithPermissions): void
    {
        if (! blank($rolePlusPermissions = json_decode($rolesWithPermissions, true))) {
            /** @var Model $roleModel */
            $roleModel = Utils::getRoleModel();
            /** @var Model $permissionModel */
            $permissionModel = Utils::getPermissionModel();

            foreach ($rolePlusPermissions as $rolePlusPermission) {
                $role = $roleModel::firstOrCreate([
                    'name' => $rolePlusPermission['name'],
                    'guard_name' => $rolePlusPermission['guard_name'],
                ]);

                if (! blank($rolePlusPermission['permissions'])) {
                    $permissionModels = collect($rolePlusPermission['permissions'])
                        ->map(fn ($permission) => $permissionModel::firstOrCreate([
                            'name' => $permission,
                            'guard_name' => $rolePlusPermission['guard_name'],
                        ]))
                        ->all();

                    $role->syncPermissions($permissionModels);
                }
            }
        }
    }

    public static function makeDirectPermissions(string $directPermissions): void
    {
        if (! blank($permissions = json_decode($directPermissions, true))) {
            /** @var Model $permissionModel */
            $permissionModel = Utils::getPermissionModel();

            foreach ($permissions as $permission) {
                if ($permissionModel::whereName($permission)->doesntExist()) {
                    $permissionModel::create([
                        'name' => $permission['name'],
                        'guard_name' => $permission['guard_name'],
                    ]);
                }
            }
        }
    }
}
