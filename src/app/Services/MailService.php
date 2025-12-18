<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Illuminate\Support\Facades\Log;

class MailService
{
    public static function sendMail($to, $subject, $body)
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = env('MAIL_HOST');
            $mail->Port = env('MAIL_PORT');
            
            // Autenticación solo si hay credenciales
            if (env('MAIL_USERNAME') && env('MAIL_PASSWORD')) {
                $mail->SMTPAuth = true;
                $mail->Username = env('MAIL_USERNAME');
                $mail->Password = env('MAIL_PASSWORD');
                
                if (env('MAIL_ENCRYPTION')) {
                    $mail->SMTPSecure = env('MAIL_ENCRYPTION');
                }
            } else {
                $mail->SMTPAuth = false;
            }
            
            $mail->setFrom(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            #Log::error($e->getMessage());
            Log::error("Error al enviar correo: {$mail->ErrorInfo}");
            return false;
        }
    }
}