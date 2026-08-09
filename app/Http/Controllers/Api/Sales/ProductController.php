<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('productItems.item');

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

    public function all() { return Product::with('productItems.item')->where('status', 1)->orderBy('name')->get(); }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'sale_price' => 'required|numeric|min:0',
            'status' => 'required|integer|in:1,2',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity_required' => 'required|numeric|min:0.001',
        ]);

        $product = Product::create([
            'name' => $data['name'], 'sale_price' => $data['sale_price'], 'status' => $data['status'],
        ]);

        foreach ($data['items'] as $line) {
            $product->productItems()->create($line);
        }

        return response()->json($product->load('productItems.item'), 201);
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'sale_price' => 'required|numeric|min:0',
            'status' => 'required|integer|in:1,2',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity_required' => 'required|numeric|min:0.001',
        ]);

        $product->update(['name' => $data['name'], 'sale_price' => $data['sale_price'], 'status' => $data['status']]);

        $product->productItems()->delete();
        foreach ($data['items'] as $line) {
            $product->productItems()->create($line);
        }

        return $product->load('productItems.item');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(['message' => 'Deleted']);
    }
}