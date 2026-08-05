<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;
use App\Services\AccessControlService;

class ClientPolicy
{
    public function delete(User $user, Client $client): bool
    {
        return app(AccessControlService::class)->canAccessPage($user, 'clients.php');
    }
}
