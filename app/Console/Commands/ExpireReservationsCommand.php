<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reservations:expire')]
#[Description('Expire reservations that have passed their ready window')]
class ExpireReservationsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ReservationService $service)
    {
        $this->info('Starting reservation expiration check...');
        $count = $service->expireReservations();
        $this->info("Successfully expired {$count} reservations.");
    }
}
