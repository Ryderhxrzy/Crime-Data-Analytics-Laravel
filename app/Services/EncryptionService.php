<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Exception;

class EncryptionService
{
    const ALGORITHM = 'AES-256-CBC';

    /**
     * Encrypt sensitive data using AES-256-CBC
     *
     * @param string $data The data to encrypt
     * @return string Encrypted data
     */
    public static function encrypt(string $data): string
    {
        try {
            return Crypt::encryptString($data);
        } catch (Exception $e) {
            \Log::error('Encryption failed', [
                'error' => $e->getMessage(),
                'data_length' => strlen($data)
            ]);
            throw $e;
        }
    }

    /**
     * Decrypt sensitive data using AES-256-CBC
     *
     * @param string $encrypted The encrypted data
     * @return string Decrypted data
     */
    public static function decrypt(string $encrypted): string
    {
        try {
            return Crypt::decryptString($encrypted);
        } catch (Exception $e) {
            \Log::error('Decryption failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Encrypt data if it's not empty
     *
     * @param string|null $data The data to encrypt
     * @return string|null Encrypted data or null if input is null/empty
     */
    public static function encryptIfNotEmpty(?string $data): ?string
    {
        if (empty($data)) {
            return null;
        }
        return self::encrypt($data);
    }

    /**
     * Decrypt data if it's not empty
     *
     * @param string|null $encrypted The encrypted data
     * @return string|null Decrypted data or null if input is null/empty
     */
    public static function decryptIfNotEmpty(?string $encrypted): ?string
    {
        if (empty($encrypted)) {
            return null;
        }
        return self::decrypt($encrypted);
    }
}
