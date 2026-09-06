<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Two-factor login codes for admin and staff sign-ins.
 *
 * The pending login lives in the session; only the code itself is kept here,
 * hashed and bound to that session id, so a code emailed to one browser cannot
 * be replayed in another. Separate from OtpDecryptionService, which guards
 * record decryption for an already-authenticated user.
 */
class LoginOtpService
{
    public const OTP_EXPIRY_MINUTES = 1;
    public const MAX_ATTEMPTS = 5;
    public const RESEND_COOLDOWN_SECONDS = 30;

    private const KEY_PREFIX = 'login_otp:';
    private const RESEND_PREFIX = 'login_otp_resend:';

    private static function key(string $sessionId): string
    {
        return self::KEY_PREFIX . hash('sha256', $sessionId);
    }

    private static function resendKey(string $sessionId): string
    {
        return self::RESEND_PREFIX . hash('sha256', $sessionId);
    }

    /**
     * Issue a fresh 6-digit code for this session, replacing any previous one.
     * Returns the plaintext code — the caller emails it and must not store it.
     */
    public static function generate(string $sessionId, string $accountKey): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put(self::key($sessionId), [
            'hash'        => hash('sha256', $code),
            'account_key' => $accountKey,
            'attempts'    => 0,
            'created_at'  => now()->toIso8601String(),
        ], now()->addMinutes(self::OTP_EXPIRY_MINUTES));

        Cache::put(self::resendKey($sessionId), now()->addSeconds(self::RESEND_COOLDOWN_SECONDS)->timestamp, now()->addSeconds(self::RESEND_COOLDOWN_SECONDS));

        Log::info('Login OTP issued', [
            'account_key' => $accountKey,
            'session'     => substr($sessionId, 0, 8),
        ]);

        return $code;
    }

    /**
     * Check a submitted code. Returns an [ok, message] pair rather than throwing
     * so the controller can render the error straight back onto the form.
     *
     * @return array{0:bool,1:?string}
     */
    public static function verify(string $sessionId, string $accountKey, string $code): array
    {
        $key = self::key($sessionId);
        $data = Cache::get($key);

        if (!$data) {
            return [false, 'Your verification code has expired. Please sign in again to get a new one.'];
        }

        // The code is tied to the account that requested it, so a pending code
        // cannot be spent against a different account in the same session.
        if (!hash_equals((string) $data['account_key'], $accountKey)) {
            self::clear($sessionId);
            return [false, 'This verification code is no longer valid. Please sign in again.'];
        }

        if ((int) $data['attempts'] >= self::MAX_ATTEMPTS) {
            self::clear($sessionId);
            return [false, 'Too many incorrect codes. Please sign in again to request a new one.'];
        }

        if (!hash_equals((string) $data['hash'], hash('sha256', $code))) {
            $data['attempts'] = (int) $data['attempts'] + 1;
            $remaining = self::MAX_ATTEMPTS - $data['attempts'];

            if ($remaining <= 0) {
                self::clear($sessionId);
                return [false, 'Too many incorrect codes. Please sign in again to request a new one.'];
            }

            // Keep the original expiry: a wrong guess must not extend the window.
            $ttl = self::remainingTtl($data);
            Cache::put($key, $data, $ttl);

            return [false, "Incorrect code. {$remaining} attempt(s) remaining."];
        }

        self::clear($sessionId);

        return [true, null];
    }

    /** Whether a code is still live for this session (false once spent or expired) */
    public static function isActive(string $sessionId): bool
    {
        return Cache::has(self::key($sessionId));
    }

    /** Seconds left before another code may be requested (0 when allowed now) */
    public static function resendCooldownRemaining(string $sessionId): int
    {
        $until = Cache::get(self::resendKey($sessionId));

        return $until ? max(0, (int) $until - now()->timestamp) : 0;
    }

    public static function clear(string $sessionId): void
    {
        Cache::forget(self::key($sessionId));
    }

    /** Time left on the original window, floored at one second so the put sticks */
    private static function remainingTtl(array $data): \DateTimeInterface
    {
        $expiresAt = \Illuminate\Support\Carbon::parse($data['created_at'])
            ->addMinutes(self::OTP_EXPIRY_MINUTES);

        return $expiresAt->isFuture() ? $expiresAt : now()->addSecond();
    }
}
