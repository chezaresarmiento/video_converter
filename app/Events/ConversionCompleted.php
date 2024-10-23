<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class ConversionCompleted implements ShouldBroadcast
{
    use SerializesModels;

    public $userId;
    public $url;

    /**
     * Create a new event instance.
     *
     * @param int $userId
     * @param string $url
     */
    public function __construct($userId, $url)
    {
        $this->userId = $userId;
        $this->url = $url;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('conversions');
    }

    public function broadcastWith()
    {
        return [
            'url' => $this->url
        ];
    }
}
