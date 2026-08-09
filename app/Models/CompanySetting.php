<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CompanySetting extends Model
{
    protected $fillable = ['company_name', 'address', 'phone', 'email', 'country', 'timezone', 'currency_code'];

    /** Always operates on the single row (id=1) — this app is not multi-tenant. */
    public static function current(): self
    {
        return Cache::remember('company_settings', 3600, function () {
            return self::firstOrCreate(['id' => 1], [
                'company_name' => 'Shanta Plastics',
                'country' => 'Bangladesh',
                'timezone' => 'Asia/Dhaka',
                'currency_code' => 'BDT',
            ]);
        });
    }

    public static function timezone(): string
    {
        return self::current()->timezone;
    }

    public static function forget(): void
    {
        Cache::forget('company_settings');
    }
}