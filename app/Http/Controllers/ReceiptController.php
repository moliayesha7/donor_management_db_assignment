<?php

namespace App\Http\Controllers;

use App\Models\Donation;
use App\Services\ReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class ReceiptController extends Controller
{
    public function __construct(protected ReceiptService $service) {}

    /**
     * Returns a temporary, shareable signed URL for the receipt download.
     * Front-end calls this (Sanctum-authenticated), then redirects the
     * browser to the signed URL — which works in a plain <a> tag.
     */
    public function signedUrl(Donation $donation)
    {
        $url = URL::temporarySignedRoute(
            'donations.receipt',
            now()->addMinutes(15),
            // Pass the model — Laravel calls $donation->getRouteKey() which is
            // the encrypted token (HasEncryptedRouteKey).
            ['donation' => $donation]
        );

        return response()->json([
            'success' => true,
            'data'    => ['url' => $url, 'expires_in' => 900],
        ]);
    }

    /**
     * Public-but-signed download. Throws 403 if signature is missing/invalid/expired.
     */
    public function download(Request $request, Donation $donation)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Receipt link expired or tampered with.');
        }

        $contents = $this->service->buildXlsx($donation);
        $filename = 'Receipt_' . $donation->receipt_number . '.xlsx';

        return response($contents, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
