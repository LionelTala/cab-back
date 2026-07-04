<?php

namespace App\Events;

use App\Models\Candidature;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CandidatureRecue implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $candidature;
    public $count;

    public function __construct(Candidature $candidature, $count)
    {
        $this->candidature = $candidature;
        $this->count = $count;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('admin-candidatures'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'candidature.recue';
    }
}
