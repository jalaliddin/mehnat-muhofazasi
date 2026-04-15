<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'operator' && $user->organization_id) {
            $orgs = Organization::where('id', $user->organization_id)->get();
        } else {
            $orgs = Organization::all();
        }

        return response()->json($orgs);
    }

    public function show(Request $request, $id)
    {
        $org = Organization::findOrFail($id);
        $this->authorizeOrg($request, $org->id);

        return response()->json($org);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'code'    => 'required|string|unique:organizations,code',
            'type'    => 'required|in:central,branch',
            'address' => 'nullable|string',
            'phone'   => 'nullable|string',
        ]);

        $org = Organization::create($data);

        return response()->json($org, 201);
    }

    public function update(Request $request, $id)
    {
        $org = Organization::findOrFail($id);

        $data = $request->validate([
            'name'    => 'sometimes|string|max:255',
            'code'    => 'sometimes|string|unique:organizations,code,' . $id,
            'type'    => 'sometimes|in:central,branch',
            'address' => 'nullable|string',
            'phone'   => 'nullable|string',
        ]);

        $org->update($data);

        return response()->json($org);
    }

    public function destroy($id)
    {
        $org = Organization::findOrFail($id);
        $org->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function employees(Request $request, $id)
    {
        $org = Organization::findOrFail($id);
        $this->authorizeOrg($request, $org->id);

        $employees = $org->employees()->with(['department', 'position'])->get();

        return response()->json($employees);
    }

    public function exams(Request $request, $id)
    {
        $org = Organization::findOrFail($id);
        $this->authorizeOrg($request, $org->id);

        $exams = $org->periodicExams()->with(['examType', 'order'])->get();

        return response()->json($exams);
    }

    private function authorizeOrg(Request $request, int $orgId): void
    {
        $user = $request->user();
        if ($user->role === 'operator' && $user->organization_id && $user->organization_id !== $orgId) {
            abort(403, 'Forbidden');
        }
    }
}
