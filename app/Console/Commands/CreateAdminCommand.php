<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Mail\AdminWelcomeEmail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdminCommand extends Command
{
    protected $signature = 'app:create-admin
                            {--email= : Email address of the admin}
                            {--firstname= : First name}
                            {--lastname= : Last name}
                            {--password= : Password (min 8 chars)}';

    protected $description = 'Create a new administrator account interactively or via options';

    public function handle(): int
    {
        $this->info('Creating a new admin account...');
        $this->newLine();

        $email = $this->option('email') ?? text(
            label: 'Email address',
            placeholder: 'admin@example.com',
            required: true,
            validate: fn (string $value) => match (true) {
                !filter_var($value, FILTER_VALIDATE_EMAIL) => 'Please enter a valid email address.',
                User::withTrashed()->where('email', $value)->exists() => 'This email is already taken.',
                default => null,
            },
        );

        if (User::withTrashed()->where('email', $email)->exists()) {
            $this->error("A user with email [{$email}] already exists.");

            return self::FAILURE;
        }

        $firstname = $this->option('firstname') ?? text(
            label: 'First name',
            placeholder: 'John',
            required: true,
        );

        $lastname = $this->option('lastname') ?? text(
            label: 'Last name',
            placeholder: 'Doe',
            required: true,
        );

        $plainPassword = $this->option('password') ?? password(
            label: 'Password',
            placeholder: 'min. 8 characters',
            required: true,
            validate: fn (string $value) => strlen($value) < 8
                ? 'Password must be at least 8 characters.'
                : null,
        );

        $validator = Validator::make(
            ['password' => $plainPassword],
            ['password' => ['required', 'string', 'min:8']],
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first('password'));

            return self::FAILURE;
        }

        try {
            $user = new User;
            $user->fill([
                'firstname' => $firstname,
                'lastname' => $lastname,
                'email' => $email,
                'password' => $plainPassword,
            ]);
            $user->forceFill([
                'role' => UserRole::ADMIN,
                'type' => null,
                'email_verified_at' => now(),
                'is_active' => true,
                // Admin chooses their own password in this command — no forced change needed.
                // Email MFA is pre-enabled so the user can pass MFA on first login
                // (Filament 4 isRequired:true would otherwise create a setup-loop).
                'must_change_password_at' => null,
                'has_email_authentication' => true,
            ]);
            $user->save();

            Mail::to($user->email, $user->firstname)->queue(new AdminWelcomeEmail($user));

            $this->newLine();
            $this->info('  Admin account created successfully.');
            $this->line("  ID    : {$user->id}");
            $this->line("  Email : {$user->email}");
            $this->line("  Name  : {$user->firstname} {$user->lastname}");
            $this->newLine();
            $this->comment('A welcome email has been queued.');
        } catch (\Throwable $e) {
            $this->error("Failed to create admin: {$e->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
