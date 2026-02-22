<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Exception;

class OtpDecryptionService
{
    const OTP_EXPIRY_MINUTES = 5;
    const MAX_ATTEMPTS = 3;
    const RATE_LIMIT_SECONDS = 60;
    const REDIS_PREFIX = 'otp_decryption:';
    const REDIS_ATTEMPT_PREFIX = 'otp_attempts:';

    /**
     * Generate OTP and store in Redis with session binding
     * Redis Key: otp_decryption:session_id
     * Value: {otp_code, user_id, created_at, attempts}
     *
     * @param string $userId User ID
     * @param string $sessionId Session ID
     * @return string Generated OTP code
     * @throws Exception
     */
    public static function generateOtp(string $userId, string $sessionId): string
    {
        try {
            // Generate 6-digit OTP
            $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            $redisKey = self::REDIS_PREFIX . $sessionId;
            $ttl = self::OTP_EXPIRY_MINUTES * 60;

            // Store OTP in Redis with TTL
            $otpData = [
                'code' => $otpCode,
                'user_id' => $userId,
                'session_id' => $sessionId,
                'created_at' => now()->toIso8601String(),
                'attempts' => 0,
                'verified' => false,
            ];

            // Use Redis transaction to ensure atomicity
            Redis::setex($redisKey, $ttl, json_encode($otpData));

            // Log OTP generation for audit trail
            Log::info('OTP generated for decryption', [
                'user_id' => $userId,
                'session_id' => substr($sessionId, 0, 8), // Partial session ID for privacy
                'ttl' => $ttl,
            ]);

            return $otpCode;
        } catch (Exception $e) {
            Log::error('Failed to generate OTP', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);
            throw new Exception('Failed to generate OTP. Please try again.');
        }
    }

    /**
     * Verify OTP against stored value in Redis
     * Returns true only if OTP is valid and session matches
     *
     * @param string $providedOtp OTP provided by user
     * @param string $userId User ID (from session)
     * @param string $sessionId Session ID
     * @return bool True if OTP is valid
     * @throws Exception
     */
    public static function verifyOtp(string $providedOtp, string $userId, string $sessionId): bool
    {
        try {
            $redisKey = self::REDIS_PREFIX . $sessionId;
            $attemptKey = self::REDIS_ATTEMPT_PREFIX . $sessionId;

            // Check attempt limit
            $attempts = (int) Redis::get($attemptKey) ?? 0;
            if ($attempts >= self::MAX_ATTEMPTS) {
                Log::warning('OTP verification attempt limit exceeded', [
                    'user_id' => $userId,
                    'session_id' => substr($sessionId, 0, 8),
                    'attempts' => $attempts,
                ]);
                throw new Exception('Maximum OTP verification attempts exceeded. Please request a new OTP.');
            }

            // Get stored OTP from Redis
            $otpData = Redis::get($redisKey);
            if (!$otpData) {
                Log::warning('OTP not found in Redis', [
                    'user_id' => $userId,
                    'session_id' => substr($sessionId, 0, 8),
                ]);
                throw new Exception('OTP has expired. Please request a new one.');
            }

            $otp = json_decode($otpData, true);

            // Verify OTP code
            if ($otp['code'] !== $providedOtp) {
                $attempts++;
                Redis::incr($attemptKey);
                Redis::expire($attemptKey, self::RATE_LIMIT_SECONDS);

                Log::warning('Invalid OTP provided', [
                    'user_id' => $userId,
                    'session_id' => substr($sessionId, 0, 8),
                    'attempts' => $attempts,
                ]);
                throw new Exception("Invalid OTP. Attempts remaining: " . (self::MAX_ATTEMPTS - $attempts));
            }

            // Verify user ID matches
            if ($otp['user_id'] !== $userId) {
                Log::warning('OTP user mismatch', [
                    'expected_user' => $userId,
                    'otp_user' => $otp['user_id'],
                    'session_id' => substr($sessionId, 0, 8),
                ]);
                throw new Exception('Invalid OTP for this user.');
            }

            // Mark OTP as verified in Redis (doesn't expire the key, just marks as used)
            $otp['verified'] = true;
            $otp['verified_at'] = now()->toIso8601String();
            $ttl = Redis::ttl($redisKey);

            if ($ttl > 0) {
                Redis::setex($redisKey, $ttl, json_encode($otp));
            }

            // Clear attempt counter
            Redis::del($attemptKey);

            Log::info('OTP verified successfully', [
                'user_id' => $userId,
                'session_id' => substr($sessionId, 0, 8),
            ]);

            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Check if OTP is verified for current session
     * Used to determine if decryption is allowed
     *
     * @param string $sessionId Session ID
     * @param string $userId User ID
     * @return bool True if OTP is verified
     */
    public static function isDecryptionAllowed(string $sessionId, string $userId): bool
    {
        try {
            $redisKey = self::REDIS_PREFIX . $sessionId;
            $otpData = Redis::get($redisKey);

            if (!$otpData) {
                return false;
            }

            $otp = json_decode($otpData, true);
            return $otp['verified'] === true && $otp['user_id'] === $userId;
        } catch (Exception $e) {
            Log::error('Error checking decryption allowed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get remaining TTL for OTP in seconds
     *
     * @param string $sessionId Session ID
     * @return int Remaining seconds (0 if expired)
     */
    public static function getOtpTtl(string $sessionId): int
    {
        try {
            $redisKey = self::REDIS_PREFIX . $sessionId;
            $ttl = Redis::ttl($redisKey);
            return max(0, $ttl);
        } catch (Exception $e) {
            Log::error('Error getting OTP TTL', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Invalidate OTP for session (logout, security purposes)
     *
     * @param string $sessionId Session ID
     * @return void
     */
    public static function invalidateOtp(string $sessionId): void
    {
        try {
            $redisKey = self::REDIS_PREFIX . $sessionId;
            $attemptKey = self::REDIS_ATTEMPT_PREFIX . $sessionId;

            Redis::del($redisKey);
            Redis::del($attemptKey);

            Log::info('OTP invalidated for session', [
                'session_id' => substr($sessionId, 0, 8),
            ]);
        } catch (Exception $e) {
            Log::error('Error invalidating OTP', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Rate limit check for OTP sending (prevent spam)
     * Uses Redis to track OTP send attempts per user per minute
     *
     * @param string $userId User ID
     * @return bool True if user can request new OTP
     */
    public static function canRequestOtp(string $userId): bool
    {
        try {
            $rateLimitKey = 'otp_send_limit:' . $userId;
            $attempts = (int) Redis::get($rateLimitKey) ?? 0;

            if ($attempts >= 3) { // Max 3 OTP requests per minute
                return false;
            }

            Redis::incr($rateLimitKey);
            Redis::expire($rateLimitKey, 60); // Reset every 60 seconds

            return true;
        } catch (Exception $e) {
            Log::error('Error checking OTP rate limit', ['error' => $e->getMessage()]);
            return true; // Allow on error to not block legitimate users
        }
    }

    /**
     * Get OTP rate limit remaining for user
     *
     * @param string $userId User ID
     * @return int Seconds until next OTP can be requested
     */
    public static function getOtpRateLimitRemaining(string $userId): int
    {
        try {
            $rateLimitKey = 'otp_send_limit:' . $userId;
            $ttl = Redis::ttl($rateLimitKey);
            return max(0, $ttl);
        } catch (Exception $e) {
            Log::error('Error getting rate limit remaining', ['error' => $e->getMessage()]);
            return 0;
        }
    }
}
