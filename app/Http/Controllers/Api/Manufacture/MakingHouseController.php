<?php

namespace App\Http\Controllers\Api\Manufacture;

use App\Http\Controllers\Controller;
use App\Models\Manufacture\MakingHouse;
use Illuminate\Http\Request;

class MakingHouseController extends Controller
{
    public function index(Request $request)
    {
        $query = MakingHouse::query();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $limit = (int) $request->query('limit', 20);
        $page = (int) $request->query('page', 1);
        $orderBy = strtolower($request->query('orderBy', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortKey = in_array($request->query('sortKey'), ['name', 'created_at']) ? $request->query('sortKey') : 'created_at';

        $total = $query->count();
        $data = $query->orderBy($sortKey, $orderBy)->skip(($page - 1) * $limit)->take($limit)->get();

        return response()->json(['data' => $data, 'total' => $total]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'status' => 'required|integer|in:1,2',
        ]);

        return response()->json(MakingHouse::create($data), 201);
    }

    public function update(Request $request, MakingHouse $makingHouse)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'status' => 'required|integer|in:1,2',
        ]);

        $makingHouse->update($data);
        return $makingHouse;
    }

    public function destroy(MakingHouse $makingHouse)
    {
        $makingHouse->delete(); // soft delete — sets deleted_at, row stays
        return response()->json(['message' => 'Deleted']);
    }
}