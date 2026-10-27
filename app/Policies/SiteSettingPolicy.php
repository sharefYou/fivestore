<?php

namespace App\Policies;

use App\Models\SiteSetting;
use App\Models\User;

class SiteSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, SiteSetting $setting): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, SiteSetting $setting): bool
    {
        return $user->is_admin;
    }
}
