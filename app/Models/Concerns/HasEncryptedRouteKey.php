<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException; 

/**
 * Add `use HasEncryptedRouteKey;` to any Eloquent model and route-model-bound
 * URL parameters for that model will contain an encrypted, opaque token
 * instead of the raw integer ID.
 *
 * - Plain integer IDs still resolve, so internal calls and existing routes keep working.
 * - Each encrypt() produces a different token (Laravel adds a fresh IV), so the same
 *   donation looks different in every log line — IDs cannot be enumerated.
 *
 * To use:
 *   class Donation extends Model { use HasEncryptedRouteKey; }
 *   Route::get('donations/{donation}/receipt', ...)   // controller signature: (Donation $donation)
 *   URL::route('donations.receipt', ['donation' => $donation])   // passes the model, not its id
 */
trait HasEncryptedRouteKey
{
    public function getRouteKey()
    {
        return Crypt::encryptString((string) $this->getKey());
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $column = $field ?? $this->getKeyName();

        // Accept raw numeric IDs (e.g. from internal API tests, /me redirects, etc).
        // Production URLs go through the encrypted branch below.
        if (ctype_digit((string) $value)) {
            return $this->newQuery()->where($column, $value)->firstOrFail();
        }

        try {
            $decrypted = Crypt::decryptString($value);
        } catch (DecryptException $e) { // [UPDATED] Catch specific Exception instead of generic \Throwable
            abort(404);
        }

        return $this->newQuery()->where($column, $decrypted)->firstOrFail();
    }
}
