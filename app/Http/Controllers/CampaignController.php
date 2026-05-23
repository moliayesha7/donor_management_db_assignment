<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Models\Campaign;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index(Request $request)
    {
        $query = Campaign::query();

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
        }

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json(['success' => true, 'data' => $query->latest()->get()]);
    }

    public function store(StoreCampaignRequest $request)
    {
        $campaign = Campaign::create($request->validated());
        return response()->json(['success' => true, 'message' => 'Campaign created', 'data' => $campaign], 201);
    }

    public function show(Campaign $campaign)
    {
        return response()->json(['success' => true, 'data' => $campaign]);
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign)
    {
        $campaign->update($request->validated());
        return response()->json(['success' => true, 'message' => 'Campaign updated', 'data' => $campaign]);
    }

    public function destroy(Campaign $campaign)
    {
        $campaign->delete();
        return response()->json(['success' => true, 'message' => 'Campaign deleted']);
    }
}
