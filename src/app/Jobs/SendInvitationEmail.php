<?php

namespace App\Jobs;

use App\Services\MailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendInvitationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $email;
    public $subject;
    public $body;

    /**
     * Create a new job instance.
     */
    public function __construct(string $email, string $subject, string $body)
    {
        $this->email = $email;
        $this->subject = $subject;
        $this->body = $body;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            MailService::sendMail($this->email, $this->subject, $this->body);
            Log::info("Email enviado exitosamente a: {$this->email}");
        } catch (\Exception $e) {
            Log::error("Error al enviar email a {$this->email}: " . $e->getMessage());
            throw $e; // Permite reintentos automáticos
        }
    }

    /**
     * Número de intentos del job
     */
    public $tries = 3;

    /**
     * Tiempo máximo de ejecución (segundos)
     */
    public $timeout = 30;
}
