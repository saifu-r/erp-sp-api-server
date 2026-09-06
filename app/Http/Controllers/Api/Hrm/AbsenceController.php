<?php
namespace App\Http\Controllers\Api\Hrm;

use App\Http\Controllers\Controller;
use App\Models\Hrm\Absence;
use Illuminate\Http\Request;

class AbsenceController extends Controller
{
    public function index(Request $request)
    {
        $query = Absence::with('employee');
        if ($employeeId = $request->query('employee_id')) $query->where('employee_id', $employeeId);

        $limit = (int) $request->query('limit', 20);
        $page = (int) $request->query('page', 1);
        $orderBy = strtolower($request->query('orderBy', 'desc')) === 'asc' ? 'asc' : 'desc';
        $total = $query->count();
        $data = $query->orderBy('from_date', $orderBy)->skip(($page - 1) * $limit)->take($limit)->get();

        return response()->json(['data' => $data, 'total' => $total]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'reason' => 'nullable|string|max:255',
        ]);

        $overlap = Absence::where('employee_id', $data['employee_id'])
            ->where('from_date', '<=', $data['to_date'])
            ->where('to_date', '>=', $data['from_date'])
            ->exists();

        if ($overlap) {
            return response()->json(['message' => 'This date range overlaps an existing absence entry for this employee.'], 422);
        }

        return response()->json(Absence::create($data), 201);
    }

    public function destroy(Absence $absence)
    {
        $absence->delete();
        return response()->json(['message' => 'Deleted']);
    }
}