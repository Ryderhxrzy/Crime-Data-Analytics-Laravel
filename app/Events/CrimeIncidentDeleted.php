<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class CrimeIncidentDeleted implements ShouldBroadcast
{
    public int $incidentId;

    public function __construct(int $incidentId)
    {
        $this->incidentId = $incidentId;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('crime-incidents');
    }

    public function broadcastAs(): string
    {
        return 'incident.deleted';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->incidentId];
    }
}
