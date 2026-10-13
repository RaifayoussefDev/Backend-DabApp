<?php

namespace App\Http\Controllers;

use App\Mail\ContactSubmissionMail;
use App\Models\ContactSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * @OA\Tag(
 *     name="Contact",
 *     description="Public contact form submissions"
 * )
 */
class ContactController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/contact-us",
     *     summary="Submit a contact form message",
     *     description="Public endpoint — no authentication required.",
     *     operationId="submitContactUs",
     *     tags={"Contact"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","message"},
     *             @OA\Property(property="name",    type="string", example="John Doe"),
     *             @OA\Property(property="email",   type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="message", type="string", example="I have a question about...")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Message submitted"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|max:255',
            'message' => 'required|string|max:3000',
        ]);

        $submission = ContactSubmission::create($validated);

        // Tell the team (CONTACT_RECEIVER in .env). The message is already saved and listed in
        // the admin panel, so a mail failure is logged and never fails the customer's request.
        $receiver = config('mail.contact_receiver');
        if ($receiver) {
            try {
                Mail::to($receiver)->send(new ContactSubmissionMail($submission));
            } catch (\Throwable $e) {
                Log::error('Contact form: notification email failed', [
                    'submission_id' => $submission->id,
                    'error'         => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Your message has been submitted successfully.',
        ], 201);
    }
}
