<?php

namespace App\Http\Controllers\Assist\Admin;

use App\Http\Controllers\Assist\AssistBaseController;
use App\Models\Assist\AssistPriceConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Assist - Admin Price Config",
 *     description="Manage the global price range and step for assist proposals"
 * )
 */
class AdminPriceConfigController extends AssistBaseController
{
    /**
     * @OA\Get(
     *     path="/api/assist/admin/price-config",
     *     summary="Get the current assist price configuration",
     *     description="Returns the global min price, max price, step value, and the list of valid proposal prices.",
     *     tags={"Assist - Admin Price Config"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Current price configuration",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id",           type="integer", example=1),
     *                 @OA\Property(property="price_min",    type="integer", example=0),
     *                 @OA\Property(property="price_max",    type="integer", example=150),
     *                 @OA\Property(property="price_step",   type="integer", example=50),
     *                 @OA\Property(property="valid_prices", type="array",
     *                     description="All valid proposal prices (multiples of step between min and max)",
     *                     @OA\Items(type="integer"),
     *                     example={0, 50, 100, 150}
     *                 ),
     *                 @OA\Property(property="updated_at",   type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show(): JsonResponse
    {
        $config = AssistPriceConfig::current();

        return $this->success(array_merge($config->toArray(), [
            'valid_prices' => $config->validPrices(),
        ]));
    }

    /**
     * @OA\Patch(
     *     path="/api/assist/admin/price-config",
     *     summary="Update the assist price configuration",
     *     description="Update min price, max price, and/or step. All proposed prices must be multiples of step and within [min, max]. The step must divide evenly into (max - min).",
     *     tags={"Assist - Admin Price Config"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             example={"price_min": 0, "price_max": 200, "price_step": 50},
     *             @OA\Property(property="price_min",  type="integer", minimum=0,   example=0,
     *                 description="Minimum allowed proposal price (0 = free / bénévolat)"),
     *             @OA\Property(property="price_max",  type="integer", minimum=1,   example=200,
     *                 description="Maximum allowed proposal price"),
     *             @OA\Property(property="price_step", type="integer", minimum=1,   example=50,
     *                 description="Increment step. Proposals must be 0, step, 2×step, … up to max")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Configuration updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success",  type="boolean", example=true),
     *             @OA\Property(property="message",  type="string",  example="Price configuration updated."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="price_min",    type="integer", example=0),
     *                 @OA\Property(property="price_max",    type="integer", example=200),
     *                 @OA\Property(property="price_step",   type="integer", example=50),
     *                 @OA\Property(property="valid_prices", type="array",
     *                     @OA\Items(type="integer"),
     *                     example={0, 50, 100, 150, 200}
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'price_min'  => 'sometimes|integer|min:0',
            'price_max'  => 'sometimes|integer|min:1',
            'price_step' => 'sometimes|integer|min:1',
        ]);

        $config = AssistPriceConfig::current();
        $config->fill($data);

        if ($config->price_min >= $config->price_max) {
            return $this->error('price_min must be strictly less than price_max.', 422);
        }

        $config->save();

        return $this->success(array_merge($config->toArray(), [
            'valid_prices' => $config->validPrices(),
        ]), 'Price configuration updated.');
    }
}
