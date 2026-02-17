<?php

namespace App\Events;

use App\Models\CrimeIncident;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class CrimeIncidentUpdated implements ShouldBroadcast
{
    use SerializesModels;

    public $incident;
    public $eventType; // 'created' or 'updated'

    public function __construct(CrimeIncident $incident, string $eventType = 'updated')
    {
        $this->incident = $incident;
        $this->eventType = $eventType;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('crime-incidents');
    }

    public function broadcastAs(): string
    {
        return 'incident.' . $this->eventType;
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->incident->id,
            'latitude' => (float) $this->incident->latitude,
            'longitude' => (float) $this->incident->longitude,
            'incident_date' => $this->incident->incident_date?->format('Y-m-d'),
            'incident_title' => $this->incident->incident_title,
            'status' => $this->incident->status,
            'clearance_status' => $this->incident->clearance_status,
            'crime_category_id' => $this->incident->crime_category_id,
            'barangay_id' => $this->incident->barangay_id,
            'location' => $this->incident->barangay?->barangay_name ?? 'Unknown Barangay',
            'category_name' => $this->incident->category?->category_name ?? 'Unknown',
            'color_code' => $this->incident->category?->color_code ?? '#274d4c',
            'icon' => $this->incident->category?->icon ?? 'fa-exclamation-circle',
            'event_type' => $this->eventType,
        ];
    }

    public function broadcastWhen(): bool
    {
        // Only broadcast if incident has valid coordinates
        return !empty($this->incident->latitude) && !empty($this->incident->longitude);
    }
}
