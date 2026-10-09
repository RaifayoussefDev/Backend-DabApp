<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\PrepareMotorcycleImportJob;
use App\Jobs\ProcessMotorcycleImportChunkJob;
use App\Models\MotorcycleImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="Admin Motorcycle Import",
 *     description="Bulk upsert of the motorcycle catalog (brands / models / years / specs) from the scraper spreadsheet. Runs in background chunks; poll the import for progress."
 * )
 */
class MotorcycleImportAdminController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/motorcycle-imports",
     *     tags={"Admin Motorcycle Import"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15)),
     *     @OA\Response(response=200, description="Import history")
     * )
     */
    public function index(Request $request)
    {
        $imports = MotorcycleImport::with(['admin:id,first_name,last_name,email', 'defaultType:id,name'])
            ->latest('id')
            ->paginate((int) $request->get('per_page', 15));

        $imports->getCollection()->each->makeHidden('row_errors');

        return response()->json(['success' => true, 'data' => $imports]);
    }

    /**
     * Upload the spreadsheet and start the import in background.
     *
     * Existing motorcycles (matched by Make + Model + Year) are updated when a
     * value changed, new ones are inserted. Nothing is ever deleted.
     *
     * @OA\Post(
     *     path="/api/admin/motorcycle-imports",
     *     tags={"Admin Motorcycle Import"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="multipart/form-data",
     *         @OA\Schema(required={"file","default_type_id"},
     *             @OA\Property(property="file", type="string", format="binary", description=".xlsx or .csv, max 100MB"),
     *             @OA\Property(property="default_type_id", type="integer", description="Type given to NEW models (the sheet has no category column)")
     *         )
     *     )),
     *     @OA\Response(response=202, description="Import queued"),
     *     @OA\Response(response=409, description="Another import is already running"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'file'            => 'required|file|mimes:xlsx,csv,txt|max:102400',
            'default_type_id' => 'required|integer|exists:motorcycle_types,id',
        ]);

        $running = MotorcycleImport::whereIn('status', [
            MotorcycleImport::STATUS_QUEUED,
            MotorcycleImport::STATUS_PREPARING,
            MotorcycleImport::STATUS_PROCESSING,
        ])->first();

        if ($running) {
            return response()->json([
                'success' => false,
                'message' => 'Another import is already running. Wait for it to finish or cancel it.',
                'data'    => $running,
            ], 409);
        }

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';

        $import = MotorcycleImport::create([
            'admin_id'        => $request->user()?->id,
            'original_name'   => $file->getClientOriginalName(),
            'file_path'       => '',
            'status'          => MotorcycleImport::STATUS_QUEUED,
            'default_type_id' => (int) $request->default_type_id,
        ]);

        $path = $file->storeAs("motorcycle-imports/{$import->id}", "source.{$ext}", 'local');
        $import->update(['file_path' => $path]);

        PrepareMotorcycleImportJob::dispatch($import->id);

        return response()->json([
            'success' => true,
            'message' => 'Import started. It runs in background, you can follow the progress here.',
            'data'    => $import->fresh(),
        ], 202);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/motorcycle-imports/{id}",
     *     tags={"Admin Motorcycle Import"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Import status, counters and row errors")
     * )
     */
    public function show(int $id)
    {
        $import = MotorcycleImport::with(['admin:id,first_name,last_name,email', 'defaultType:id,name'])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $import]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/motorcycle-imports/{id}/cancel",
     *     tags={"Admin Motorcycle Import"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Import cancelled (rows already committed stay)"),
     *     @OA\Response(response=422, description="Import is not running")
     * )
     */
    public function cancel(int $id)
    {
        $import = MotorcycleImport::findOrFail($id);

        if (!$import->isRunning()) {
            return response()->json(['success' => false, 'message' => 'This import is not running.'], 422);
        }

        $import->update([
            'status'      => MotorcycleImport::STATUS_CANCELLED,
            'finished_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Import cancelled.', 'data' => $import]);
    }

    /**
     * Restart a failed/cancelled import from its last checkpoint.
     *
     * @OA\Post(
     *     path="/api/admin/motorcycle-imports/{id}/resume",
     *     tags={"Admin Motorcycle Import"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Import resumed"),
     *     @OA\Response(response=422, description="Import cannot be resumed")
     * )
     */
    public function resume(int $id)
    {
        $import = MotorcycleImport::findOrFail($id);

        if (!in_array($import->status, [MotorcycleImport::STATUS_FAILED, MotorcycleImport::STATUS_CANCELLED], true)) {
            return response()->json(['success' => false, 'message' => 'Only failed or cancelled imports can be resumed.'], 422);
        }

        $disk = Storage::disk('local');

        // Failed during preparation (or data file gone): prepare again from the source file.
        if (!$import->data_path || !$disk->exists($import->data_path)) {
            if (!$import->file_path || !$disk->exists($import->file_path)) {
                return response()->json(['success' => false, 'message' => 'The uploaded file is no longer available, upload it again.'], 422);
            }

            $import->update([
                'status'        => MotorcycleImport::STATUS_QUEUED,
                'error_message' => null,
                'finished_at'   => null,
            ]);
            PrepareMotorcycleImportJob::dispatch($import->id);
        } else {
            $import->update([
                'status'        => MotorcycleImport::STATUS_PROCESSING,
                'error_message' => null,
                'finished_at'   => null,
            ]);
            ProcessMotorcycleImportChunkJob::dispatch($import->id, $import->processed_rows);
        }

        return response()->json(['success' => true, 'message' => 'Import resumed.', 'data' => $import->fresh()]);
    }
}
