<?php

namespace Database\Seeders;

use App\Models\Accounts\Account;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['code' => '1000', 'name' => 'Assets', 'type' => 'asset', 'parent_id' => null],
            ['code' => '1100', 'name' => 'Cash', 'type' => 'asset', 'parent_code' => '1000'],
            ['code' => '1200', 'name' => 'Bank', 'type' => 'asset', 'parent_code' => '1000'],
            ['code' => '1300', 'name' => 'Accounts Receivable', 'type' => 'asset', 'parent_code' => '1000'],
            ['code' => '1400', 'name' => 'Raw Material Inventory', 'type' => 'asset', 'parent_code' => '1000'],
            ['code' => '1500', 'name' => 'Finished Goods Inventory', 'type' => 'asset', 'parent_code' => '1000'],

            ['code' => '2000', 'name' => 'Liabilities', 'type' => 'liability', 'parent_id' => null],
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'parent_code' => '2000'],
            ['code' => '2150', 'name' => 'Making House Payable', 'type' => 'liability', 'parent_code' => '2000'],

            ['code' => '3000', 'name' => 'Equity', 'type' => 'equity', 'parent_id' => null],
            ['code' => '3100', 'name' => "Owner's Capital", 'type' => 'equity', 'parent_code' => '3000'],
            ['code' => '3200', 'name' => 'Opening Balance Equity', 'type' => 'equity', 'parent_code' => '3000'],

            ['code' => '4000', 'name' => 'Income', 'type' => 'income', 'parent_id' => null],
            ['code' => '4100', 'name' => 'Sales Revenue', 'type' => 'income', 'parent_code' => '4000'],
            ['code' => '4100', 'name' => 'Sales Revenue', 'type' => 'income', 'parent_code' => '4000'],
            ['code' => '4200', 'name' => 'Discount Received', 'type' => 'income', 'parent_code' => '4000'],

            ['code' => '5000', 'name' => 'Expenses', 'type' => 'expense', 'parent_id' => null],
            ['code' => '5100', 'name' => 'Cost of Goods Sold', 'type' => 'expense', 'parent_code' => '5000'],
            ['code' => '5160', 'name' => 'Making Cost Expense', 'type' => 'expense', 'parent_code' => '5000'],

            ['code' => '2200', 'name' => 'VAT Payable', 'type' => 'liability', 'parent_code' => '2000'],
            ['code' => '5150', 'name' => 'Discount Allowed', 'type' => 'expense', 'parent_code' => '5000'],
        ];


//         \App\Models\Accounts\Account::firstOrCreate(['code' => '2150'], ['name' => 'Making House Payable', 'type' => 'liability', 'parent_id' => $liabilities->id, 'is_system' => true]);
// \App\Models\Accounts\Account::firstOrCreate(['code' => '5160'], ['name' => 'Making Cost Expense', 'type' => 'expense', 'parent_id' => $expenses->id, 'is_system' => true]);
// \App\Models\Accounts\Account::firstOrCreate(['code' => '4200'], ['name' => 'Discount Received', 'type' => 'income', 'parent_id' => $income->id, 'is_system' => true]);

        $createdByCode = [];

        foreach ($accounts as $acc) {
            if (!isset($acc['parent_code'])) {
                $created = Account::firstOrCreate(
                    ['code' => $acc['code']],
                    ['name' => $acc['name'], 'type' => $acc['type'], 'parent_id' => null, 'is_system' => true]
                );
                $createdByCode[$acc['code']] = $created->id;
            }
        }

        foreach ($accounts as $acc) {
            if (isset($acc['parent_code'])) {
                Account::firstOrCreate(
                    ['code' => $acc['code']],
                    [
                        'name' => $acc['name'],
                        'type' => $acc['type'],
                        'parent_id' => $createdByCode[$acc['parent_code']] ?? null,
                        'is_system' => true,
                    ]
                );
            }
        }
    }
}