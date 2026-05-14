<?php

namespace App\Console\Commands;

use App\Domain\Leads\Enums\UserRole;
use App\Models\User;
use App\Models\UserLeadScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateUser extends Command
{
    protected $signature = 'lodgely:user:create
        {--name=    : Display name}
        {--email=   : Login email}
        {--password= : Password (auto-generated if omitted)}
        {--role=operator : operator | client}
        {--client=*  : One or more client_name scopes (client role only)}';

    protected $description = 'Create a lodgely user (operator or scoped client).';

    public function handle(): int
    {
        $name     = $this->option('name')     ?: $this->ask('Name');
        $email    = $this->option('email')    ?: $this->ask('Email');
        $roleRaw  = strtolower((string) ($this->option('role') ?: 'operator'));
        $role     = UserRole::tryFrom($roleRaw) ?? UserRole::Operator;
        $password = $this->option('password') ?: Str::password(16);

        if (User::where('email', $email)->exists()) {
            $this->error("A user with email {$email} already exists.");
            return self::FAILURE;
        }

        $user = User::create([
            'name'      => $name,
            'email'     => $email,
            'password'  => Hash::make($password),
            'role'      => $role->value,
            'is_active' => true,
        ]);

        if ($role === UserRole::Client) {
            $scopes = $this->option('client') ?: [];
            if (empty($scopes)) {
                $scopes = array_filter(array_map('trim', explode(',', (string) $this->ask('Client name scopes (comma-separated)'))));
            }
            foreach ($scopes as $scope) {
                if ($scope === '') continue;
                UserLeadScope::firstOrCreate(['user_id' => $user->id, 'client_name' => $scope]);
            }
        }

        $this->info("Created {$role->value} user #{$user->id} <{$user->email}>.");
        if (! $this->option('password')) {
            $this->line("Generated password: <fg=yellow>{$password}</>");
            $this->line('Store this somewhere safe — lodgely does not save it in cleartext.');
        }

        return self::SUCCESS;
    }
}
