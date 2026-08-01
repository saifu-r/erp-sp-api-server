<?php

namespace Database\Seeders;

use App\Models\Accounts\Account;
use App\Models\Accounts\ExpenseType;
use Illuminate\Database\Seeder;

class ExpenseTypeSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = ['Rent Expense', 'Internet Expense', 'Food Expense', 'Utilities Expense', 'Salary Expense', 'Transport Expense', 'Office Supplies Expense', 'Maintenance Expense'];

        $expenseParent = Account::where('code', '5000')->first();
        $nextCode = 5200;

        foreach ($defaults as $name) {
            if (ExpenseType::where('name', $name)->exists()) continue;

            $account = Account::firstOrCreate(
                ['name' => $name, 'type' => 'expense'],
                ['code' => (string) $nextCode, 'parent_id' => $expenseParent?->id, 'is_system' => true]
            );

            ExpenseType::create(['name' => $name, 'account_id' => $account->id, 'status' => 1]);
            $nextCode += 10;
        }
    }
}