<?php

namespace App\Services\Captcha;

interface CaptchaVerifier
{
    public function verify(?string $token, ?string $ipAddress = null): bool;
}
