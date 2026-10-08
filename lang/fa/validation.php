<?php

// Minimal Persian validation messages for the admin panel. Missing rules fall back to English.
return [
    'required' => 'فیلد :attribute الزامی است.',
    'string' => ':attribute باید متن باشد.',
    'email' => ':attribute باید یک ایمیل معتبر باشد.',
    'url' => ':attribute باید یک آدرس معتبر (HTTPS) باشد.',
    'unique' => 'این :attribute قبلاً استفاده شده است.',
    'exists' => ':attribute انتخاب‌شده معتبر نیست.',
    'regex' => 'فرمت :attribute معتبر نیست.',
    'json' => ':attribute باید JSON معتبر باشد.',
    'boolean' => ':attribute باید درست یا نادرست باشد.',
    'in' => ':attribute انتخاب‌شده معتبر نیست.',
    'date' => ':attribute باید تاریخ معتبر باشد.',
    'array' => ':attribute باید آرایه باشد.',
    'prohibited' => 'فیلد :attribute مجاز نیست.',
    'max' => [
        'string' => ':attribute نباید بیشتر از :max کاراکتر باشد.',
        'numeric' => ':attribute نباید بیشتر از :max باشد.',
        'array' => ':attribute نباید بیشتر از :max مورد داشته باشد.',
    ],
    'attributes' => [
        'name' => 'نام',
        'slug' => 'شناسه کوتاه',
        'webhook_url' => 'آدرس وب‌هوک',
        'return_url' => 'آدرس بازگشت',
        'email' => 'ایمیل',
        'password' => 'رمز عبور',
        'provider' => 'درگاه',
        'client' => 'سایت',
        'config' => 'تنظیمات',
        'extra_config' => 'تنظیمات اضافه',
    ],
];
