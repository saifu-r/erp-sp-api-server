<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use Illuminate\Http\Request;

class CompanySettingController extends Controller
{
    public function show()
    {
        return CompanySetting::current();
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'company_name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'country' => 'required|string|max:100',
            'timezone' => 'required|string|max:100',
            'currency_code' => 'required|string|max:10',
        ]);

        $settings = CompanySetting::current();
        $settings->update($data);
        CompanySetting::forget(); // invalidate cache immediately

        return $settings->fresh();
    }
}