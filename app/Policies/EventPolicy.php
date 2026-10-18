<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function manage(User $user, Event $event): bool
    {
        return $event->user_id === $user->id;
    }
}
