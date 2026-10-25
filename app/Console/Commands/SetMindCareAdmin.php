<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetMindCareAdmin extends Command
{
    protected $signature = 'mindcare:admin {email : Email address of an existing user} {--remove : Revoke admin access}';

    protected $description = 'Grant or revoke access to aggregate MindCare administration pages';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No account exists for that email address.');

            return self::FAILURE;
        }

        $isAdmin = ! $this->option('remove');
        $user->forceFill(['is_admin' => $isAdmin])->save();

        $this->info($isAdmin ? 'Admin access granted.' : 'Admin access revoked.');

        return self::SUCCESS;
    }
}
