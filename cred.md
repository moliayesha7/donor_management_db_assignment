Demo credentials

Role	Email	Password
super_admin	super@gmail.com	password
admin	admin@gmail.com	12345678
accountant	accountant@gmail.com	password
user	user@gmail.com	password


Permission matrix recap

Action	super_admin	admin	accountant	user
View Users tab	✓	✓		
Create users	✓	✓		
Edit users	✓	✓		
Delete users	✓			
Self-delete				


To use in production:

Change .env MAIL_MAILER=log → smtp (with MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD)
Run php artisan queue:work as a daemon (Supervisor on Linux / Windows Task Scheduler) so dispatched jobs get processed
Failed jobs land in the failed_jobs table — inspect with php artisan queue:failed
Trigger events (donation confirmed → auto-send thank-you email) — Laravel event listener

Note on the discovery quirk: Laravel 12's withCommands() path-discovery flow wasn't picking up the command on this Windows setup — explicit registration in AppServiceProvider::boot() via $this->commands([...]) works reliably. The closure-style Artisan::command(...) in routes/console.php likewise wasn't firing for either inspire or my command; the service-provider path is more reliable here.

To run in production: enable Laravel's scheduler by adding one cron entry — * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1. On Windows dev box, run php artisan schedule:work in a separate terminal alongside php artisan queue:work.

Want me to move on to #3 Receipt PDFs next?


/////////////////
How to test both pipelines
Quick check (one event, both listeners fire)

# 1. Clear stale state (optional, useful when iterating)
php artisan queue:flush
php artisan tinker --execute="DB::table('jobs')->delete();DB::table('whatsapp_logs')->delete();DB::table('email_logs')->delete();"

# 2. Fire a DonationConfirmed event
php artisan tinker --execute="
\$d = App\Models\Donor::whereNotNull('email')->whereNotNull('phone_number')->first();
\$donation = App\Models\Donation::create([
    'donor_id' => \$d->id,
    'project_id' => App\Models\Project::first()->id,
    'amount' => 500,
    'payment_method' => 'Stripe',
    'transaction_date' => now(),
    'receipt_number' => 'REC-TEST-' . time(),
    'status' => 'confirmed',
]);
App\Events\DonationConfirmed::dispatch(\$donation);
"

# 3. Process the queue (should see 2 listener jobs + 1 child WhatsApp job, all DONE)
php artisan queue:work --stop-when-empty --tries=1

# 4. Verify side effects
php artisan tinker --execute="
foreach (DB::table('whatsapp_logs')->get() as \$l) echo \$l->recipient_number . ' [' . \$l->status . ']' . PHP_EOL;
echo 'Failed jobs: ' . DB::table('failed_jobs')->count() . PHP_EOL;
"
Test via the real HTTP endpoint

# Get a token first (replace email/password with a seeded admin)
curl -s -X POST http://127.0.0.1:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'

# POST /api/donations with status=confirmed → triggers the event automatically
curl -X POST http://127.0.0.1:8000/api/donations \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"donor_id":1,"project_id":1,"amount":500,"payment_method":"Stripe","transaction_date":"2026-05-21 10:00:00","status":"confirmed"}'
Or in the UI: open Donations in the sidebar → Record Donation → set status to Confirmed → Save. Then run php artisan queue:work --stop-when-empty to flush.

What success looks like
Output	Meaning
App\Listeners\SendDonationThankYouEmail ... DONE	Gmail accepted the message
App\Listeners\SendDonationThankYouWhatsapp ... DONE	Template body assembled, child job dispatched
App\Jobs\SendWhatsappMessageJob ... DONE	WhatsappService delivered (log driver writes to storage/logs/laravel.log)
Row in whatsapp_logs with status=sent	Delivery captured
Failed jobs: 0	No retries needed
Where to look when it fails
Symptom	Where to check
Email listener fails with 535-5.7.8	Gmail app password — regenerate at Google App Passwords and update MAIL_PASSWORD
Email listener times out >20s	Wrong port / blocked outbound 465 — check MAIL_PORT and firewall
WhatsApp job fails with Unknown WhatsApp driver	WHATSAPP_DRIVER has stray chars in .env (was caused by trailing comma earlier)
Job runs but whatsapp_logs is empty	App\Jobs\SendWhatsappMessageJob::failed() wasn't called either — check php artisan queue:failed
Running the worker continuously in dev

# Terminal 1 — Laravel scheduler (picks up "Later" emails every minute)
php artisan schedule:work

# Terminal 2 — Queue worker (processes all dispatched jobs)
php artisan queue:work
Note: the email_logs table stays empty for these trigger-based sends because the SendDonationThankYouEmail listener calls Mail::to()->send() directly. The bulk-send pipeline (SendBulkEmailJob) is what writes to email_logs. Want me to add logging to the trigger listener too so confirmation emails show up in email_logs for audit?


////////////////////

Step 4: Get Meta WhatsApp credentials
A. Create the App (one-time)
Go to https://developers.facebook.com/apps → click Create App
Pick Business as the app type → name it (e.g. "Donor Mgmt WhatsApp") → continue
On the app dashboard, find WhatsApp → click Set up
You'll land on WhatsApp → API Setup. Three things you need from this page:
Field	Where on the page
Temporary access token	Big purple button at the top (24h validity, regenerable). Copy this → WHATSAPP_META_TOKEN
Phone number ID	Below "From" — looks like 1234567890123456. Copy → WHATSAPP_META_PHONE_NUMBER_ID
Test phone number	Meta gives you a free sender (e.g. +1 555 …). This is the from number — Meta-owned, no action needed
B. Add +8801813235452 as a test recipient
On the API Setup page, under "To" dropdown → Manage phone number list → Add phone number → enter +8801813235452 → enter the verification code Meta SMSes you. You can add up to 5 recipients on the free tier.

C. Paste credentials into .env

WHATSAPP_DRIVER=meta
WHATSAPP_META_TOKEN=EAAxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
WHATSAPP_META_PHONE_NUMBER_ID=123456789012345
WHATSAPP_META_API_VERSION=v21.0
Then:


php artisan config:clear
Step 5: Test the real delivery

# Send a single message directly via WhatsappService (bypasses queue, fastest feedback)
php artisan tinker --execute="
\$svc = app(App\Services\WhatsappService::class);
\$result = \$svc->send('+8801813235452', 'Hello from Donor Management! This is a Meta WhatsApp test at ' . now());
print_r(\$result);
"
Expected success output:


Array
(
    [status] => sent
    [provider_id] => wamid.HBgM...
)
Your phone should buzz within a few seconds.

If you get an error like:

Error	Meaning / Fix
(#131030) Recipient phone number not in allowed list	Add the number under "Manage phone number list" (Step B)
Invalid OAuth access token	Token expired (24h temp tokens) — regenerate it on the API Setup page, update .env, run php artisan config:clear
(#100) Param to must be a valid phone number	Strip the + from the number — the service already does this with ltrim, but verify the recipient added on Meta matches
(#132000) Number of parameters does not match expected	You triggered a template path. The free-form text path requires a 24h conversation window — for your first message, recipient must reply once to the test number, or use a pre-approved template
Important — Meta's "24-hour window" rule: you can only send free-form text after the recipient has messaged you within the last 24 hours. For first contact you need an approved template (Meta provides hello_world by default). If your test message fails because of this, ask +8801813235452 to first send any message to Meta's test number, then re-run within 24h.

Step 6: Test the full event-driven pipeline
Once direct send works, fire the event-driven path:


php artisan queue:flush
php artisan tinker --execute="
DB::table('jobs')->delete(); DB::table('whatsapp_logs')->delete();
\$d = App\Models\Donor::where('phone_number', '+8801813235452')->first()
   ?: App\Models\Donor::whereNotNull('phone_number')->first();
\$donation = App\Models\Donation::create([
    'donor_id' => \$d->id,
    'project_id' => App\Models\Project::first()->id,
    'amount' => 1000, 'payment_method' => 'Stripe',
    'transaction_date' => now(),
    'receipt_number' => 'REC-META-' . time(),
    'status' => 'confirmed',
]);
App\Events\DonationConfirmed::dispatch(\$donation);
echo 'Sent to: ' . \$d->phone_number . PHP_EOL;
"
php artisan queue:work --stop-when-empty --tries=1
Then check whatsapp_logs — you should see a row with status=sent and provider_id starting with wamid.… (Meta's message ID).

Tell me once you've grabbed the token + phone-number-ID and pasted them into .env — I'll run the direct-send test with you and we can debug any provider errors in real time.

seeder data in package.json:
  "dev": "concurrently \"php artisan serve\" \"vite\" \"nodemon --watch database/migrations --ext php --exec \\\"php artisan migrate:fresh --seed\\\"\"",