<?php

namespace App\Services\Captcha;

class NullCaptchaVerifier implements CaptchaVerifier
{
    public function verify(?string $token, ?string $ipAddress = null): bool
    {
        return true;
    }
}
