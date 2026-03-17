<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Mail\AdminWelcomeEmail;
use App\Mail\AgencyWelcomeEmail;
use App\Mail\BailleurWelcomeEmail;
use App\Mail\WelcomeEmail;
use App\Models\User;
use App\Services\AgencyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CreateDemoUsersCommand extends Command
{
    protected $signature = 'app:create-test-users';

    protected $description = 'Create demo users (admin, agency, bailleur, client) with predefined credentials and send welcome emails';

    private const string PASSWORD = 'Password123!';

    private const array USERS = [
        [
            'email' => 'test-admin-nc@proton.me',
            'firstname' => 'Test',
            'lastname' => 'Admin',
            'role' => UserRole::ADMIN,
            'type' => null,
        ],
        [
            'email' => 'test-prof-nc@proton.me',
            'firstname' => 'Test',
            'lastname' => 'Agency',
            'role' => UserRole::AGENT,
            'type' => UserType::AGENCY,
            'agency_name' => 'Agence Test KeyHome',
        ],
        [
            'email' => 'test-student-nc@proton.me',
            'firstname' => 'Test',
            'lastname' => 'Bailleur',
            'role' => UserRole::AGENT,
            'type' => UserType::INDIVIDUAL,
            'is_bailleur' => true,
        ],
        [
            'email' => 'test-client-nc@proton.me',
            'firstname' => 'Test',
            'lastname' => 'Client',
            'role' => UserRole::CUSTOMER,
            'type' => null,
        ],
    ];

    public function handle(): int
    {
        $this->info('Creating test users...');

        $agencyService = app(AgencyService::class);
        $created = 0;
        $skipped = 0;

        foreach (self::USERS as $data) {
            $email = $data['email'];
            $existing = User::withTrashed()->where('email', $email)->first();

            if ($existing) {
                $this->warn(" {$email} already exists (ID: {$existing->id}). Skipping.");
                $skipped++;

                continue;
            }

            try {
                DB::transaction(function () use ($data, $agencyService, &$created): void {
                    $user = $this->createUser($data, $agencyService);
                    $this->verifyAndSendWelcome($user, $data);
                    $created++;
                    $this->info("  ✅ {$user->email} created (ID: {$user->id})");
                });
            } catch (\Throwable $e) {
                $this->error("  ❌ {$data['email']}: {$e->getMessage()}");

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info("Done. Created: {$created}, Skipped: {$skipped}.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createUser(array $data, AgencyService $agencyService): User
    {
        $user = new User;
        $user->fill([
            'firstname' => $data['firstname'],
            'lastname' => $data['lastname'],
            'email' => $data['email'],
            'password' => self::PASSWORD,
        ]);

        $isAgencyOrBailleur = !empty($data['is_bailleur']) || !empty($data['agency_name'] ?? null);
        $user->forceFill([
            'role' => $isAgencyOrBailleur ? UserRole::CUSTOMER : $data['role'],
            'type' => $isAgencyOrBailleur ? null : ($data['type'] ?? null),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        if ($data['role'] === UserRole::ADMIN) {
            $user->forceFill(['must_change_password_at' => now()]);
        }

        $user->save();

        if (!empty($data['is_bailleur'] ?? false)) {
            $agencyService->promoteToBailleur($user);
        } elseif (!empty($data['agency_name'] ?? null)) {
            $agencyService->promoteToAgency($user, $data['agency_name']);
        }

        return $user->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function verifyAndSendWelcome(User $user, array $data): void
    {
        $user->refresh();

        match ($user->role) {
            UserRole::ADMIN => Mail::to($user->email, $user->firstname)->queue(new AdminWelcomeEmail($user)),
            UserRole::AGENT => $user->type === UserType::AGENCY
                ? Mail::to($user->email, $user->firstname)->queue(new AgencyWelcomeEmail($user))
                : Mail::to($user->email, $user->firstname)->queue(new BailleurWelcomeEmail($user)),
            UserRole::CUSTOMER => Mail::to($user->email, $user->firstname)->queue(new WelcomeEmail($user)),
        };
    }
}
