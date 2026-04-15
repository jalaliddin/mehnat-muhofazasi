<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Order::with('organization');

        if ($user->role === 'operator' && $user->organization_id) {
            $query->where('organization_id', $user->organization_id);
        }

        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        return response()->json($query->orderByDesc('order_date')->get());
    }

    public function show($id)
    {
        return response()->json(Order::with('organization')->findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'order_number'    => 'required|string|max:100',
            'order_date'      => 'required|date',
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'file'            => 'nullable|file|mimes:pdf,doc,docx|max:10240',
        ]);

        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('orders', 'public');
            $data['file_path'] = $path;
        }

        unset($data['file']);

        $order = Order::create($data);

        return response()->json($order->load('organization'), 201);
    }

    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        $data = $request->validate([
            'organization_id' => 'sometimes|exists:organizations,id',
            'order_number'    => 'sometimes|string|max:100',
            'order_date'      => 'sometimes|date',
            'title'           => 'sometimes|string|max:255',
            'description'     => 'nullable|string',
            'file'            => 'nullable|file|mimes:pdf,doc,docx|max:10240',
        ]);

        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('orders', 'public');
            $data['file_path'] = $path;
        }

        unset($data['file']);

        $order->update($data);

        return response()->json($order->load('organization'));
    }

    public function destroy($id)
    {
        Order::findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
