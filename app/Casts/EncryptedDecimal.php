<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Stores a numeric value as encrypted ciphertext on disk but exposes it
 * as a float in PHP. Use on financial columns (e.g. donations.amount).
 *
 * Side effect: the column cannot participate in SQL SUM/AVG/WHERE — every
 * encrypt() yields a fresh IV, so ciphertext is non-deterministic. Aggregate
 * in PHP after fetching rows.
 */
class EncryptedDecimal implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?float
    {
        if ($value === null || $value === '') return null;

        try {
            $plain = Crypt::decryptString($value);
        } catch (DecryptException $e) {
            // Tolerate legacy plaintext rows so the column remains readable
            // during the encrypt-existing-data migration window.
            $plain = $value;
        }

        return is_numeric($plain) ? (float) $plain : null;
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null || $value === '') return null;
        return Crypt::encryptString((string) (float) $value);
    }
}
