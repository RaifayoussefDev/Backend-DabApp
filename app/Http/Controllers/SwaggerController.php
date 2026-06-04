<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="DabApp API",
 *     version="1.0.0",
 *     description="Documentation de l'API Application avec Swagger",
 *     @OA\Contact(
 *         email="contact@dabapp.com"
 *     )
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter your JWT token. Example: eyJ0eXAiOiJKV1Qi..."
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="API Server"
 * )
 */
class SwaggerController extends Controller
{
    // Ce contrôleur peut être vide, il sert juste à contenir l'annotation Swagger
}
