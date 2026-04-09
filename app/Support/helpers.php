<?php

use App\Models\Setting;
use App\Support\CurrentCompany;

if (! function_exists('company_id')) {
    function company_id(): ?int
    {
        return app(CurrentCompany::class)->id();
    }
}

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::getValue($key, $default);
    }
}
