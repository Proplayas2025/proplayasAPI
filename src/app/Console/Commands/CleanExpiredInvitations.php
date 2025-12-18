<?php

namespace App\Console\Commands;

use App\Models\Invitation;
use Illuminate\Console\Command;

class CleanExpiredInvitations extends Command
{
    protected $signature = 'invitations:clean';
    protected $description = 'Eliminar invitaciones expiradas o no aceptadas después de 1 hora';

    public function handle()
    {
        $deleted = Invitation::where('status', 'pendiente')
            ->where('sent_date', '<', now()->subHour())
            ->delete();

        $this->info("Se eliminaron {$deleted} invitaciones expiradas.");
        
        return 0;
    }
}
