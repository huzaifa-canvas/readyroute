<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Register a dispatcher company.
     *
     * Public registration only ever produces a dispatcher, never an
     * administrator: platform admins are created by another admin. The role is
     * set here rather than taken from the form, and a posted role is rejected
     * outright so a tampered request fails loudly instead of being quietly
     * ignored.
     *
     * The new company starts with no subscription, which leaves their panel
     * readable but read-only until they choose a plan.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
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

            // Nothing may ask for a role here. Prohibited rather than ignored,
            // so an attempt shows up as a validation failure.
            'role'    => ['prohibited'],
            'role_id' => ['prohibited'],
        ], [
            'role.prohibited'    => 'The account type cannot be chosen during registration.',
            'role_id.prohibited' => 'The account type cannot be chosen during registration.',
        ])->validate();

        return User::create([
            'name'     => $input['name'],
            'email'    => $input['email'],
            'password' => Hash::make($input['password']),

            // Fixed, never read from the request.
            'role'          => 'dispatcher',
            'dispatcher_id' => null,
            'status'        => 'active',

            // No plan yet: they can sign in and look around, and the panel
            // unlocks once they subscribe.
            'subscription_status' => 'none',
        ]);
    }
}
