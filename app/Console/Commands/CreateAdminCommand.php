<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdminCommand extends Command
{
    protected $signature = 'app:create-admin
                            {--email= : Admin email address}
                            {--firstname= : Admin first name}
                            {--lastname= : Admin last name}
                            {--password= : Admin password}';

    protected $description = 'Create a new admin user or promote an existing user to admin';

    public function handle(): int
    {
        $this->info('🔑 Creating admin user...');

        $email = $this->sanitize($this->option('email') ?? $this->ask('Email address'));
        $existing = User::where('email', $email)->first();

        if ($existing) {
            if ($existing->role === UserRole::ADMIN) {
                $this->warn("User {$email} is already an admin.");

                return self::SUCCESS;
            }

            if ($this->confirm("User {$email} exists as {$existing->role->value}. Promote to admin?", true)) {
                $existing->forceFill(['role' => UserRole::ADMIN])->save();
                $this->info("✅ User {$email} promoted to admin.");

                return self::SUCCESS;
            }

            return self::FAILURE;
        }

        $firstname = $this->sanitize($this->option('firstname') ?? $this->ask('First name'));
        $lastname = $this->sanitize($this->option('lastname') ?? $this->ask('Last name'));
        $password = $this->option('password');
        while (empty($password) || strlen((string) $password) < 8) {
            $password = $this->sanitize($this->ask('Password (min 8 characters)'));
            if (empty($password)) {
                $this->error('Le mot de passe ne peut pas être vide.');
            } elseif (strlen($password) < 8) {
                $this->error('Le mot de passe doit contenir au moins 8 caractères.');
                $password = null;
            }
        }

        $validator = Validator::make([
            'email' => $email,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'password' => $password,
        ], [
            'email' => ['required', 'email', 'unique:users,email'],
            'firstname' => ['required', 'string', 'min:2'],
            'lastname' => ['required', 'string', 'min:2'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User;
        $user->fill([
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'password' => $password,
        ]);
        $user->forceFill(['role' => UserRole::ADMIN]);
        $user->save();

        // Send a signed verification link (not OTP) — admins use the web link, not the mobile OTP flow.
        $user->notify(new \Illuminate\Auth\Notifications\VerifyEmail);

        $this->info("✅ Admin created: {$user->email} (ID: {$user->id})");
        $this->info(" A verification email has been sent to {$user->email}.");
        $this->line('   The admin will receive a welcome email automatically after verifying their address.');

        return self::SUCCESS;
    }

    /**
     * Strip invalid UTF-8 bytes and trim whitespace from input.
     * Docker exec -T can inject garbage bytes into interactive prompts.
     */
    private function sanitize(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        // Remove any non-UTF-8 bytes
        $clean = mb_convert_encoding($value, 'UTF-8', 'UTF-8');

        return trim($clean);
    }
}
