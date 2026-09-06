<?php

namespace App\Services;

use App\Models\Accounts\Account;
use App\Models\Hrm\AdvanceSalary;
use App\Models\Hrm\Employee;
use App\Models\Hrm\SalaryPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SalaryService
{
    public function __construct(private JournalPostingService $journal) {}

    /** Returns the auto-computed preview so the Angular form can show it before confirming. */
    public function calculatePreview(int $employeeId, string $periodMonth): array
    {
        /** @var Employee $employee */
        $employee = Employee::findOrFail($employeeId);

        $periodStart = Carbon::parse($periodMonth . '-01')->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();
        $daysInMonth = $periodStart->daysInMonth;

        $absentDays = $this->overlappingAbsentDays($employeeId, $periodStart, $periodEnd);
        $perDayRate = $employee->base_salary / $daysInMonth;
        $absenceDeduction = round($perDayRate * $absentDays, 2);

        $outstandingAdvance = AdvanceSalary::where('employee_id', $employeeId)
            ->where('status', 1)->sum('remaining_balance');

        return [
            'base_salary' => $employee->base_salary,
            'absent_days' => $absentDays,
            'per_day_rate' => round($perDayRate, 2),
            'absence_deduction' => $absenceDeduction,
            'outstanding_advance' => (float) $outstandingAdvance,
        ];
    }

    private function overlappingAbsentDays(int $employeeId, Carbon $periodStart, Carbon $periodEnd): int
    {
        $absences = \App\Models\Hrm\Absence::where('employee_id', $employeeId)
            ->where('from_date', '<=', $periodEnd)
            ->where('to_date', '>=', $periodStart)
            ->get();

        $total = 0;
        foreach ($absences as $absence) {
            $overlapStart = $absence->from_date->max($periodStart);
            $overlapEnd = $absence->to_date->min($periodEnd);
            $total += $overlapStart->diffInDays($overlapEnd) + 1;
        }
        return $total;
    }

    public function processSalary(
        int $employeeId, string $periodMonth, int $absentDays, float $absenceDeduction,
        float $bonus, float $advanceRecovered, string $paidFrom, string $date, ?int $userId
    ): SalaryPayment {
        return DB::transaction(function () use ($employeeId, $periodMonth, $absentDays, $absenceDeduction, $bonus, $advanceRecovered, $paidFrom, $date, $userId) {
            /** @var Employee $employee */
            $employee = Employee::findOrFail($employeeId);

            $grossPayable = $employee->base_salary - $absenceDeduction + $bonus;
            $netPaid = $grossPayable - $advanceRecovered;

            if ($netPaid < 0) {
                throw new \Exception('Advance recovery cannot exceed the gross payable amount.');
            }

            $salary = SalaryPayment::create([
                'employee_id' => $employeeId, 'period_month' => $periodMonth,
                'base_salary' => $employee->base_salary, 'absent_days' => $absentDays,
                'absence_deduction' => $absenceDeduction, 'bonus' => $bonus,
                'gross_payable' => $grossPayable, 'advance_recovered' => $advanceRecovered,
                'net_paid' => $netPaid, 'paid_from' => $paidFrom, 'date' => $date, 'user_id' => $userId,
            ]);

            if ($advanceRecovered > 0) {
                $this->deductFromAdvances($employeeId, $advanceRecovered);
            }

            $salaryExpense = Account::where('name', 'Salary Expense')->firstOrFail();
            $employeeAdvance = Account::where('code', '1600')->firstOrFail();
            $paidFromAccount = Account::where('name', $paidFrom === 'bank' ? 'Bank' : 'Cash')->firstOrFail();

            $lines = [['account_id' => $salaryExpense->id, 'debit' => $grossPayable, 'credit' => 0]];
            if ($advanceRecovered > 0) {
                $lines[] = ['account_id' => $employeeAdvance->id, 'debit' => 0, 'credit' => $advanceRecovered];
            }
            $lines[] = ['account_id' => $paidFromAccount->id, 'debit' => 0, 'credit' => $netPaid];

            $this->journal->post(
                description: "Salary — {$employee->name} ({$periodMonth})",
                lines: $lines,
                referenceType: 'salary_payment',
                referenceId: $salary->id,
                date: $date
            );

            return $salary->fresh();
        });
    }

    /** Draws down oldest outstanding advances first, same FIFO spirit as raw material batches. */
    private function deductFromAdvances(int $employeeId, float $amount): void
    {
        $advances = AdvanceSalary::where('employee_id', $employeeId)->where('status', 1)
            ->orderBy('date', 'asc')->get();

        $remaining = $amount;
        foreach ($advances as $advance) {
            if ($remaining <= 0) break;
            $deduct = min($advance->remaining_balance, $remaining);
            $advance->remaining_balance -= $deduct;
            if ($advance->remaining_balance <= 0) $advance->status = 2;
            $advance->save();
            $remaining -= $deduct;
        }
    }

    public function giveAdvance(int $employeeId, float $amount, string $date, string $paidFrom): AdvanceSalary
    {
        return DB::transaction(function () use ($employeeId, $amount, $date, $paidFrom) {
            $advance = AdvanceSalary::create([
                'employee_id' => $employeeId, 'amount' => $amount, 'remaining_balance' => $amount,
                'date' => $date, 'status' => 1,
            ]);

            $employeeAdvance = Account::where('code', '1600')->firstOrFail();
            $paidFromAccount = Account::where('name', $paidFrom === 'bank' ? 'Bank' : 'Cash')->firstOrFail();

            $this->journal->post(
                description: "Advance salary given",
                lines: [
                    ['account_id' => $employeeAdvance->id, 'debit' => $amount, 'credit' => 0],
                    ['account_id' => $paidFromAccount->id, 'debit' => 0, 'credit' => $amount],
                ],
                referenceType: 'advance_salary', referenceId: $advance->id, date: $date
            );

            return $advance;
        });
    }
}