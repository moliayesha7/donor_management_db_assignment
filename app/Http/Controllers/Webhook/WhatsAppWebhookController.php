<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Donor;
use App\Models\Donation;

class WhatsAppWebhookController extends Controller
{
    /**
     * meta webhook verification 
     */
    public function verify(Request $request)
    {
        if ($request->query('hub_mode') === 'subscribe' && $request->query('hub_verify_token') === env('WHATSAPP_VERIFY_TOKEN')) {
            header('Content-Type: text/plain');
            echo $request->query('hub_challenge');
            exit;
        }
        return response()->json(['error' => 'Verification failed'], 403);
    }

   
  public function handle(Request $request)
{
    // Read PHP raw input directly
    $rawInput = file_get_contents('php://input');
    $payload = json_decode($rawInput, true);

    if (isset($payload['entry'][0]['changes'][0]['value']['messages'][0])) {
        
        $messageData = $payload['entry'][0]['changes'][0]['value']['messages'][0];
        $metadata = $payload['entry'][0]['changes'][0]['value']['metadata'];
        
        $fromNumberId = $metadata['phone_number_id'];
        $rawPhone = (string)$messageData['from'];
        
        // Read Meta's actual sent text body and convert to lowercase
        $incomingMessage = isset($messageData['text']['body']) ? strtolower(trim((string)$messageData['text']['body'])) : '';

        Log::info("Incoming Message: " . $incomingMessage);

        // 1. Condition to check 'status'
        if ($incomingMessage === 'status') {
            $formattedPhone = '+' . $rawPhone; 
            $donor = Donor::where('phone_number', $formattedPhone)->first();

            if ($donor) {
                $lastDonation = Donation::where('donor_id', $donor->id)->latest()->first();

                if ($lastDonation) {
                    $statusEmoji = $lastDonation->status === 'success' ? '✅ Success' : '⏳ Pending';
                    $replyText = "আপনার প্রোফাইল পাওয়া গেছে, *{$donor->name}*! 😊\n\n📍 *সর্বশেষ ডোনেশন বিবরণ:*\n• পরিমাণ: {$lastDonation->amount} BDT\n• মাধ্যম: {$lastDonation->payment_method}\n• স্ট্যাটাস: {$statusEmoji}";
                } else {
                    $replyText = "ধন্যবাদ *{$donor->name}*। আমাদের ডাটাবেজে আপনার প্রোফাইল আছে, তবে কোনো ডোনেশনের রেকর্ড পাওয়া যায়নি।";
                }
            } else {
                $replyText = "দুঃখিত, আপনার নম্বরটি (*{$formattedPhone}*) ডোনার ডাটাবেজে রেজিস্টার্ড নেই।";
            }
            
        // 2. Condition to check 'projects'
        } elseif ($incomingMessage === 'projects' || $incomingMessage === 'project') {
            $replyText = "আমাদের চলমান প্রজেক্টসমূহ:\n১. এতিম শিশুদের শিক্ষা ফান্ড\n২. জরুরি শীতবস্ত্র বিতরণ\n৩. নিরাপদ পানি প্রজেক্ট";
            
        // 3. Show the main menu on 'hi', 'hello', 'start' or first-time message
        } elseif (in_array($incomingMessage, ['hi', 'hello', 'start', 'আসসালামু আলাইকুম'])) {
            $replyText = "আমাদের ডোনার কমিউনিটিতে আপনাকে স্বাগতম! 😊\n\n• আপনার ডোনেশন স্ট্যাটাস লাইভ চেক করতে টাইপ করুন: *Status*\n• আমাদের চলমান প্রজেক্টগুলো দেখতে টাইপ করুন: *Projects*";
            
        // 4. Default guide message when the user types something unexpected
        } else {
            $replyText = "দুঃখিত, আপনার মেসেজটি আমি বুঝতে পারিনি। আমাদের মেইন মেনু দেখতে অনুগ্রহ করে *Hi* লিখে মেসেজ দিন।";
        }

        // Send the response via Meta
        $this->sendWhatsAppMessage($fromNumberId, $rawPhone, $replyText);
    }

    return response()->json(['status' => 'EVENT_RECEIVED'], 200);
}

    /**
     * Private method to call the Meta Graph API
     */
   private function sendWhatsAppMessage($phoneId, $to, $message)
{
    $accessToken = env('WHATSAPP_META_TOKEN');
    $url = "https://graph.facebook.com/v18.0/{$phoneId}/messages";

    // Try with a leading + sign (in case Meta blocks the previous format)
    $cleanTo = preg_replace('/[^0-9]/', '', $to); 

    $data = [
        'messaging_product' => 'whatsapp',
        'recipient_type'    => 'individual',
        'to'                => $cleanTo, 
        'type'              => 'text',
        'text'              => [
            'preview_url' => false,
            'body'        => (string)$message
        ]
    ];

    $postData = json_encode($data);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
        'Content-Length: ' . strlen($postData)
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Print the exact Meta response to the log
    Log::info("Meta API Raw Response Code [{$httpCode}]: " . $result);
}
}