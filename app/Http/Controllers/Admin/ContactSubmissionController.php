<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Admin Contact Submissions",
 *     description="Manage public contact-us form submissions"
 * )
 */
class ContactSubmissionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/contact-submissions",
     *     summary="List contact submissions",
     *     tags={"Admin Contact Submissions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"new","read"})),
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string"), description="Matches name, email or message"),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=20)),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="List of contact submissions")
     * )
     */
    public function index(Request $request)
    {
        $query = ContactSubmission::query();

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->query('per_page', 20), 100);

        return response()->json($query->latest()->paginate($perPage, ['*'], 'page', (int) $request->query('page', 1)));
    }

    /**
     * @OA\Get(
     *     path="/api/admin/contact-submissions/{id}",
     *     summary="Get a contact submission",
     *     tags={"Admin Contact Submissions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Submission detail")
     * )
     */
    public function show($id)
    {
        return response()->json(ContactSubmission::findOrFail($id));
    }

    /**
     * @OA\Put(
     *     path="/api/admin/contact-submissions/{id}",
     *     summary="Mark a contact submission as read/new",
     *     tags={"Admin Contact Submissions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(required={"status"}, @OA\Property(property="status", type="string", enum={"new","read"}))
     *     ),
     *     @OA\Response(response=200, description="Submission updated")
     * )
     */
    public function update(Request $request, $id)
    {
        $submission = ContactSubmission::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:new,read',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $submission->update(['status' => $request->status]);

        return response()->json($submission);
    }
}
