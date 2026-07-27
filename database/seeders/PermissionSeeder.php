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
            'sales' => ['customer', 'quotation', 'order', 'invoice', 'payment'],
            'inventory' => ['stock-adjustment'],
            'manufacturing' => ['making-house', 'items', 'productions', 'stock-adjustment'],
            'delivery' => ['delivery-note'],
            'accounts' => ['chart-of-accounts', 'journal', 'trial-balance', 'income-statement'],
            'hrm' => ['employees', 'attendance', 'payroll'],
            'admin' => ['user', 'role'],
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
