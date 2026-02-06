<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Node;
use App\Models\Member;
use App\Helpers\ApiResponse;
use App\Helpers\JWTHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    /** Registro de usuarios */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'string|nullable|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,node_leader,member',
            'about' => 'string|nullable',
            'degree' => 'string|nullable|max:255',
            'postgraduate' => 'string|nullable|max:255',
            'expertise_area' => 'string|nullable|max:255',
            'research_work' => 'string|nullable|max:255',
            'profile_picture' => 'string|nullable|max:255',
            'social_media' => 'array|nullable',
        ]);

        // Decodificar contraseña base64
        $decodedPassword = base64_decode($request->password);

        // Crear usuario
        $user = User::create([
            'name'             => $request->name,
            'username'         => $request->username,
            'email'            => $request->email,
            'password'         => Hash::make($decodedPassword),
            'role'             => $request->role,
            'about'            => $request->about,
            'degree'           => $request->degree,
            'postgraduate'     => $request->postgraduate,
            'expertise_area'   => $request->expertise_area,
            'research_work'    => $request->research_work,
            'profile_picture'  => $request->profile_picture,
            'social_media'     => $request->social_media,
            'status'           => 'activo',
        ]);

        // Asignar rol
        $role = Role::where('name', $request->role)->first();
        if ($role) {
            $user->assignRole($role);
        } else {
            return ApiResponse::error('Role not found', 400);
        }

        return ApiResponse::created('Usuario registrado con éxito', $user);
    }



    /** Login */
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]);

            $user = User::where('email', $request->email)->first();

            Log::info('Intentando login', [
                'email' => $request->email,
                'usuario_encontrado' => $user ? 'sí' : 'no',
                'password_recibido_es_base64' => base64_encode(base64_decode($request->password, true)) === $request->password ? 'sí' : 'no',
            ]);

            // Decodificar la contraseña base64 antes de validarla
            $decodedPassword = base64_decode($request->password, true);
            
            Log::info('Decodificación base64', [
                'password_encoded_length' => strlen($request->password),
                'password_decoded_length' => $decodedPassword ? strlen($decodedPassword) : 0,
                'password_decoded_value' => $decodedPassword ? substr($decodedPassword, 0, 5) . '***' : 'NULL',
            ]);

            if (!$decodedPassword) {
                Log::warning('Base64 decode falló', ['email' => $request->email]);
                return ApiResponse::unauthenticated('Invalid credentials (decode failed)', 401);
            }

            if (!$user) {
                Log::warning('Usuario no encontrado', ['email' => $request->email]);
                return ApiResponse::unauthenticated('Invalid credentials', 401);
            }

            Log::info('Comparando contraseñas', [
                'user_password_hash_type' => substr($user->password, 0, 4),
                'user_id' => $user->id,
            ]);

            if (!Hash::check($decodedPassword, $user->password)) {
                Log::warning('Contraseña incorrecta', ['user_id' => $user->id, 'email' => $user->email]);
                return ApiResponse::unauthenticated('Invalid credentials', 401);
            }

            // Si la contraseña usa $2b$, rehashearla a $2y$
            if (strpos($user->password, '$2b$') === 0) {
                Log::info('Rehashando contraseña de usuario', ['user_id' => $user->id, 'email' => $user->email]);
                $user->password = Hash::make($decodedPassword);
                $user->save();
            }

            Log::info('Usuario encontrado, generando token', ['user_id' => $user->id, 'email' => $user->email]);

            $token = JWTHandler::createToken($user, $request);

            Log::info('Token generado exitosamente', ['user_id' => $user->id]);

            // Obtener node_code si aplica
            $nodeCode = null;
            $node_id = null;

            if ($user->role === 'node_leader') {
                $nodeCode = Node::where('leader_id', $user->id)->value('code');
                Log::info('Node leader - nodo obtenido', ['node_code' => $nodeCode]);
            } elseif ($user->role === 'member') {
                $node_id = Member::where('user_id', $user->id)->value('node_id');
                if ($node_id) {
                    $nodeCode = Node::where('id', $node_id)->value('code');
                }
                Log::info('Miembro - nodo obtenido', ['node_id' => $node_id, 'node_code' => $nodeCode]);
            }

            return ApiResponse::success('Login successful', [
                'token' => $token,
                'role' => $user->role,
                'node_id' => $nodeCode
            ]);
        } catch (\Exception $e) {
            Log::error('Error en login:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return ApiResponse::error('Error en el servidor: ' . $e->getMessage(), 500);
        }
    }



    /** Logout */
    public function logout(Request $request)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return ApiResponse::error('Token not provided', 400);
        }

        JWTHandler::invalidateToken($token);

        return ApiResponse::success('Logged out successfully');
    }

    /** Logout de todas las sesiones */
    public function logoutAll(Request $request)
    {
        JWTHandler::invalidateAllSessions($request->user->sub);

        return ApiResponse::success('All sessions logged out');
    }

    /** Refresh token */
    public function refresh(Request $request)
    {
        try {
            // Obtener el usuario autenticado desde el middleware
            $user = $request->user();

            // Crear nuevo token
            $newToken = JWTHandler::createToken($user, $request);

            return ApiResponse::success('Token refreshed successfully.', [
                'token' => $newToken,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error refreshing token: ' . $e->getMessage());
            return ApiResponse::error('Could not refresh token.', 500);
        }
    }
}
