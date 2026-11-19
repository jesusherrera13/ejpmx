<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\User;
use Spatie\Permission\Models\Role;

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
        // Asignar roles solo lo puede hacer un administrador (/register es público)
        abort_unless($request->user()->isAdmin(), 403);

        $validated = $request->validate([
            'name' => 'required|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required',
            'role_id' => 'nullable|integer|exists:roles,id',
        ]);

        $user = User::create($validated);

        if (!empty($validated['role_id'])) {
            $user->assignRole(Role::find($validated['role_id']));
        }

        return response()->json($this->withRoles($user));
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
    public function update(Request $request, User $user)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $validated = $request->validate([
            'name' => 'required|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string',
            'role_id' => 'nullable|integer|exists:roles,id',
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        if (!empty($validated['password'])) {
            $user->password = $validated['password'];
        }
        $user->save();

        if ($request->has('role_id')) {
            $user->syncRoles($validated['role_id'] ? [Role::find($validated['role_id'])] : []);
        }

        return response()->json($this->withRoles($user));
    }

    // Misma forma que devuelve index(): usuario con roles y permisos, sin pivots
    private function withRoles(User $user): User
    {
        $user->load(['roles:id,name', 'roles.permissions:id,name']);
        $user->roles->makeHidden('pivot');
        $user->roles->each(fn ($role) => $role->permissions->makeHidden('pivot'));

        return $user->setVisible(['id', 'name', 'email', 'roles']);
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
