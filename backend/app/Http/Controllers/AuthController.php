<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request) {

        $user = User::where('email', $request['email'])->first();
        if($user) return response()->json(['success' => false, 'message' => 'El email `'.$request['email'].'` ya está en uso'], 200);

        // $request['name'] = ucwords(mb_strtolower($request['email']));
        
        $fields = $request->validate([
            'name' => 'required|string',
            'email' => 'required|string|unique:users,email',
            'password' => 'required|string|confirmed',
        ]);

        $user = User::create([
            'name' => $fields['name'],
            'email' => $fields['email'],
            'password' => bcrypt($fields['password'])
        ]);

        $user->assignRole('invitado');

        return response($user, 201);
    }

    public function login(Request $request) {
        $fields = $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        // 1. Buscar al usuario con los campos específicos y sus relaciones
        $user = User::select('id', 'name', 'email', 'password')
            ->with([
                'roles:id,name',
                'roles.permissions:id,name'
            ])
            ->where('email', $fields['email'])
            ->first();

        // 2. Validar credenciales
        if (!$user || !Hash::check($fields['password'], $user->password)) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        // 3. Ocultar los datos 'pivot' de la respuesta
        $user->roles->makeHidden('pivot');
        $user->roles->each(function ($role) {
            $role->permissions->makeHidden('pivot');
        });

        // 4. Generar el token (Asumiendo que usas Laravel Sanctum)
        $user->token = $user->createToken('auth_token')->plainTextToken;

        return response()->json($user);
    }

    public function logout(Request $request) {

        auth()->user()->tokens()->delete();

        return response(['message' => 'Logged out'], 205);
    }
}
