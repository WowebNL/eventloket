<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-admin-user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interactively create a new admin user with auto-generated secure password';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Creating a new Admin user...');
        $this->newLine();

        // Get user input
        $firstName = $this->ask('First name');
        $lastName = $this->ask('Last name');
        $email = $this->ask('Email address');
        $phone = $this->ask('Phone number (optional)', '');

        // Validate email doesn't already exist
        if (User::where('email', $email)->exists()) {
            $this->error("A user with email '{$email}' already exists!");

            return Command::FAILURE;
        }

        // Generate secure password
        $password = Str::password();

        // Confirm details
        $this->newLine();
        $this->info('User details:');
        $this->table(
            ['Field', 'Value'],
            [
                ['First Name', $firstName],
                ['Last Name', $lastName],
                ['Email', $email],
                ['Phone', $phone ?: 'Not provided'],
                ['Role', 'Admin'],
            ]
        );

        if (! $this->confirm('Create this admin user?')) {
            $this->info('User creation cancelled.');

            return Command::SUCCESS;
        }

        try {
            // Create the user
            $user = User::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'name' => "{$firstName} {$lastName}",
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make($password),
                'role' => Role::Admin,
                'email_verified_at' => now(), // Auto-verify admin users
            ]);

            $this->newLine();
            $this->info('✅ Admin user created successfully!');
            $this->newLine();

            // Display password once
            $this->warn('🔐 IMPORTANT: Save this password securely - it will only be shown once!');
            $this->newLine();
            $this->line("Email: {$email}");
            $this->line("Password: {$password}");
            $this->newLine();
            $this->warn('The user should change this password on first login.');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to create admin user: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
