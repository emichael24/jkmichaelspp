<?php

namespace App\Jobs;

use App\Services\NotificationDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(public string $deliveryId) {}

    public function handle(NotificationDeliveryService $service): void
    {
        $service->deliver($this->deliveryId);
    }
}
