<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlatformSettings
{
    public function all(): array
    {
        $defaults = ['platform_name' => config('app.name'), 'timezone' => 'Africa/Lagos', 'locale' => 'en',
            'notifications' => ['push_enabled' => false, 'email_enabled' => true, 'max_push_per_day' => 3],
            'data_retention_days' => 365, 'allow_registration' => true, 'require_email_verification' => true];
        if (! Schema::hasTable('platform_settings')) {
            return $defaults;
        }

        return array_replace($defaults, PlatformSetting::all()->pluck('value', 'key')->all());
    }

    public function get(string $key, mixed $fallback = null): mixed
    {
        return data_get($this->all(), $key, $fallback);
    }

    public function put(array $values): void
    {
        DB::transaction(function () use ($values) {
            foreach ($values as $key => $value) {
                PlatformSetting::updateOrCreate(['key' => $key], ['value' => $value]);
            }
        }, 3);
    }
}
