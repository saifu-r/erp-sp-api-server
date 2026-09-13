<?php

namespace App\Http\Controllers\Api\Hrm;

use App\Http\Controllers\Controller;
use App\Models\Hrm\AdvanceSalary;
use App\Services\SalaryService;
use Illuminate\Http\Request;

class AdvanceSalaryController extends Controller
{
    public function __construct(private SalaryService $service) {}

    public function index(Request $request)
    {
        $query = AdvanceSalary::with('employee');
        if ($employeeId = $request->query('employee_id')) $query->where('employee_id', $employeeId);

        $limit = (int) $request->query('limit', 20);
        $page = (int) $request->query('page', 1);
        $orderBy = strtolower($request->query('orderBy', 'desc')) === 'asc' ? 'asc' : 'desc';
        $total = $query->count();
        $data = $query->orderBy('date', $orderBy)->skip(($page - 1) * $limit)->take($limit)->get();

        return response()->json(['data' => $data, 'total' => $total]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'paid_from' => 'required|in:cash,bank',
        ]);

        $advance = $this->service->giveAdvance($data['employee_id'], $data['amount'], $data['date'], $data['paid_from']);
        return response()->json($advance, 201);
    }

    public function show(AdvanceSalary $advanceSalary)
    {
        return $advanceSalary->load('employee');
    }
}
