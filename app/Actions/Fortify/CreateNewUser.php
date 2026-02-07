<?php

namespace App\Actions\Fortify;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Entitlements\Contracts\UsageMeterInterface;
use App\Services\Entitlements\EntitlementEnforcer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(
        private EntitlementEnforcer $entitlementEnforcer,
        private UsageMeterInterface $usageMeter
    ) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $tenant = Tenant::create([
                'name' => 'Personal',
            ]);

            $this->entitlementEnforcer->ensureCanInviteMember($tenant->id);
            $user->tenants()->attach($tenant->id, ['role' => 'owner']);
            $this->usageMeter->meter(
                tenantId: $tenant->id,
                meter: 'active_members',
                quantity: 1,
                context: [
                    'user_id' => $user->id,
                    'actor_user_id' => $user->id,
                    'source' => 'registration',
                ],
            );

            $this->entitlementEnforcer->ensureCanCreateProject($tenant->id);
            Project::create([
                'tenant_id' => $tenant->id,
                'name' => 'Personal',
            ]);

            return $user;
        });
    }
}
