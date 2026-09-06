<?php

namespace App\Http\Controllers\Api\Hrm;

use App\Http\Controllers\Controller;
use App\Models\Hrm\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::query();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $limit = (int) $request->query('limit', 20);
        $page = (int) $request->query('page', 1);
        $orderBy = strtolower($request->query('orderBy', 'desc')) === 'asc' ? 'asc' : 'desc';

        $total = $query->count();
        $data = $query->orderBy('created_at', $orderBy)->skip(($page - 1) * $limit)->take($limit)->get();

        return response()->json(['data' => $data, 'total' => $total]);
    }

    public function all()
    {
        return Employee::where('status', 1)->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'designation' => 'nullable|string|max:255',
            'joining_date' => 'nullable|date',
            'base_salary' => 'nullable|numeric|min:0',
            'status' => 'required|integer|in:1,2',
        ]);
        return response()->json(Employee::create($data), 201);
    }

    //     protected $fillable = ['name', 'phone', 'address', 'designation', 'joining_date', 'base_salary', 'status'];

    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'designation' => 'nullable|string|max:255',
            'joining_date' => 'nullable|date',
            'base_salary' => 'nullable|numeric|min:0',
            'status' => 'required|integer|in:1,2',
        ]);
        $employee->update($data);
        return $employee;
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
