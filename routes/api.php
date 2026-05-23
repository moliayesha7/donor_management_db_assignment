<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\RecycleBinController;
use App\Http\Controllers\DonorController;
use App\Http\Controllers\DonorSourceController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\NotificationRetryController;
use App\Http\Controllers\NotificationWebhookController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectTypeController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SmsTemplateController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WhatsappTemplateController;
use App\Http\Controllers\Webhook\WhatsAppWebhookController;
use Spatie\Activitylog\Models\Activity;

// Public auth endpoints
Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);

// Public-but-signed financial downloads (signature is the access token here)
Route::get('donations/{donation}/receipt', [ReceiptController::class, 'download'])->name('donations.receipt');
Route::get('backup/download',              [BackupController::class, 'download'])->name('backup.download');

// Public provider delivery callbacks (no auth)
Route::match(['get', 'post'], 'webhooks/meta/whatsapp', [NotificationWebhookController::class, 'meta']);

Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify'])->withoutMiddleware([\App\Http\Middleware\Authenticate::class, 'auth:sanctum']);
Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle'])->withoutMiddleware([\App\Http\Middleware\Authenticate::class, 'auth:sanctum']);

// Protected endpoints (Sanctum bearer token)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    // Projects
    Route::get('projects',              [ProjectController::class, 'index'])->middleware('permission:projects.view');
    Route::get('projects/{project}',    [ProjectController::class, 'show'])->middleware('permission:projects.view');
    Route::post('projects',             [ProjectController::class, 'store'])->middleware('permission:projects.create');
    Route::put('projects/{project}',    [ProjectController::class, 'update'])->middleware('permission:projects.edit');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->middleware('permission:projects.delete');

    // Project types
    Route::get('project-types',                   [ProjectTypeController::class, 'index'])->middleware('permission:project-types.view');
    Route::get('project-types/{project_type}',    [ProjectTypeController::class, 'show'])->middleware('permission:project-types.view');
    Route::post('project-types',                  [ProjectTypeController::class, 'store'])->middleware('permission:project-types.create');
    Route::put('project-types/{project_type}',    [ProjectTypeController::class, 'update'])->middleware('permission:project-types.edit');
    Route::delete('project-types/{project_type}', [ProjectTypeController::class, 'destroy'])->middleware('permission:project-types.delete');

    // Donor sources
    Route::get('donor-sources',                     [DonorSourceController::class, 'index'])->middleware('permission:donor-sources.view');
    Route::get('donor-sources/{donor_source}',      [DonorSourceController::class, 'show'])->middleware('permission:donor-sources.view');
    Route::post('donor-sources',                    [DonorSourceController::class, 'store'])->middleware('permission:donor-sources.create');
    Route::put('donor-sources/{donor_source}',      [DonorSourceController::class, 'update'])->middleware('permission:donor-sources.edit');
    Route::delete('donor-sources/{donor_source}',   [DonorSourceController::class, 'destroy'])->middleware('permission:donor-sources.delete');

    // Schedules and Logs Endpoints
    Route::get('email-schedules', [EmailController::class, 'getSchedules']);
    Route::get('email-logs', [EmailController::class, 'getLogs']);

    // Students
    Route::get('students',              [StudentController::class, 'index'])->middleware('permission:students.view');
    Route::get('students/{student}',    [StudentController::class, 'show'])->middleware('permission:students.view');
    Route::post('students',             [StudentController::class, 'store'])->middleware('permission:students.create');
    Route::put('students/{student}',    [StudentController::class, 'update'])->middleware('permission:students.edit');
    Route::delete('students/{student}', [StudentController::class, 'destroy'])->middleware('permission:students.delete');

    // Donations
    Route::get('campaigns',               [CampaignController::class, 'index'])->middleware('permission:campaigns.view');
    Route::get('campaigns/{campaign}',    [CampaignController::class, 'show'])->middleware('permission:campaigns.view');
    Route::post('campaigns',              [CampaignController::class, 'store'])->middleware('permission:campaigns.create');
    Route::put('campaigns/{campaign}',    [CampaignController::class, 'update'])->middleware('permission:campaigns.edit');
    Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy'])->middleware('permission:campaigns.delete');

    Route::get('donations',               [DonationController::class, 'index'])->middleware('permission:donations.view');
    Route::get('donations/{donation}',    [DonationController::class, 'show'])->middleware('permission:donations.view');
    Route::post('donations',              [DonationController::class, 'store'])->middleware('permission:donations.create');
    Route::put('donations/{donation}',    [DonationController::class, 'update'])->middleware('permission:donations.edit');
    Route::delete('donations/{donation}', [DonationController::class, 'destroy'])->middleware('permission:donations.delete');

    // SMS Logs and Schedules Endpoints
    Route::get('sms-schedules', [SmsTemplateController::class, 'getSchedules']);
    Route::get('sms-logs', [SmsTemplateController::class, 'getLogs']);

    // Manual retry for failed notification sends
    Route::post('sms-logs/{smsLog}/retry',      [NotificationRetryController::class, 'retrySms'])->middleware('permission:notifications.send');
    Route::post('whatsapp-logs/{whatsappLog}/retry', [NotificationRetryController::class, 'retryWhatsapp'])->middleware('permission:notifications.send');
    Route::post('email-logs/{emailLog}/retry',    [NotificationRetryController::class, 'retryEmail'])->middleware('permission:notifications.send');

    // Emails
    Route::get('emails',           [EmailController::class, 'index'])->middleware('permission:emails.view');
    Route::post('emails',          [EmailController::class, 'store'])->middleware('permission:emails.create');
    Route::get('emails/{email}',      [EmailController::class, 'show'])->middleware('permission:emails.view');
    Route::put('emails/{email}',      [EmailController::class, 'update'])->middleware('permission:emails.edit');
    Route::delete('emails/{email}',   [EmailController::class, 'destroy'])->middleware('permission:emails.delete');

    // Email templates
    Route::get('email-templates',           [EmailTemplateController::class, 'index'])->middleware('permission:email-templates.view');
    Route::post('email-templates',          [EmailTemplateController::class, 'store'])->middleware('permission:email-templates.create');
    Route::get('email-templates/{emailTemplate}',      [EmailTemplateController::class, 'show'])->middleware('permission:email-templates.view');
    Route::put('email-templates/{emailTemplate}',      [EmailTemplateController::class, 'update'])->middleware('permission:email-templates.edit');
    Route::delete('email-templates/{emailTemplate}',   [EmailTemplateController::class, 'destroy'])->middleware('permission:email-templates.delete');

    // SMS templates
    Route::get('sms-templates',             [SmsTemplateController::class, 'index'])->middleware('permission:sms-templates.view');
    Route::post('sms-templates',            [SmsTemplateController::class, 'store'])->middleware('permission:sms-templates.create');
    Route::get('sms-templates/{smsTemplate}',        [SmsTemplateController::class, 'show'])->middleware('permission:sms-templates.view');
    Route::put('sms-templates/{smsTemplate}',        [SmsTemplateController::class, 'update'])->middleware('permission:sms-templates.edit');
    Route::delete('sms-templates/{smsTemplate}',     [SmsTemplateController::class, 'destroy'])->middleware('permission:sms-templates.delete');
    Route::post('sms/send',                 [SmsTemplateController::class, 'send'])->middleware('permission:sms.send');

    // WhatsApp
    Route::get('whatsapp-templates',                       [WhatsappTemplateController::class, 'index'])->middleware('permission:whatsapp-templates.view');
    Route::post('whatsapp-templates',                      [WhatsappTemplateController::class, 'store'])->middleware('permission:whatsapp-templates.create');
    Route::get('whatsapp-templates/{whatsapp_template}',   [WhatsappTemplateController::class, 'show'])->middleware('permission:whatsapp-templates.view');
    Route::put('whatsapp-templates/{whatsapp_template}',   [WhatsappTemplateController::class, 'update'])->middleware('permission:whatsapp-templates.edit');
    Route::delete('whatsapp-templates/{whatsapp_template}',[WhatsappTemplateController::class, 'destroy'])->middleware('permission:whatsapp-templates.delete');
    Route::post('whatsapp/send',                           [WhatsappTemplateController::class, 'send'])->middleware('permission:whatsapp.send');
    Route::get('whatsapp/logs',                            [WhatsappTemplateController::class, 'getLogs'])->middleware('permission:whatsapp.view');

    // Donors
    Route::get('donors',            [DonorController::class, 'index'])->middleware('permission:donors.view');
    Route::get('donors/{donor}',    [DonorController::class, 'show'])->middleware('permission:donors.view');
    Route::post('donors',           [DonorController::class, 'store'])->middleware('permission:donors.create');
    Route::put('donors/{donor}',    [DonorController::class, 'update'])->middleware('permission:donors.edit');
    Route::delete('donors/{donor}', [DonorController::class, 'destroy'])->middleware('permission:donors.delete');

    // Expenses
    Route::get('expenses',              [ExpenseController::class, 'index'])->middleware('permission:expenses.view');
    Route::get('expenses/{expense}',    [ExpenseController::class, 'show'])->middleware('permission:expenses.view');
    Route::post('expenses',             [ExpenseController::class, 'store'])->middleware('permission:expenses.create');
    Route::put('expenses/{expense}',    [ExpenseController::class, 'update'])->middleware('permission:expenses.edit');
    Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->middleware('permission:expenses.delete');

    // Reports (Part 7: Project Reports)
    Route::get('reports/project-wise',          [ReportController::class, 'projectWise'])->middleware('permission:reports.view');
    Route::get('reports/project/{project}/detail',   [ReportController::class, 'projectDetail'])->middleware('permission:reports.view');
    Route::get('reports/donation-summary',      [ReportController::class, 'donationSummary'])->middleware('permission:reports.view');

    // Reports (Part 8: Financial reports)
    Route::get('reports/cash-flow',              [ReportController::class, 'cashFlow'])->middleware('permission:reports.view');
    Route::get('reports/donation-ledger',        [ReportController::class, 'donationLedger'])->middleware('permission:reports.view');
    Route::get('reports/project-balance',        [ReportController::class, 'projectBalance'])->middleware('permission:reports.view');
    Route::get('reports/financial-reconciliation', [ReportController::class, 'financialReconciliation'])->middleware('permission:reports.view');

    // Bank Reconciliation (Part 8)
    Route::get('reconciliation/template',                    [ReconciliationController::class, 'template'])->middleware('permission:reconciliation.view');
    Route::get('reconciliation/uploads',                     [ReconciliationController::class, 'index'])->middleware('permission:reconciliation.view');
    Route::post('reconciliation/uploads',                    [ReconciliationController::class, 'store'])->middleware('permission:reconciliation.upload');
    Route::get('reconciliation/uploads/{reconciliationUpload}',                [ReconciliationController::class, 'show'])->middleware('permission:reconciliation.view');
    Route::delete('reconciliation/uploads/{reconciliationUpload}',             [ReconciliationController::class, 'destroy'])->middleware('permission:reconciliation.upload');
    Route::get('reconciliation/unmatched',                   [ReconciliationController::class, 'unmatched'])->middleware('permission:reconciliation.view');
    Route::post('reconciliation/transactions/{transaction}/match',    [ReconciliationController::class, 'matchTransaction'])->middleware('permission:reconciliation.match');

    // Receipts (signed-URL issuer; download itself is public-signed above)
    Route::get('donations/{donation}/receipt-url', [ReceiptController::class, 'signedUrl'])->middleware('permission:receipts.download');

    // Recycle bin (soft-deleted records)
    Route::get('recycle-bin',                       [RecycleBinController::class, 'index'])->middleware('permission:recycle-bin.view');
    Route::get('recycle-bin/{type}',                [RecycleBinController::class, 'showType'])->middleware('permission:recycle-bin.view');
    Route::post('recycle-bin/{type}/{model}/restore',  [RecycleBinController::class, 'restore'])->middleware('permission:recycle-bin.restore');
    Route::delete('recycle-bin/{type}/{model}',        [RecycleBinController::class, 'forceDelete'])->middleware('permission:recycle-bin.force_delete');
    Route::post('recycle-bin/empty',                [RecycleBinController::class, 'empty'])->middleware('permission:recycle-bin.force_delete');

    // Data backup (super_admin only — see RoleSeeder; signed download above is public)
    Route::get('backup/url', [BackupController::class, 'signedUrl'])->middleware('permission:backup.create');

    // Users & Roles
    Route::get('roles',          [RoleController::class, 'index'])->middleware('permission:users.view');
    Route::get('users',          [UserController::class, 'index'])->middleware('permission:users.view');
    Route::get('users/{user}',   [UserController::class, 'show'])->middleware('permission:users.view');
    Route::post('users',         [UserController::class, 'store'])->middleware('permission:users.create');
    Route::put('users/{user}',   [UserController::class, 'update'])->middleware('permission:users.edit');
    Route::delete('users/{user}',[UserController::class, 'destroy'])->middleware('permission:users.delete');

    Route::get('activity-logs', [ActivityLogController::class, 'index'])
         ->middleware('permission:audit-logs.view');

});
