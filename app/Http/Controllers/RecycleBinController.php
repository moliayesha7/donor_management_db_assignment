<?php

namespace App\Http\Controllers;

use App\Models\Donation;
use App\Models\Donor;
use App\Models\Expense;
use App\Models\Project;
use App\Models\ProjectType;
use App\Models\Student;
use Illuminate\Http\Request;

class RecycleBinController extends Controller
{
    /**
     * Map URL type slugs to model class + display fields. Add a new entry here
     * (plus SoftDeletes on the model) to extend the recycle bin.
     */
    protected array $registry = [
        'donors'        => ['model' => Donor::class,       'label' => 'name'],
        'projects'      => ['model' => Project::class,     'label' => 'name'],
        'project-types' => ['model' => ProjectType::class, 'label' => 'name'],
        'donations'     => ['model' => Donation::class,    'label' => 'receipt_number'],
        'students'      => ['model' => Student::class,     'label' => 'student_name'],
        'expenses'      => ['model' => Expense::class,     'label' => 'category'],
    ];

    /**
     * GET /api/recycle-bin — counts per type + the latest 100 trashed across all types.
     */
    public function index()
    {
        $counts = [];
        $items  = collect();

        foreach ($this->registry as $type => $cfg) {
            $count = $cfg['model']::onlyTrashed()->count();
            $counts[$type] = $count;

            if ($count > 0) {
                $trashed = $cfg['model']::onlyTrashed()
                    ->latest('deleted_at')
                    ->take(100)
                    ->get()
                    ->map(fn ($m) => [
                        'type'       => $type,
                        'id'         => $m->id,
                        'label'      => $m->{$cfg['label']} ?? "#{$m->id}",
                        'deleted_at' => $m->deleted_at,
                    ]);
                $items = $items->concat($trashed);
            }
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'counts' => $counts,
                'items'  => $items->sortByDesc('deleted_at')->values(),
                'types'  => array_keys($this->registry),
            ],
        ]);
    }

    /**
     * GET /api/recycle-bin/{type} — full list for a single type.
     */
    public function showType(string $type)
    {
        $cfg = $this->resolve($type);
        $rows = $cfg['model']::onlyTrashed()->latest('deleted_at')->get();

        return response()->json([
            'success' => true,
            'data'    => $rows,
        ]);
    }

    /**
     * POST /api/recycle-bin/{type}/{id}/restore — undo the soft-delete.
     */
    public function restore(string $type, $id)
    {
        $cfg = $this->resolve($type);
        $row = $cfg['model']::onlyTrashed()->findOrFail($id);
        $row->restore();

        return response()->json([
            'success' => true,
            'message' => 'Restored successfully.',
            'data'    => $row,
        ]);
    }

    /**
     * DELETE /api/recycle-bin/{type}/{id} — permanently remove.
     */
    public function forceDelete(string $type, $id)
    {
        $cfg = $this->resolve($type);
        $row = $cfg['model']::onlyTrashed()->findOrFail($id);
        $row->forceDelete();

        return response()->json([
            'success' => true,
            'message' => 'Permanently deleted.',
        ]);
    }

    /**
     * POST /api/recycle-bin/empty — purge every trashed record across every type.
     * Restricted to permission:recycle-bin.delete.
     */
    public function empty()
    {
        $total = 0;
        foreach ($this->registry as $cfg) {
            $total += $cfg['model']::onlyTrashed()->forceDelete();
        }

        return response()->json([
            'success' => true,
            'message' => "Permanently removed {$total} records.",
        ]);
    }

    protected function resolve(string $type): array
    {
        if (!isset($this->registry[$type])) {
            abort(404, "Unknown recycle-bin type '{$type}'.");
        }
        return $this->registry[$type];
    }
}
