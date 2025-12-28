<?php

namespace App\Observers;

use App\Models\User;
use Spatie\Permission\Models\Role;

class UserObserver
{
    /**
     * Assign default role 'peminjam' to newly registered users
     * if they don't have any role yet.
     */
    public function created(User $user): void
    {
        if ($user->roles()->exists()) {
            return;
        }

        $role = Role::firstOrCreate([
            'name' => 'peminjam',
            'guard_name' => 'web',
        ]);

        $user->assignRole($role);
    }
}

