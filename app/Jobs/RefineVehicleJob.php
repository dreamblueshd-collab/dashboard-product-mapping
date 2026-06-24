<?php

namespace App\Jobs;

use App\Models\Vehicle;
use App\Services\AiRefinementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefineVehicleJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 240;
    public int $tries = 2;

    public function __construct(public int $vehicleId) {}

    public function handle(AiRefinementService $ai): void
    {
        $vehicle = Vehicle::find($this->vehicleId);
        if (! $vehicle) {
            return;
        }

        $ai->refineVehicle($vehicle);
    }
}
