<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with('organization')->get();

        return response()->json($users);
    }

    public function show($id)
    {
        return response()->json(User::with('organization')->findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'username'        => 'required|string|unique:users,username',
            'email'           => 'required|email|unique:users,email',
            'password'        => 'required|string|min:6',
            'role'            => 'required|in:admin,operator',
            'organization_id' => 'nullable|exists:organizations,id',
            'is_active'       => 'boolean',
        ]);

        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);

        return response()->json($user->load('organization'), 201);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name'            => 'sometimes|string|max:255',
            'username'        => 'sometimes|string|unique:users,username,' . $id,
            'email'           => 'sometimes|email|unique:users,email,' . $id,
            'password'        => 'nullable|string|min:6',
            'role'            => 'sometimes|in:admin,operator',
            'organization_id' => 'nullable|exists:organizations,id',
            'is_active'       => 'boolean',
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json($user->load('organization'));
    }

    public function destroy($id)
    {
        User::findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
