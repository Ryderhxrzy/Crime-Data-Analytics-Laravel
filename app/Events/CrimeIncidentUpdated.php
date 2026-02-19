<?php

namespace App\Events;

use App\Models\CrimeIncident;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CrimeIncidentUpdated implements ShouldBroadcast
{
    use SerializesModels;

    public $incident;
    public $eventType; // 'created' or 'updated'

    public function __construct(CrimeIncident $incident, string $eventType = 'updated')
    {
        $this->incident = $incident;
        $this->eventType = $eventType;
        
        // Debug logging
        Log::info('🔌 CrimeIncidentUpdated event created', [
            'event_type' => $eventType,
            'incident_id' => $incident->id,
            'incident_title' => $incident->incident_title,
            'latitude' => $incident->latitude,
            'longitude' => $incident->longitude,
            'broadcast_when' => $this->broadcastWhen()
        ]);
    }

    public function broadcastOn(): Channel
    {
        Log::info('📡 Setting up broadcast channel: crime-incidents');
        return new Channel('crime-incidents');
    }

    public function broadcastAs(): string
    {
        $eventName = 'incident.' . $this->eventType;
        Log::info('📢 Broadcast event name: ' . $eventName);
        return $eventName;
    }

    public function broadcastWith(): array
    {
        $data = [
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
        
        Log::info('📦 Broadcast data prepared', $data);
        return $data;
    }

    public function broadcastWhen(): bool
    {
        // Always broadcast for debugging - remove coordinate restriction
        $shouldBroadcast = true;
        
        Log::info('🔍 broadcastWhen check', [
            'should_broadcast' => $shouldBroadcast,
            'incident_id' => $this->incident->id,
            'latitude' => $this->incident->latitude,
            'longitude' => $this->incident->longitude
        ]);
        
        return $shouldBroadcast;
        
        // Original code (commented out for debugging):
        // return !empty($this->incident->latitude) && !empty($this->incident->longitude);
    }
}
