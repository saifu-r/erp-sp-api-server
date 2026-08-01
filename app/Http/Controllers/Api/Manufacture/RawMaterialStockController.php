<?php

namespace App\Http\Controllers\Api\Manufacture;

use App\Http\Controllers\Controller;
use App\Models\Manufacture\RawMaterial;
use Illuminate\Http\Request;

class RawMaterialStockController extends Controller
{
    /** Company-level stock for every raw material. */
    public function company()
    {
        $materials = RawMaterial::where('status', 1)->get();
        return $materials->map(fn($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'unit' => $m->unit,
            'stock' => $m->companyStock(),
        ]);
    }

    /** Stock for every raw material at one specific making house. */
    public function makingHouse(Request $request, int $makingHouseId)
    {
        $materials = RawMaterial::where('status', 1)->get();
        return $materials->map(fn($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'unit' => $m->unit,
            'stock' => $m->makingHouseStock($makingHouseId),
        ]);
    }
}