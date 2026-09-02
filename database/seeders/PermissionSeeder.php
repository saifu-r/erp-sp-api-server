<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            'sales' => ['customer', 'product', 'quotation', 'order', 'invoice', 'payment', 'write-off'],
            'inventory' => ['stock-adjustment'],
            'manufacturing' => [
                'making-house',
                'items',
                'productions',
                'stock-adjustment',
                'raw-material',
                'raw-material-purchase',
                'raw-material-transfer', 'making-cost'
            ],
            'delivery' => ['delivery-note'],
            'hrm' => ['employees', 'attendance', 'payroll'],
            'admin' => ['user', 'role'],
            'adjustment' => ['stock-adjustment', 'ledger-adjustment'],
            'accounts' => ['chart-of-accounts', 'journal', 'trial-balance', 'income-statement', 'balance-sheet', 'cash-flow', 'expense-type', 'expense'],
            'purchase' => ['supplier', 'purchase', 'purchase-payment'],
            'settings' => ['company-profile', 'preferences'],
        ];

        $actions = ['view', 'create', 'edit', 'delete'];

        foreach ($modules as $module => $features) {
            foreach ($features as $feature) {
                foreach ($actions as $action) {
                    Permission::firstOrCreate([
                        'code' => "{$module}.{$feature}.{$action}",
                    ], [
                        'module' => $module,
                        'label' => ucfirst($action) . ' ' . ucwords(str_replace('-', ' ', $feature)),
                    ]);
                }
            }
        }
    }
}
