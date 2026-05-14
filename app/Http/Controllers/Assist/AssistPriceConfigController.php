<?php

namespace App\Http\Controllers\Assist;

use App\Models\Assist\AssistPriceConfig;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Assist - Price Config",
 *     description="Public price range config — available to both Helper and Seeker"
 * )
 */
class AssistPriceConfigController extends AssistBaseController
{
    /**
     * @OA\Get(
     *     path="/api/assist/price-config",
     *     summary="Get the current assist price range",
     *     description="Returns the global minimum price, maximum price, step increment, and the full list of valid proposal prices. Both Helper and Seeker can call this endpoint. Use it to display the price range on the request screen and to build the helper's price picker.",
     *     tags={"Assist - Price Config"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Current price configuration",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="price_min",    type="integer", example=0,
     *                     description="Minimum allowed proposal price (0 = free / volunteer)"),
     *                 @OA\Property(property="price_max",    type="integer", example=150,
     *                     description="Maximum allowed proposal price"),
     *                 @OA\Property(property="price_step",   type="integer", example=50,
     *                     description="Increment between valid prices"),
     *                 @OA\Property(property="valid_prices", type="array",
     *                     description="Exact list of prices the helper can propose. Use these values directly as buttons in the UI.",
     *                     @OA\Items(type="integer"),
     *                     example={0, 50, 100, 150}
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show(): JsonResponse
    {
        $config = AssistPriceConfig::current();

        return $this->success([
            'price_min'    => $config->price_min,
            'price_max'    => $config->price_max,
            'price_step'   => $config->price_step,
            'valid_prices' => $config->validPrices(),
        ]);
    }
}
