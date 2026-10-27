<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Creates (or promotes) an administrator.
 *
 * Administrators are made here rather than by a seeder, so no environment
 * ever ends up with a known, committed password.
 */
class CreateAdminUser extends Command
{
    protected $signature = 'app:create-admin
                            {--email= : The administrator email address}
                            {--name= : The display name}';

    protected $description = 'Create an administrator, or grant admin access to an existing user';

    public function handle(): int
    {
        $email = $this->option('email') ?: text(
            label: 'Email address',
            required: true,
            validate: fn (string $value) => Validator::make(['email' => $value], [
                'email' => ['required', 'email', 'max:255'],
            ])->errors()->first('email'),
        );

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            if ($existing->is_admin) {
                $this->components->info("{$email} is already an administrator.");

                return self::SUCCESS;
            }

            if (! confirm("A user with {$email} already exists. Grant admin access?", default: false)) {
                return self::FAILURE;
            }

            $existing->forceFill(['is_admin' => true])->save();
            $this->components->info("{$email} can now sign in to /admin.");

            return self::SUCCESS;
        }

        $name = $this->option('name') ?: text(label: 'Display name', required: true);

        // Never echoed, never passed as an argument, never logged.
        $plain = password(
            label: 'Password',
            required: true,
            validate: fn (string $value) => Validator::make(['password' => $value], [
                'password' => ['required', Password::default()],
            ])->errors()->first('password'),
        );

        if (password(label: 'Confirm password', required: true) !== $plain) {
            $this->components->error('The passwords did not match.');

            return self::FAILURE;
        }

        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($plain),
            'email_verified_at' => now(),
            'is_admin' => true,
        ])->save();

        $this->components->info("Administrator {$user->email} created. Sign in at /admin.");

        return self::SUCCESS;
    }
}
