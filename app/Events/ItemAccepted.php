<?php

namespace App\Events;

use App\Models\Accessory;
use App\Models\Actionlog;
use App\Models\Contracts\Acceptable;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ItemAccepted
{
    use Dispatchable, SerializesModels;

    public $item;
    public $acceptedBy;
    public $signature;
    
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Acceptable $item, User $acceptedBy, string $signature)
    {
        $this->item       = $item;
        $this->acceptedBy = $acceptedBy;
        $this->signature  = $signature;
    }
}
