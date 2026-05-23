<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Http\Requests\StoreEmailTemplateRequest;
use App\Http\Requests\UpdateEmailTemplateRequest;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    /**
     * READ (List & Search)
     */
    public function index(Request $request)
    {
        $query = EmailTemplate::query();

        // লাইভ সার্চ ফিল্টার
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $templates = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data'    => $templates
        ], 200);
    }

    /**
     * CREATE
     */
    public function store(StoreEmailTemplateRequest $request)
    {
        // যদি কারেন্ট টেমপ্লেট ডিফল্ট সেট হয়, তবে বাকিদের ডিফল্ট ফ্ল্যাগ রিসেট হবে
        if ($request->is_default) {
            EmailTemplate::where('is_default', true)->update(['is_default' => false]);
        }

        $template = EmailTemplate::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Email Template created successfully!',
            'data'    => $template
        ], 201);
    }

    /**
     * READ (Single Item)
     */
    public function show($id)
    {
        $template = EmailTemplate::findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $template
        ], 200);
    }

    /**
     * UPDATE
     */
    public function update(UpdateEmailTemplateRequest $request, $id)
    {
        $template = EmailTemplate::findOrFail($id);

        // যদি এই টেমপ্লেটটিকে ডিফল্ট বানানো হয়, বাকিগুলোর ডিফল্ট ফ্ল্যাগ বাতিল হবে
        if ($request->is_default) {
            EmailTemplate::where('id', '!=', $id)->where('is_default', true)->update(['is_default' => false]);
        }

        $template->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Email Template updated successfully!',
            'data'    => $template
        ], 200);
    }

    /**
     * DELETE
     */
    public function destroy($id)
    {
        $template = EmailTemplate::findOrFail($id);
        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Email Template deleted successfully!'
        ], 200);
    }
}