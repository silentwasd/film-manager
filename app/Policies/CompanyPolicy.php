<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function update(User $user, Company $company): bool
    {
        return $company->author_id == $user->id || $user->role == UserRole::Admin;
    }

    public function delete(User $user, Company $company): bool
    {
        return ($company->author_id == $user->id && $company->films()->count() == 0) ||
               $user->role == UserRole::Admin;
    }
}
