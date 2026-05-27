<?php

namespace App\Services\Captcha;

use Illuminate\Support\Facades\Http;

class TurnstileCaptchaVerifier implements CaptchaVerifier
{
    public function verify(?string $token, ?string $ipAddress = null): bool
    {
        $secret = config('captcha.turnstile.secret_key');

        if (! $secret || ! $token) {
            return false;
        }

        $response = Http::asForm()->post(config('captcha.turnstile.verify_url'), [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $ipAddress,
        ]);

        return $response->ok() && (bool) $response->json('success');
    }
}
