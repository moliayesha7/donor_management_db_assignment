<?php

namespace App\Listeners;

use App\Events\DonorRegistered;
use App\Events\DonationFailed;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWhatsAppTemplateNotification
{
    public function handle(object $event): void
    {
        $eventClass = class_basename($event); // e.g., "DonorRegistered" or "DonationFailed"
        
        //use this event whats app template found from database
        $template = WhatsAppTemplate::where('trigger_event', $eventClass)
                                    ->where('status', 'approved')
                                    ->first();

        if (!$template) {
            Log::info("WhatsApp System: No approved template assigned for event [{$eventClass}]");
            return;
        }

        $toPhoneNumber = '';
        $parameters = [];

        // dynamic variable prepare as per event
        if ($event instanceof DonorRegistered) {
            $donor = $event->donor;
            $toPhoneNumber = $donor->phone_number; // Number stored with leading + in the database (e.g., +8801813...)
            
            // Build the values for {{1}} and {{2}} in the Meta template
            $parameters = [
                ['type' => 'text', 'text' => $donor->name],         // {{1}}
                ['type' => 'text', 'text' => $donor->phone_number], // {{2}}
            ];
        } 
        
        elseif ($event instanceof DonationFailed) {
            $donation = $event->donation;
            $donor = $donation->donor; // The Donation model must define a donor() relation
            $project = $donation->project; // The Donation model must define a project() relation
            
            $toPhoneNumber = $donor->phone_number;
            $projectName = $project ? $project->name : 'সাধারণ তহবিল';

            // Build the values for {{1}}, {{2}}, {{3}}, {{4}} in the Meta template
            $parameters = [
                ['type' => 'text', 'text' => $donor->name],                      // {{1}}
                ['type' => 'text', 'text' => $projectName],                      // {{2}}
                ['type' => 'text', 'text' => (string)$donation->amount],         // {{3}}
                ['type' => 'text', 'text' => url("/donations/retry/{$donation->id}")], // {{4}}
            ];
        }

        if (empty($toPhoneNumber)) {
            Log::error("WhatsApp System: Recipient phone number is empty for event [{$eventClass}]");
            return;
        }

        // 3. Push the final dynamic template payload to the Meta Cloud API
        $this->sendMetaTemplateMessage($toPhoneNumber, $template->name, $template->language, $parameters);
    }

    /**
     * Method to call the Meta template message API (uses cURL to avoid object-marshalling issues)
     */
    private function sendMetaTemplateMessage($to, $templateName, $languageCode, $parameters)
        {
        
            $phoneId = env('WHATSAPP_PHONE_NUMBER_ID') ?: '1127356333791272'; 
            
         
            $accessToken = env('WHATSAPP_META_TOKEN'); 
            
            // URL setup
            $url = "https://graph.facebook.com/v18.0/{$phoneId}/messages";

            // Keep only digits in the number (stripping the + sign is safer for Meta templates)
            $cleanTo = preg_replace('/[^0-9]/', '', $to);

            $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $cleanTo,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode
                ],
          
               'components' => [
                [
                    'type' => 'body',
                    'parameters' => $parameters // Parameters built in your listener go here
                ]
            ]
            ]
            ];

            $postData = json_encode($payload);

            // Log the URL to make debugging easier
            \Illuminate\Support\Facades\Log::info("WhatsApp Sending URL: " . $url);

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

            if ($httpCode >= 200 && $httpCode < 300) {
                \Illuminate\Support\Facades\Log::info("WhatsApp Template [{$templateName}] successfully sent to {$cleanTo}");
            } else {
                \Illuminate\Support\Facades\Log::error("WhatsApp Template Error Code [{$httpCode}]: " . $result);
            }
        }
}