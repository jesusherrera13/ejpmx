<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::select('id', 'name', 'email')
            ->with([
                'roles:id,name',                  // 1. Traemos los roles
                'roles.permissions:id,name'       // 2. Traemos los permisos anidados dentro del rol
            ])
            ->get()
            ->each(function ($user) {
                // 3. Ocultar el objeto 'pivot' de los roles
                $user->roles->makeHidden('pivot');

                // 4. Recorrer cada rol para ocultar el 'pivot' de sus permisos
                $user->roles->each(function ($role) {
                    $role->permissions->makeHidden('pivot');
                });
            });

        return response()->json($users);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required',
        ]);
        
        $team = User::create($validated);

        return response()->json($team);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function teams(User $user)
    {
        $user->load('teams');
        $teams = $user->teams;
        
        return response()->json($teams);
    }
}
