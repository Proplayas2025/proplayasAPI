<?php

namespace App\Helpers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\Session;

class JWTHandler
{
    public static function createToken($user, $request = null, $isInvitation = false)
    {
        try {
            $secret = env('JWT_SECRET');
            if (!$secret) {
                throw new \Exception('JWT_SECRET no está configurado en .env');
            }

            $ttl = env('JWT_TTL', 86400); // Default 24 horas en segundos

            $payload = [
                'iss' => env('APP_URL', 'http://localhost'),
                'iat' => time(),
                'exp' => time() + $ttl
            ];

            // Verificar si es un usuario registrado o una invitación
            if (isset($user->id) && !$isInvitation) {
                $payload['sub'] = $user->id;
                $payload['email'] = $user->email;
                $payload['role'] = $user->role;

                // Generar el token
                $token = JWT::encode($payload, $secret, 'HS256');

                // Si es una sesión real, intenta guardarla en BD (pero no es crítica)
                if ($request) {
                    try {
                        // 🔹 INVALIDAR SESIONES PREVIAS DE MISMO USUARIO + MISMA IP Y USER AGENT
                        Session::where('user_id', $user->id)
                            ->where('ip_address', $request->ip())
                            ->where('user_agent', $request->header('User-Agent'))
                            ->delete();

                        // 🔹 CREAR NUEVA SESIÓN
                        Session::create([
                            'user_id' => $user->id,
                            'token' => $token,
                            'ip_address' => $request->ip(),
                            'user_agent' => $request->header('User-Agent')
                        ]);
                    } catch (\Exception $sessionError) {
                        // Log el error de sesión pero continúa (no es crítico)
                        \Log::warning('Error al guardar sesión en BD, pero el token se generó correctamente', [
                            'user_id' => $user->id,
                            'error' => $sessionError->getMessage()
                        ]);
                    }
                }

                return $token;
                
            // Si es un token de invitacion
            } elseif ($isInvitation) {
                $payload['name'] = $user->name ?? null;
                $payload['email'] = $user->email ?? null;
                $payload['role_type'] = $user->role_type ?? null;

                // Solo agregar `node_type` si es un `node_leader`
                if ($user->role_type === 'node_leader' && isset($user->node_type)) {
                    $payload['node_type'] = $user->node_type;
                }
                
                // Solo agregar `node_id` si es un `member`
                if ($user->role_type === 'member' && isset($user->node_id)) {
                    $payload['node_id'] = $user->node_id;
                }

                return JWT::encode($payload, $secret, 'HS256');
            }

            throw new \Exception("Invalid data provided for token generation. Missing user->id or incorrect isInvitation flag.");
        } catch (\Exception $e) {
            \Log::error('Error en JWTHandler::createToken', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }
    }

    public static function decodeToken($token)
    {
        return JWT::decode($token, new Key(env('JWT_SECRET'), 'HS256'));
    }

    public static function invalidateToken($token)
    {
        // Elimina la sesión de la BD
        return Session::where('token', $token)->delete();
    }

    public static function invalidateAllSessions($userId)
    {
        // Elimina todas las sesiones activas del usuario
        return Session::where('user_id', $userId)->delete();
    }
}
