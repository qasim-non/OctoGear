<?php

namespace App\Support;

use Closure;

final class AdminPassword
{
    public static function rules(): array
    {
        // Bcrypt truncates after 72 bytes; reject rather than silently truncate.
        return ['required', 'string', 'min:15', 'max:72', function (string $attribute, mixed $value, Closure $fail) {
            if (strlen($value) > 72) {
                $fail(__('admin_accounts.password_length'));
            }
        }];
    }
}
