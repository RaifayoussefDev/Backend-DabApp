<?php

namespace App\Http\Controllers;

use App\Models\ContactSubmission;
use Illuminate\Http\Request;

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

        ContactSubmission::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Your message has been submitted successfully.',
        ], 201);
    }
}
