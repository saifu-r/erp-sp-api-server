<?php
namespace App\Http\Controllers\Api\Hrm;

use App\Http\Controllers\Controller;
use App\Models\Hrm\SalaryPayment;
use App\Services\SalaryService;
use Illuminate\Http\Request;

class SalaryPaymentController extends Controller
{
    public function __construct(private SalaryService $service) {}

    public function index(Request $request)
    {
        $query = SalaryPayment::with('employee');
        $limit = (int) $request->query('limit', 20);
        $page = (int) $request->query('page', 1);
        $orderBy = strtolower($request->query('orderBy', 'desc')) === 'asc' ? 'asc' : 'desc';
        $total = $query->count();
        $data = $query->orderBy('date', $orderBy)->skip(($page - 1) * $limit)->take($limit)->get();
        return response()->json(['data' => $data, 'total' => $total]);
    }

    public function preview(Request $request)
    {
        $data = $request->validate(['employee_id' => 'required|exists:employees,id', 'period_month' => 'required|string']);
        return response()->json($this->service->calculatePreview($data['employee_id'], $data['period_month']));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'period_month' => 'required|string',
            'absent_days' => 'required|integer|min:0',
            'absence_deduction' => 'required|numeric|min:0',
            'bonus' => 'nullable|numeric|min:0',
            'advance_recovered' => 'nullable|numeric|min:0',
            'paid_from' => 'required|in:cash,bank',
            'date' => 'required|date',
        ]);

        try {
            $salary = $this->service->processSalary(
                $data['employee_id'], $data['period_month'], $data['absent_days'], $data['absence_deduction'],
                $data['bonus'] ?? 0, $data['advance_recovered'] ?? 0, $data['paid_from'], $data['date'], $request->user()->id
            );
            return response()->json($salary, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}