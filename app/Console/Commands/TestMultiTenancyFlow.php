<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AgencyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class TestMultiTenancyFlow extends Command
{
    protected $signature = 'test:tenancy {type : agency or bailleur} {email : email of the user}';

    protected $description = 'Simulate Spark payment and promote user to Agency or Bailleur';

    public function handle(AgencyService $service)
    {
        $type = $this->argument('type');
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->info('Creating new test user...');
            $user = User::create([
                'firstname' => 'Test',
                'lastname' => ucfirst($type),
                'email' => $email,
                'password' => Hash::make('password'),
                'role' => \App\Enums\UserRole::CUSTOMER,
                'email_verified_at' => now(),
            ]);
        }

        $this->info("Current status: Role: {$user->role->value}, Type: ".($user->type !== null ? $user->type->value : 'none'));

        if ($type === 'agency') {
            $name = $this->ask('What is the agency name?', 'Immo Pro SARL');
            $service->promoteToAgency($user, $name);
            $this->success('User promoted to AGENCY owner!');
            $this->info('Login at: /agency');
        } else {
            $service->promoteToBailleur($user);
            $this->success('User promoted to BAILLEUR!');
            $this->info('Login at: /bailleur');
        }

        $user->refresh();
        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $user->id],
                ['Role', $user->role->value],
                ['Type', $user->type->value],
                ['Agency ID', $user->agency_id],
                ['Panel Access', $user->canAccessPanel(\Filament\Facades\Filament::getPanel($type)) ? '✅ ALLOWED' : '❌ DENIED'],
            ]
        );
    }

    private function success($msg)
    {
        $this->line("<info>SUCCESS:</info> $msg");
    }
}
