<?php

namespace App\Http\Controllers\Api\Manufacture;

use App\Http\Controllers\Controller;
use App\Models\Manufacture\RawMaterial;
use Illuminate\Http\Request;

class RawMaterialController extends Controller
{
    public function index(Request $request)
    {
        $query = RawMaterial::query();

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $limit = (int) $request->query('limit', 20);
        $page = (int) $request->query('page', 1);
        $orderBy = strtolower($request->query('orderBy', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortKey = in_array($request->query('sortKey'), ['name', 'created_at']) ? $request->query('sortKey') : 'created_at';

        $total = $query->count();
        $materials = $query->orderBy($sortKey, $orderBy)->skip(($page - 1) * $limit)->take($limit)->get();

        // Attach company stock to each row for display in the grid
        $data = $materials->map(function ($m) {
            return [
                'id' => $m->id,
                'name' => $m->name,
                'unit' => $m->unit,
                'status' => $m->status,
                'company_stock' => $m->companyStock(),
            ];
        });

        return response()->json(['data' => $data, 'total' => $total]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|integer|min:1',
            'status' => 'required|integer|in:1,2',
        ]);

        return response()->json(RawMaterial::create($data), 201);
    }

    public function update(Request $request, RawMaterial $rawMaterial)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:20',
            'status' => 'required|integer|in:1,2',
        ]);

        $rawMaterial->update($data);
        return $rawMaterial;
    }

    public function destroy(RawMaterial $rawMaterial)
    {
        $rawMaterial->delete();
        return response()->json(['message' => 'Deleted']);
    }
}