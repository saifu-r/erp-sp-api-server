<?php

namespace App\Http\Controllers\Api\Manufacture;

use App\Http\Controllers\Controller;
use App\Models\Manufacture\Item;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $query = Item::with('recipeLines.rawMaterial');

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $limit = (int) $request->query('limit', 20);
        $page = (int) $request->query('page', 1);
        $orderBy = strtolower($request->query('orderBy', 'desc')) === 'asc' ? 'asc' : 'desc';

        $total = $query->count();
        $data = $query->orderBy('created_at', $orderBy)->skip(($page - 1) * $limit)->take($limit)->get();

        return response()->json(['data' => $data, 'total' => $total]);
    }

    /** Flat list — used by Production's "select item to produce" dropdown. */
    public function all()
    {
        return Item::with('recipeLines.rawMaterial')->where('status', 1)->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|integer|min:1',
            'status' => 'required|integer|in:1,2',
        ]);

        return response()->json(Item::create($data), 201);
    }

    public function update(Request $request, Item $item)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|integer|min:1',
            'status' => 'required|integer|in:1,2',
        ]);

        $item->update($data);
        return $item;
    }

    public function destroy(Item $item)
    {
        $item->delete();
        return response()->json(['message' => 'Deleted']);
    }

    /** Replace the item's optional recipe hint entirely — simplest way to manage a short list. */
    public function updateRecipe(Request $request, Item $item)
    {
        $data = $request->validate([
            'lines' => 'array',
            'lines.*.raw_material_id' => 'required|exists:raw_materials,id',
            'lines.*.quantity_per_unit' => 'required|numeric|min:0.0001',
        ]);

        $item->recipeLines()->delete();
        foreach ($data['lines'] ?? [] as $line) {
            $item->recipeLines()->create($line);
        }

        return $item->load('recipeLines.rawMaterial');
    }
}