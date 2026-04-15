<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Position;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Position::with('organization');

        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        if ($user->role === 'operator' && $user->organization_id) {
            $query->where('organization_id', $user->organization_id);
        }

        return response()->json($query->get());
    }

    public function show($id)
    {
        return response()->json(Position::with('organization')->findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'name'            => 'required|string|max:255',
        ]);

        $position = Position::create($data);

        return response()->json($position->load('organization'), 201);
    }

    public function update(Request $request, $id)
    {
        $position = Position::findOrFail($id);

        $data = $request->validate([
            'organization_id' => 'sometimes|exists:organizations,id',
            'name'            => 'sometimes|string|max:255',
        ]);

        $position->update($data);

        return response()->json($position->load('organization'));
    }

    public function destroy($id)
    {
        Position::findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
