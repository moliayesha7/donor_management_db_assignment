<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = Activity::with('causer')->latest();

        if ($subject = $request->query('subject_type')) {
            $query->where('subject_type', $subject);
        }

        if ($event = $request->query('event')) {
            $query->where('event', $event);
        }

        $logs = $query->paginate($request->query('per_page', 25));

        $logs->getCollection()->transform(function (Activity $a) {
            return [
                'id'           => $a->id,
                'description'  => $a->description,
                'event'        => $a->event,
                'subject_type' => class_basename($a->subject_type),
                'subject_id'   => $a->subject_id,
                'causer'       => $a->causer ? ['id' => $a->causer->id, 'name' => $a->causer->name] : null,
                'changes'      => $a->properties,
                'created_at'   => $a->created_at,
            ];
        });

        return response()->json(['success' => true, 'data' => $logs]);
    }
}
