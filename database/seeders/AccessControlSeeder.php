<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Database\Seeder;

/**
 * Grants the local admin account (jordi) full access via the ACL data model.
 *
 * This is for the local sample data only — it deliberately uses the same
 * permission mechanism as every other account (a SUPER_ADMIN_PAGE row in
 * tbl_permissions) so the implicit `user_id = 1` special case never reappears.
 * Production cutover does NOT run this seeder: v1 permission rows are carried
 * over with the data itself.
 */
class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('username', 'jordi')->first();

        if ($admin === null) {
            $this->command->warn('No "jordi" user found; skipping super-admin seeding.');

            return;
        }

        Permission::query()->updateOrCreate(
            ['user_id' => $admin->id, 'page_name' => AccessControlService::SUPER_ADMIN_PAGE],
            ['can_access' => true]
        );
    }
}
