<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class ConversionFailed implements ShouldBroadcast
{
    use SerializesModels;

    public $userId;
    public $error;
    public $youtubeLink;

    /**
     * Create a new event instance.
     *
     * @param int $userId
     * @param string $error
     * @param string $youtubeLink
     */
    public function __construct($userId, $error, $youtubeLink = null)
    {
        $this->userId = $userId;
        $this->error = $error;
        $this->youtubeLink = $youtubeLink;
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
            'error' => $this->error,
            'youtubeLink' => $this->youtubeLink
        ];
    }
}