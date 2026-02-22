<?php

namespace App\Http\Controllers;

use App\Services\OtpDecryptionService;
use App\Services\EncryptionService;
use App\Services\AuditLogService;
use App\Models\PersonsInvolved;
use App\Models\Evidence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Exception;

class DataDecryptionController extends Controller
{
    /**
     * Send OTP to user's email for data decryption
     * POST /api/decrypt-data/send-otp
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendOtp(Request $request)
    {
        try {
            // Get user info from session (JWT auth helper)
            $authUser = session('auth_user');
            $userId = $authUser['id'] ?? auth()->id();
            $userEmail = $authUser['email'] ?? auth()->user()?->email;
            $userName = $authUser['name'] ?? 'User';

            $sessionId = session()->getId();

            if (!$userId || !$userEmail) {
                Log::warning('Attempted to send decryption OTP without valid user context', [
                    'ip' => $request->ip(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'User authentication required.',
                ], 401);
            }

            // Rate limit check - max 3 requests per minute
            if (!OtpDecryptionService::canRequestOtp($userId)) {
                $remaining = OtpDecryptionService::getOtpRateLimitRemaining($userId);
                return response()->json([
                    'success' => false,
                    'message' => "Too many OTP requests. Please wait {$remaining} seconds.",
                    'retry_after' => $remaining,
                ], 429);
            }

            // Get incident ID from request if available (for audit logging)
            $incidentId = $request->input('incident_id', 0);

            // Generate OTP and store in Redis
            $otpCode = OtpDecryptionService::generateOtp($userId, $sessionId);

            // Send OTP via email
            Mail::send('emails.decryption-otp', [
                'otp_code' => $otpCode,
                'user_name' => $userName,
                'expires_in_minutes' => OtpDecryptionService::OTP_EXPIRY_MINUTES,
            ], function ($message) use ($userEmail) {
                $message->to($userEmail)
                    ->subject('Data Decryption OTP - Crime Management System');
            });

            // Log successful OTP send
            Log::info('Decryption OTP sent via email', [
                'user_id' => $userId,
                'email' => substr($userEmail, 0, 3) . '***' . substr($userEmail, -3), // Partial email for privacy
                'ip' => $request->ip(),
            ]);

            // Audit log: OTP requested
            if ($incidentId > 0) {
                AuditLogService::logSendDecryptOtp($incidentId, [
                    'user_email' => substr($userEmail, 0, 3) . '***' . substr($userEmail, -3),
                    'ip_address' => $request->ip(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "OTP has been sent to {$userEmail}. Valid for 5 minutes.",
                'expires_in' => OtpDecryptionService::OTP_EXPIRY_MINUTES * 60,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send decryption OTP', [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null,
                'ip' => $request->ip(),
            ]);

            // Audit log: Decryption error (OTP send failed)
            if (isset($incidentId) && $incidentId > 0) {
                AuditLogService::logDecryptDataError($incidentId, [
                    'error_message' => 'Failed to send OTP: ' . $e->getMessage(),
                    'ip_address' => $request->ip(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP. Please try again later.',
            ], 500);
        }
    }

    /**
     * Verify OTP and return decrypted data
     * POST /api/decrypt-data/verify-otp
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyOtp(Request $request)
    {
        try {
            // Validate input - allow 'session_valid' for auto-decryption if session is still valid
            $otpInput = $request->input('otp');
            $isSessionValidPseudoOtp = $otpInput === 'session_valid';

            if (!$isSessionValidPseudoOtp) {
                $request->validate([
                    'otp' => 'required|string|size:6|regex:/^\d{6}$/',
                    'incident_id' => 'required|integer',
                ], [
                    'otp.required' => 'OTP is required.',
                    'otp.size' => 'OTP must be 6 digits.',
                    'otp.regex' => 'OTP must contain only digits.',
                    'incident_id.required' => 'Incident ID is required.',
                    'incident_id.integer' => 'Incident ID must be a number.',
                ]);
            } else {
                $request->validate([
                    'incident_id' => 'required|integer',
                ]);
            }

            // Get user info from session (JWT auth helper)
            $authUser = session('auth_user');
            $userId = $authUser['id'] ?? auth()->id();
            $sessionId = session()->getId();
            $incidentId = $request->input('incident_id');
            $otpCode = $request->input('otp');

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User authentication required.',
                ], 401);
            }

            // Verify OTP against Redis (skip if using session_valid pseudo OTP)
            if (!$isSessionValidPseudoOtp) {
                OtpDecryptionService::verifyOtp($otpCode, $userId, $sessionId);
            } else {
                // Check if session-based decryption is allowed
                if (!OtpDecryptionService::isDecryptionAllowed($sessionId, $userId)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Decryption session has expired. Please request a new OTP.',
                    ], 410);
                }
            }

            // Audit log: OTP verification successful
            AuditLogService::logVerifyDecryptOtpSuccess($incidentId, [
                'ip_address' => $request->ip(),
            ]);

            // Fetch and decrypt sensitive data for the incident
            $personsInvolved = PersonsInvolved::where('incident_id', $incidentId)->get();
            $evidenceItems = Evidence::where('incident_id', $incidentId)->get();

            // Decrypt persons involved data
            $decryptedPersons = $personsInvolved->map(function ($person) {
                return [
                    'id' => $person->id,
                    'role' => $person->role,
                    'first_name' => EncryptionService::decryptIfNotEmpty($person->first_name),
                    'middle_name' => EncryptionService::decryptIfNotEmpty($person->middle_name),
                    'last_name' => EncryptionService::decryptIfNotEmpty($person->last_name),
                    'contact_number' => EncryptionService::decryptIfNotEmpty($person->contact_number),
                    'other_info' => EncryptionService::decryptIfNotEmpty($person->other_info),
                ];
            })->all();

            // Decrypt evidence items
            $decryptedEvidence = $evidenceItems->map(function ($evidence) {
                return [
                    'id' => $evidence->id,
                    'type' => $evidence->evidence_type,
                    'description' => EncryptionService::decryptIfNotEmpty($evidence->description),
                    'evidence_link' => EncryptionService::decryptIfNotEmpty($evidence->evidence_link),
                    'collected_date' => $evidence->collected_date,
                ];
            })->all();

            // Log decryption access
            Log::info('Data decrypted via OTP verification', [
                'user_id' => $userId,
                'incident_id' => $incidentId,
                'persons_count' => count($decryptedPersons),
                'evidence_count' => count($decryptedEvidence),
                'ip' => $request->ip(),
            ]);

            // Audit log: Data decryption successful
            AuditLogService::logDecryptDataSuccess($incidentId, [
                'persons_count' => count($decryptedPersons),
                'evidence_count' => count($decryptedEvidence),
                'ip_address' => $request->ip(),
            ]);

            // Cache decrypted data in Redis for 30 minutes per incident
            // This allows instant decryption on page refresh without re-decrypting
            $this->cacheDecryptedData($userId, $incidentId, [
                'persons_involved' => $decryptedPersons,
                'evidence_items' => $decryptedEvidence,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data decrypted successfully.',
                'data' => [
                    'persons_involved' => $decryptedPersons,
                    'evidence_items' => $decryptedEvidence,
                    'decrypted_at' => now()->toIso8601String(),
                    'session_valid_until' => now()->addMinutes(30)->toIso8601String(),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            // Check if it's an OTP-specific error
            $message = $e->getMessage();
            $statusCode = 400;
            $isOtpError = false;

            if (str_contains($message, 'expired') || str_contains($message, 'not found')) {
                $statusCode = 410; // Gone - OTP expired
                $isOtpError = true;
            } elseif (str_contains($message, 'exceeded')) {
                $statusCode = 429; // Too Many Requests
                $isOtpError = true;
            } elseif (str_contains($message, 'Invalid')) {
                $statusCode = 401; // Unauthorized
                $isOtpError = true;
            }

            Log::warning('OTP verification failed', [
                'error' => $message,
                'user_id' => $userId ?? null,
                'incident_id' => $incidentId ?? null,
                'ip' => $request->ip(),
            ]);

            // Audit log: OTP verification failed
            if ($incidentId ?? null) {
                AuditLogService::logVerifyDecryptOtpFailed($incidentId, [
                    'error_message' => $message,
                    'ip_address' => $request->ip(),
                ]);
            }

            // Audit log: Data decryption error (if it's a decryption error, not OTP error)
            if (!$isOtpError && ($incidentId ?? null)) {
                AuditLogService::logDecryptDataError($incidentId, [
                    'error_message' => $message,
                    'ip_address' => $request->ip(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $message,
            ], $statusCode);
        }
    }

    /**
     * Check if decryption is allowed for current session
     * GET /api/decrypt-data/status
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkDecryptionStatus(Request $request)
    {
        try {
            // Get user info from session (JWT auth helper)
            $authUser = session('auth_user');
            $userId = $authUser['id'] ?? auth()->id();
            $sessionId = session()->getId();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'authenticated' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }

            $isAllowed = OtpDecryptionService::isDecryptionAllowed($sessionId, $userId);
            $ttl = OtpDecryptionService::getOtpTtl($sessionId);

            return response()->json([
                'success' => true,
                'authenticated' => true,
                'decryption_allowed' => $isAllowed,
                'otp_ttl_seconds' => $ttl,
                'expires_at' => $ttl > 0 ? now()->addSeconds($ttl)->toIso8601String() : null,
            ]);
        } catch (Exception $e) {
            Log::error('Error checking decryption status', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error checking decryption status.',
            ], 500);
        }
    }

    /**
     * Invalidate OTP (logout, security purposes)
     * POST /api/decrypt-data/invalidate
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function invalidateOtp(Request $request)
    {
        try {
            $authUser = session('auth_user');
            $userId = $authUser['id'] ?? auth()->id();
            $sessionId = session()->getId();

            OtpDecryptionService::invalidateOtp($sessionId);

            // Clear all cached decrypted data for this user
            if ($userId) {
                $this->clearUserCachedData($userId);
            }

            return response()->json([
                'success' => true,
                'message' => 'Decryption OTP invalidated.',
            ]);
        } catch (Exception $e) {
            Log::error('Error invalidating OTP', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error invalidating OTP.',
            ], 500);
        }
    }

    /**
     * Get cached decrypted data for an incident
     * GET /api/decrypt-data/get-cached/{incident_id}
     *
     * @param int $incidentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCachedData($incidentId)
    {
        try {
            $authUser = session('auth_user');
            $userId = $authUser['id'] ?? auth()->id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User authentication required.',
                ], 401);
            }

            // Try to get cached data from Redis
            $cacheKey = "decrypted_data:user_{$userId}:incident_{$incidentId}";
            $cachedData = \Illuminate\Support\Facades\Redis::get($cacheKey);

            if (!$cachedData) {
                return response()->json([
                    'success' => false,
                    'message' => 'No cached data available.',
                ], 404);
            }

            $decryptedData = json_decode($cachedData, true);

            Log::info('Retrieved cached decrypted data', [
                'user_id' => $userId,
                'incident_id' => $incidentId,
            ]);

            return response()->json([
                'success' => true,
                'data' => $decryptedData,
            ]);
        } catch (Exception $e) {
            Log::error('Error retrieving cached data', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving cached data.',
            ], 500);
        }
    }

    /**
     * Cache decrypted data in Redis for 30 minutes per incident per user
     *
     * @param int $userId
     * @param int $incidentId
     * @param array $decryptedData
     * @return void
     */
    private function cacheDecryptedData($userId, $incidentId, $decryptedData)
    {
        try {
            $cacheKey = "decrypted_data:user_{$userId}:incident_{$incidentId}";
            $ttl = 30 * 60; // 30 minutes in seconds

            \Illuminate\Support\Facades\Redis::setex(
                $cacheKey,
                $ttl,
                json_encode($decryptedData)
            );

            Log::info('Cached decrypted data in Redis', [
                'user_id' => $userId,
                'incident_id' => $incidentId,
                'ttl' => $ttl,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to cache decrypted data', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'incident_id' => $incidentId,
            ]);
            // Don't throw - caching failure shouldn't block the response
        }
    }

    /**
     * Clear all cached decrypted data for a user
     * Called on logout or session invalidation
     *
     * @param int $userId
     * @return void
     */
    private function clearUserCachedData($userId)
    {
        try {
            // Get all cache keys for this user using pattern
            $pattern = "decrypted_data:user_{$userId}:*";
            $keys = \Illuminate\Support\Facades\Redis::keys($pattern);

            if ($keys && is_array($keys)) {
                foreach ($keys as $key) {
                    \Illuminate\Support\Facades\Redis::del($key);
                }

                Log::info('Cleared cached decrypted data for user', [
                    'user_id' => $userId,
                    'keys_deleted' => count($keys),
                ]);
            }
        } catch (Exception $e) {
            Log::error('Failed to clear user cached data', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);
            // Don't throw - cache clearing failure shouldn't block the response
        }
    }
}
