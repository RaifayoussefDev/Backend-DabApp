<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Listing;
use App\Models\AuctionHistory;
use App\Models\BankCard;
use App\Models\BikePartBrand;
use App\Models\BikePartCategory;
use App\Models\CurrencyExchangeRate;
use App\Models\LicensePlate;
use App\Models\LicensePlateValue;
use App\Models\Motorcycle;
use App\Models\MotorcycleBrand;
use App\Models\MotorcycleModel;
use App\Models\MotorcycleType;
use App\Models\Payment;
use App\Models\PricingRulesLicencePlate;
use App\Models\PricingRulesMotorcycle;
use App\Models\PricingRulesSparepart;
use App\Models\SparePart;
use App\Models\SparePartMotorcycle;
use App\Models\Submission;
use App\Models\SubmissionOption;
use CreatePricingRulesLicencePlateTable;
use CreatePricingRulesSparepartTable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;


class ListingController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/listings/motorcycle",
     *     summary="Créer ou mettre à jour une annonce de moto (multi-étapes)",
     *     tags={"Listings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="step", type="integer", example=1, description="Étape actuelle (1, 2 ou 3)"),
     *             @OA\Property(property="listing_id", type="integer", example=42, description="Obligatoire à partir de l'étape 2"),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="title", type="string", example="Yamaha MT-07"),
     *             @OA\Property(property="description", type="string", example="Moto bien entretenue"),
     *             @OA\Property(property="price", type="number", format="float", example=5000),
     *             @OA\Property(property="country_id", type="integer", example=1),
     *             @OA\Property(property="city_id", type="integer", example=10),
     *             @OA\Property(property="auction_enabled", type="boolean", example=true),
     *             @OA\Property(property="minimum_bid", type="number", example=4000),
     *             @OA\Property(property="allow_submission", type="boolean", example=true),
     *             @OA\Property(property="listing_type_id", type="integer", example=1),
     *             @OA\Property(property="contacting_channel", type="string", example="phone"),
     *             @OA\Property(property="seller_type", type="string", example="owner"),
     *             @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="brand_id", type="integer", example=1),
     *             @OA\Property(property="model_id", type="integer", example=2),
     *             @OA\Property(property="year_id", type="integer", example=2020),
     *             @OA\Property(property="engine", type="string", example="700cc"),
     *             @OA\Property(property="mileage", type="integer", example=15000),
     *             @OA\Property(property="body_condition", type="string", example="Bon état"),
     *             @OA\Property(property="modified", type="boolean", example=false),
     *             @OA\Property(property="insurance", type="boolean", example=true),
     *             @OA\Property(property="general_condition", type="string", example="Excellent"),
     *             @OA\Property(property="vehicle_care", type="string", example="Toujours au garage"),
     *             @OA\Property(property="transmission", type="string", example="Manuelle")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Annonce moto enregistrée ou mise à jour avec succès"
     *     )
     * )
     *
     * @OA\Post(
     *     path="/api/listings/spare-part",
     *     summary="Créer ou mettre à jour une annonce de pièce détachée (multi-étapes)",
     *     tags={"Listings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="step", type="integer", example=1),
     *             @OA\Property(property="listing_id", type="integer", example=45),
     *             @OA\Property(property="category_id", type="integer", example=2),
     *             @OA\Property(property="title", type="string", example="Pneu arrière"),
     *             @OA\Property(property="description", type="string", example="Pneu en bon état"),
     *             @OA\Property(property="price", type="number", example=200),
     *             @OA\Property(property="country_id", type="integer", example=1),
     *             @OA\Property(property="city_id", type="integer", example=5),
     *             @OA\Property(property="auction_enabled", type="boolean", example=false),
     *             @OA\Property(property="minimum_bid", type="number", example=null),
     *             @OA\Property(property="allow_submission", type="boolean", example=false),
     *             @OA\Property(property="listing_type_id", type="integer", example=2),
     *             @OA\Property(property="contacting_channel", type="string", example="email"),
     *             @OA\Property(property="seller_type", type="string", example="dealer"),
     *             @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="condition", type="string", example="used"),
     *             @OA\Property(
     *                 property="motorcycles",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="brand_id", type="integer", example=1),
     *                     @OA\Property(property="model_id", type="integer", example=2),
     *                     @OA\Property(property="year_id", type="integer", example=2020)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Annonce pièce détachée enregistrée ou mise à jour avec succès"
     *     )
     * )
     *
     * @OA\Post(
     *     path="/api/listings/license-plate",
     *     summary="Créer ou mettre à jour une annonce de plaque d'immatriculation (multi-étapes)",
     *     tags={"Listings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="step", type="integer", example=1),
     *             @OA\Property(property="listing_id", type="integer", example=46),
     *             @OA\Property(property="category_id", type="integer", example=3),
     *             @OA\Property(property="title", type="string", example="Plaque personnalisée"),
     *             @OA\Property(property="description", type="string", example="Plaque ABC123 rouge"),
     *             @OA\Property(property="price", type="number", example=800),
     *             @OA\Property(property="country_id", type="integer", example=1),
     *             @OA\Property(property="city_id", type="integer", example=8),
     *             @OA\Property(property="auction_enabled", type="boolean", example=true),
     *             @OA\Property(property="minimum_bid", type="number", example=500),
     *             @OA\Property(property="allow_submission", type="boolean", example=true),
     *             @OA\Property(property="listing_type_id", type="integer", example=3),
     *             @OA\Property(property="contacting_channel", type="string", example="whatsapp"),
     *             @OA\Property(property="seller_type", type="string", example="owner"),
     *             @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="plate_format_id", type="integer", example=1),
     *             @OA\Property(property="country_id_lp", type="integer", example=1),
     *             @OA\Property(property="city_id_lp", type="integer", example=1),
     *             @OA\Property(
     *                 property="fields",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="field_id", type="integer", example=1),
     *                     @OA\Property(property="value", type="string", example="ABC123")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Annonce plaque immatriculation enregistrée ou mise à jour avec succès"
     *     )
     * )
     */
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $sellerId = Auth::id();
            if (!$sellerId) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $step = $request->step ?? 1;
            $listing = $request->listing_id ? Listing::find($request->listing_id) : null;

            if ($listing && $listing->seller_id !== $sellerId) {
                return response()->json(['message' => 'Listing not found or access denied'], 403);
            }

            if (!$listing) {
                $listing = Listing::create([
                    'seller_id' => $sellerId,
                    'status' => 'draft',
                    'step' => $step,
                    'created_at' => now(),
                ]);
            }

            // ✅ Validation selon le step
            if ($step >= 3) {
                // Step paiement - on retire bank_card_id
                $request->validate([
                    'amount' => 'required|numeric|min:1',
                ]);
            } else {
                // Step 1 & 2 : validation des données de base
                $request->validate([
                    'title'       => 'sometimes|string|max:255',
                    'description' => 'sometimes|string',
                    'price'       => 'sometimes|nullable|numeric|min:0',
                    'category_id' => 'sometimes|exists:categories,id',
                    'country_id'  => 'sometimes|exists:countries,id',
                    'city_id'     => 'sometimes|exists:cities,id',
                ]);
            }

            // Remplissage du listing
            $listing->fill(array_filter($request->only([
                'title',
                'description',
                'price',
                'category_id',
                'country_id',
                'city_id',
                'auction_enabled',
                'minimum_bid',
                'allow_submission',
                'listing_type_id',
                'contacting_channel',
                'seller_type'
            ])));
            $listing->step = max($listing->step, $step);
            $listing->save();

            // Gestion des images
            if ($request->has('images')) {
                foreach ($request->images as $imageUrl) {
                    $listing->images()->updateOrCreate(['image_url' => $imageUrl]);
                }
            }

            // ✅ Traitement des données spécifiques selon la catégorie
            $this->handleCategorySpecificData($listing, $request);

            // ✅ Paiement uniquement au step 3 (avec conversion vers AED)
            // ✅ Paiement uniquement au step 3 (avec conversion vers AED)
            if ($step >= 3) {
                $originalAmount = $request->amount;
                $aedAmount = $originalAmount;
                $originalCurrency = 'AED';
                $exchangeRate = 1;

                // Si l'utilisateur est d'un autre pays, convertir vers AED
                $countryId = $request->country_id ?? $listing->country_id;

                if ($countryId && $countryId != 2) { // 2 = UAE
                    $currency = CurrencyExchangeRate::where('country_id', $countryId)->first();

                    if ($currency && $currency->exchange_rate > 0) {
                        $originalCurrency = $currency->currency_code;
                        $exchangeRate = $currency->exchange_rate;

                        // 🔥 LOGIQUE CORRECTE
                        // Si exchange_rate = 0.98, cela signifie : 1 AED = 0.98 SAR
                        // Donc pour convertir SAR → AED : montant_sar / 0.98
                        //
                        // Si exchange_rate = 3.67, cela signifie : 1 AED = 3.67 SAR
                        // Donc pour convertir SAR → AED : montant_sar / 3.67
                        //
                        // Conclusion : TOUJOURS diviser par exchange_rate

                        $aedAmount = round($originalAmount / $exchangeRate, 2);

                        \Log::info("Conversion: {$originalAmount} {$originalCurrency} → {$aedAmount} AED (rate: 1 AED = {$exchangeRate} {$originalCurrency})");
                    }
                }

                $payment = Payment::create([
                    'user_id'         => $sellerId,
                    'listing_id'      => $listing->id,
                    'amount'          => $aedAmount,
                    'original_amount' => $originalAmount,
                    'original_currency' => $originalCurrency, // Ajouter cette colonne si elle n'existe pas
                    'currency'        => 'AED',
                    'payment_status'  => 'pending',
                    'cart_id'         => 'cart_' . time(),
                ]);

                // Payload PayTabs
                $payload = [
                    'profile_id' => (int) config('paytabs.profile_id'),
                    'tran_type' => 'sale',
                    'tran_class' => 'ecom',
                    'cart_id' => $payment->cart_id,
                    'cart_description' => 'Payment for Listing #' . $listing->id,
                    'cart_currency' => 'AED',
                    'cart_amount' => $aedAmount,
                    'customer_details' => [
                        'name' => Auth::user()->name,
                        'email' => Auth::user()->email,
                        'phone' => Auth::user()->phone ?? '000000000',
                        'street1' => 'N/A',
                        'city' => 'N/A',
                        'state' => 'N/A',
                        'country' => 'ARE',
                        'zip' => '00000',
                        'ip' => $request->ip()
                    ],
                    'callback' => route('paytabs.callback'),
                    'return' => route('paytabs.return'),
                ];

                $baseUrl = 'https://secure.paytabs.com/';

                $response = Http::withHeaders([
                    'Authorization' => config('paytabs.server_key'),
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json'
                ])->post($baseUrl . 'payment/request', $payload);

                if (!$response->successful()) {
                    DB::rollBack();
                    return response()->json([
                        'error' => 'Payment request failed',
                        'details' => $response->json()
                    ], 400);
                }

                $data = $response->json();
                $payment->update([
                    'tran_ref' => $data['tran_ref'] ?? null,
                    'payment_status' => 'initiated',
                ]);

                DB::commit();

                return response()->json([
                    'message' => 'Listing saved, waiting for payment',
                    'listing_id' => $listing->id,
                    'payment_id' => $payment->id,
                    'amount_aed' => $aedAmount,
                    'original_amount' => $originalAmount,
                    'original_currency' => $originalCurrency,
                    'exchange_rate' => "1 AED = {$exchangeRate} {$originalCurrency}",
                    'currency' => 'AED',
                    'redirect_url' => $data['redirect_url'] ?? null,
                    'data' => $listing->fresh()->load(['images', 'motorcycle', 'sparePart', 'licensePlate']),
                ], 201);
            }

            // ✅ Step 1 & 2 : pas de paiement
            DB::commit();
            return response()->json([
                'message' => 'Listing saved (no payment yet)',
                'listing_id' => $listing->id,
                'data' => $listing->fresh()->load(['images', 'motorcycle', 'sparePart', 'licensePlate']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to process listing', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * ✅ Méthode pour traiter les données spécifiques à chaque catégorie
     */
    private function handleCategorySpecificData($listing, $request)
    {
        // Déterminer la catégorie (1=moto, 2=pièce détachée, 3=plaque)
        $categoryId = $request->category_id ?? $listing->category_id;

        switch ($categoryId) {
            case 1: // Moto
                $motorcycleData = array_filter($request->only([
                    'brand_id',
                    'model_id',
                    'year_id',
                    'engine',
                    'mileage',
                    'body_condition',
                    'modified',
                    'insurance',
                    'general_condition',
                    'vehicle_care',
                    'transmission'
                ]));

                // ✅ Récupérer le type_id depuis la table motorcycle_models
                if (!empty($motorcycleData['model_id'])) {
                    $model = \App\Models\MotorcycleModel::find($motorcycleData['model_id']);
                    if ($model && $model->type_id) {
                        $motorcycleData['type_id'] = $model->type_id;
                    }
                }

                if (!empty($motorcycleData)) {
                    $listing->motorcycle()->updateOrCreate(
                        ['listing_id' => $listing->id],
                        $motorcycleData
                    );
                }
                break;

            case 2: // Pièce détachée
                $sparePartData = array_filter($request->only([
                    'condition',
                    'bike_part_brand_id',      // ← Added
                    'bike_part_category_id'    // ← Added
                ]));

                if (!empty($sparePartData)) {
                    $sparePart = $listing->sparePart()->updateOrCreate(
                        ['listing_id' => $listing->id],
                        $sparePartData
                    );

                    // Traiter les motos compatibles
                    if ($request->has('motorcycles') && is_array($request->motorcycles)) {
                        $sparePart->motorcycles()->delete(); // Supprimer les anciennes relations

                        foreach ($request->motorcycles as $moto) {
                            $sparePart->motorcycles()->create([
                                'brand_id' => $moto['brand_id'] ?? null,
                                'model_id' => $moto['model_id'] ?? null,
                                'year_id' => $moto['year_id'] ?? null,
                            ]);
                        }
                    }
                }
                break;

            case 3: // Plaque d'immatriculation
                // Map request fields to database fields
                $licensePlateData = [];

                if ($request->has('plate_format_id')) {
                    $licensePlateData['plate_format_id'] = $request->plate_format_id;
                }

                if ($request->has('country_id_lp')) {
                    $licensePlateData['country_id'] = $request->country_id_lp; // Map to correct field
                }

                if ($request->has('city_id_lp')) {
                    $licensePlateData['city_id'] = $request->city_id_lp; // Map to correct field
                }

                // Remove null/empty values
                $licensePlateData = array_filter($licensePlateData);

                if (!empty($licensePlateData)) {
                    $licensePlate = $listing->licensePlate()->updateOrCreate(
                        ['listing_id' => $listing->id],
                        $licensePlateData
                    );

                    // Traiter les champs personnalisés
                    if ($request->has('fields') && is_array($request->fields)) {
                        $licensePlate->fieldValues()->delete(); // Supprimer les anciens champs

                        foreach ($request->fields as $field) {
                            // Only create if we have the required field_id and it's not null
                            if (!empty($field['field_id'])) {
                                $licensePlate->fieldValues()->create([
                                    'plate_format_field_id' => $field['field_id'], // Map to correct field name
                                    'field_value' => $field['value'] ?? '', // Use 'field_value' not 'value'
                                ]);
                            }
                        }
                    }
                }
                break;
        }
    }


    /**
     * @OA\Put(
     *     path="/api/listings/complete/{id}",
     *     summary="Compléter un listing existant",
     *     tags={"Listings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du listing à compléter",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="step", type="integer", example=3, description="Étape à compléter"),
     *             @OA\Property(property="action", type="string", example="complete", description="Action: 'update' ou 'complete'"),
     *             @OA\Property(property="amount", type="number", example=50.00, description="Montant pour step 3"),
     *             @OA\Property(property="title", type="string", example="Titre mis à jour"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="price", type="number"),
     *             @OA\Property(property="images", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Listing complété avec succès"
     *     )
     * )
     */
    public function completeListing(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $sellerId = Auth::id();
            if (!$sellerId) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $listing = Listing::where('id', $id)
                ->where('seller_id', $sellerId)
                ->first();

            if (!$listing) {
                return response()->json(['message' => 'Listing not found'], 404);
            }

            $step = $request->step ?? ($listing->step + 1);
            $action = $request->action ?? 'update'; // 'update' ou 'complete'

            // ✅ Nettoyage du prix AVANT validation
            if ($request->has('price') && $request->price !== null && $request->price !== '') {
                $cleanPrice = str_replace([' ', ','], '', $request->price);
                $cleanPrice = is_numeric($cleanPrice) ? (float)$cleanPrice : null;
                $request->merge(['price' => $cleanPrice]);
            }

            // ✅ Validation selon le step
            if ($step >= 3 && $action === 'complete') {
                $request->validate([
                    'amount' => 'required|numeric|min:1',
                ]);
            } else {
                $request->validate([
                    'title'       => 'sometimes|string|max:255',
                    'description' => 'sometimes|string',
                    'price'       => 'sometimes|nullable|numeric|min:0',
                    'category_id' => 'sometimes|exists:categories,id',
                    'country_id'  => 'sometimes|exists:countries,id',
                    'city_id'     => 'sometimes|exists:cities,id',
                ]);
            }

            // ✅ Remplissage du listing
            $listing->fill(array_filter($request->only([
                'title',
                'description',
                'price',
                'category_id',
                'country_id',
                'city_id',
                'auction_enabled',
                'minimum_bid',
                'allow_submission',
                'listing_type_id',
                'contacting_channel',
                'seller_type'
            ])));

            $listing->step = max($listing->step, $step);
            $listing->save();

            // ✅ Gestion des images
            if ($request->has('images')) {
                $listing->images()->delete();
                foreach ($request->images as $imageUrl) {
                    $listing->images()->create(['image_url' => $imageUrl]);
                }
            }

            // ✅ Traitement des données spécifiques selon la catégorie
            $this->handleCategorySpecificData($listing, $request);

            // ✅ Paiement uniquement au step 3 avec action complete
            if ($step >= 3 && $action === 'complete') {
                $originalAmount = $request->amount;
                $aedAmount = $originalAmount;
                $originalCurrency = 'AED';
                $exchangeRate = 1;

                // Si l'utilisateur est d'un autre pays, convertir vers AED
                $countryId = $request->country_id ?? $listing->country_id;

                if ($countryId && $countryId != 2) { // 2 = UAE
                    $currency = CurrencyExchangeRate::where('country_id', $countryId)->first();

                    if ($currency && $currency->exchange_rate > 0) {
                        $originalCurrency = $currency->currency_code;
                        $exchangeRate = $currency->exchange_rate;

                        // 🔥 LOGIQUE CORRECTE : TOUJOURS diviser par exchange_rate
                        // Si exchange_rate = 0.98, cela signifie : 1 AED = 0.98 SAR
                        // Donc pour convertir SAR → AED : montant_sar / 0.98

                        $aedAmount = round($originalAmount / $exchangeRate, 2);

                        \Log::info("Complete Listing - Conversion: {$originalAmount} {$originalCurrency} → {$aedAmount} AED (rate: 1 AED = {$exchangeRate} {$originalCurrency})");
                    }
                }

                $payment = Payment::create([
                    'user_id'           => $sellerId,
                    'listing_id'        => $listing->id,
                    'amount'            => $aedAmount,
                    'original_amount'   => $originalAmount,
                    'original_currency' => $originalCurrency,
                    'currency'          => 'AED',
                    'payment_status'    => 'pending',
                    'cart_id'           => 'cart_' . time(),
                ]);

                // Payload PayTabs
                $payload = [
                    'profile_id' => (int) config('paytabs.profile_id'),
                    'tran_type' => 'sale',
                    'tran_class' => 'ecom',
                    'cart_id' => $payment->cart_id,
                    'cart_description' => 'Completion Payment for Listing #' . $listing->id,
                    'cart_currency' => 'AED',
                    'cart_amount' => $aedAmount,
                    'customer_details' => [
                        'name' => Auth::user()->name,
                        'email' => Auth::user()->email,
                        'phone' => Auth::user()->phone ?? '000000000',
                        'street1' => 'N/A',
                        'city' => 'N/A',
                        'state' => 'N/A',
                        'country' => 'ARE',
                        'zip' => '00000',
                        'ip' => $request->ip()
                    ],
                    'callback' => route('paytabs.callback'),
                    'return' => route('paytabs.return'),
                ];

                $baseUrl = 'https://secure.paytabs.com/';

                $response = Http::withHeaders([
                    'Authorization' => config('paytabs.server_key'),
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json'
                ])->post($baseUrl . 'payment/request', $payload);

                if (!$response->successful()) {
                    DB::rollBack();
                    return response()->json([
                        'error' => 'Payment request failed',
                        'details' => $response->json()
                    ], 400);
                }

                $data = $response->json();
                $payment->update([
                    'tran_ref' => $data['tran_ref'] ?? null,
                    'payment_status' => 'initiated',
                ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Listing completed, payment initiated',
                    'listing_id' => $listing->id,
                    'payment_id' => $payment->id,
                    'amount_aed' => $aedAmount,
                    'original_amount' => $originalAmount,
                    'original_currency' => $originalCurrency,
                    'exchange_rate' => "1 AED = {$exchangeRate} {$originalCurrency}",
                    'currency' => 'AED',
                    'redirect_url' => $data['redirect_url'] ?? null,
                    'data' => $listing->fresh()->load(['images', 'motorcycle', 'sparePart', 'licensePlate'])
                ], 201);
            }

            // ✅ Simple mise à jour sans paiement (step 1 & 2)
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Listing updated successfully',
                'listing_id' => $listing->id,
                'current_step' => $listing->step,
                'next_step' => min($listing->step + 1, 3),
                'data' => $listing->fresh()->load(['images', 'motorcycle', 'sparePart', 'licensePlate'])
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to complete listing',
                'details' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/listings/country/{country_id}",
     *     summary="Get listings by country",
     *     tags={"Listings"},
     *     @OA\Parameter(
     *         name="country_id",
     *         in="path",
     *         required=true,
     *         description="Country ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of listings",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="price", type="number"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="city", type="string"),
     *                 @OA\Property(property="country", type="string"),
     *                 @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="wishlist", type="boolean")
     *             )
     *         )
     *     )
     * )
     */
    public function getByCountry($country_id)
    {
        $user = Auth::user();

        $listings = Listing::with([
            'images',
            'city',
            'country',
            'motorcycle.brand',
            'motorcycle.model',
            'motorcycle.year',
            'sparePart.brand',
            'sparePart.bikePartCategory',
            'sparePart.motorcycleAssociations.brand',
            'sparePart.motorcycleAssociations.model',
            'sparePart.motorcycleAssociations.year',
            'licensePlate.format',
            'licensePlate.city',
            'licensePlate.fieldValues.formatField'
        ])
            ->where('country_id', $country_id)
            ->where('status', 'published') // ✅ afficher uniquement les annonces publiées
            ->get()
            ->map(function ($listing) use ($user) {
                $isInWishlist = false;
                if ($user) {
                    $isInWishlist = DB::table('wishlists')
                        ->where('user_id', $user->id)
                        ->where('listing_id', $listing->id)
                        ->exists();
                }

                $listingData = [
                    'id' => $listing->id,
                    'title' => $listing->title,
                    'description' => $listing->description,
                    'price' => $listing->price,
                    'category_id' => $listing->category_id,
                    'auction_enabled' => $listing->auction_enabled,
                    'minimum_bid' => $listing->minimum_bid,
                    'allow_submission' => $listing->allow_submission,
                    'listing_type_id' => $listing->listing_type_id,
                    'contacting_channel' => $listing->contacting_channel,
                    'seller_type' => $listing->seller_type,
                    'status' => $listing->status,
                    'created_at' => $listing->created_at->format('Y-m-d H:i:s'),
                    'city' => $listing->city ? $listing->city->name : null,
                    'country' => $listing->country ? $listing->country->name : null,
                    'images' => $listing->images->pluck('image_url'),
                    'wishlist' => $isInWishlist,
                ];

                if ($listing->category_id == 1 && $listing->motorcycle) {
                    $listingData['motorcycle'] = [
                        'brand' => $listing->motorcycle->brand->name ?? null,
                        'model' => $listing->motorcycle->model->name ?? null,
                        'year' => $listing->motorcycle->year->year ?? null,
                        'engine' => $listing->motorcycle->engine,
                        'mileage' => $listing->motorcycle->mileage,
                        'body_condition' => $listing->motorcycle->body_condition,
                        'modified' => $listing->motorcycle->modified,
                        'insurance' => $listing->motorcycle->insurance,
                        'general_condition' => $listing->motorcycle->general_condition,
                        'vehicle_care' => $listing->motorcycle->vehicle_care,
                        'transmission' => $listing->motorcycle->transmission,
                    ];
                } elseif ($listing->category_id == 2 && $listing->sparePart) {
                    $listingData['spare_part'] = [
                        'condition' => $listing->sparePart->condition,
                        'brand' => $listing->sparePart->brand->name ?? null,
                        'category' => $listing->sparePart->bikePartCategory->name ?? null,
                        'compatible_motorcycles' => $listing->sparePart->motorcycleAssociations->map(function ($association) {
                            return [
                                'brand' => $association->brand->name ?? null,
                                'model' => $association->model->name ?? null,
                                'year' => $association->year->year ?? null,
                            ];
                        }),
                    ];
                } elseif ($listing->category_id == 3 && $listing->licensePlate) {
                    $licensePlate = $listing->licensePlate;
                    $listingData['license_plate'] = [
                        'plate_format' => [
                            'id' => $licensePlate->format->id ?? null,
                            'name' => $licensePlate->format->name ?? null,
                            'pattern' => $licensePlate->format->pattern ?? null,
                            'country' => $licensePlate->format->country ?? null,
                        ],
                        'city' => $licensePlate->city->name ?? null,
                        'country_id' => $licensePlate->country_id,
                        'fields' => $licensePlate->fieldValues->map(function ($fieldValue) {
                            return [
                                'field_id' => $fieldValue->formatField->id ?? null,
                                'field_name' => $fieldValue->formatField->field_name ?? null,
                                'field_type' => $fieldValue->formatField->field_type ?? null,
                                'field_label' => $fieldValue->formatField->field_label ?? null,
                                'is_required' => $fieldValue->formatField->is_required ?? null,
                                'max_length' => $fieldValue->formatField->max_length ?? null,
                                'validation_pattern' => $fieldValue->formatField->validation_pattern ?? null,
                                'value' => $fieldValue->field_value,
                            ];
                        })->toArray(),
                    ];
                }

                return $listingData;
            });

        return response()->json($listings);
    }

    /**
     * @OA\Get(
     *     path="/api/listings/by-category/{category_id}",
     *     summary="Get listings by category",
     *     tags={"Listings"},
     *     @OA\Parameter(
     *         name="category_id",
     *         in="path",
     *         required=true,
     *         description="Category ID (1=Motorcycle, 2=SparePart, 3=LicensePlate)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="country",
     *         in="query",
     *         required=false,
     *         description="Filter by country name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="plate_search",
     *         in="query",
     *         required=false,
     *         description="Search in license plate field values (only for category_id=3)",
     *         @OA\Schema(type="string"),
     *         example="123"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of listings",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="searched_country", type="string"),
     *             @OA\Property(property="showing_all_countries", type="boolean"),
     *             @OA\Property(property="total_listings", type="integer"),
     *             @OA\Property(
     *                 property="listings",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="title", type="string"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="price", type="number"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="city", type="string"),
     *                     @OA\Property(property="country", type="string"),
     *                     @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *                     @OA\Property(property="wishlist", type="boolean")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getByCategory($category_id, Request $request)
    {
        $user = Auth::user();
        $countryName = $request->get('country');
        $plateSearch = $request->get('plate_search');
        $showingAllCountries = false;
        $message = '';
    
        // Build the base query
        $query = Listing::where('category_id', $category_id)
            ->where('status', 'published')
            ->orderBy('created_at', 'desc');
    
        // Add license plate field search for category 3 (license plates)
        if ($category_id == 3 && $plateSearch) {
            $query->whereHas('licensePlate', function ($licensePlateQuery) use ($plateSearch) {
                $licensePlateQuery->whereHas('fieldValues', function ($fieldQuery) use ($plateSearch) {
                    $fieldQuery->where('field_value', 'LIKE', '%' . $plateSearch . '%');
                });
            });
        }
    
        // Add country filter if provided
        if ($countryName) {
            $countryFilteredQuery = clone $query;
            $countryFilteredQuery->whereHas('country', function ($q) use ($countryName) {
                $q->where('name', 'LIKE', '%' . $countryName . '%');
            });
    
            $countryListings = $countryFilteredQuery->get();
    
            if ($countryListings->isEmpty()) {
                $listings = $query->get();
                $showingAllCountries = true;
                $message = "No listings found for '{$countryName}'. Showing all countries instead.";
            } else {
                $listings = $countryListings;
                $message = "Showing listings for '{$countryName}'.";
            }
        } else {
            $listings = $query->get();
    
            if ($category_id == 3 && $plateSearch) {
                $message = "Showing license plates containing '{$plateSearch}'.";
            } else {
                $message = "Showing all listings.";
            }
        }
    
        if ($countryName && $category_id == 3 && $plateSearch && !$showingAllCountries) {
            $message = "Showing license plates containing '{$plateSearch}' for '{$countryName}'.";
        }
    
        // ✅ Charger les relations nécessaires selon la catégorie
        $listings->load([
            'images' => function ($query) {
                $query->select('listing_id', 'image_url')->limit(1);
            },
            'category:id,name',
            'country:id,name',
            'city:id,name',
            'country.currencyExchangeRate:id,country_id,currency_symbol'
        ]);
    
        // Charger les relations spécifiques par catégorie
        if ($category_id == 1) {
            // Motorcycles
            $listings->load([
                'motorcycle' => function ($query) {
                    $query->select('id', 'listing_id', 'brand_id', 'model_id', 'year_id', 'type_id', 'engine', 'mileage', 'body_condition', 'modified', 'insurance', 'general_condition', 'vehicle_care', 'transmission')
                        ->with([
                            'brand:id,name',
                            'model:id,name',
                            'year:id,year',
                            'type:id,name'
                        ]);
                }
            ]);
        } elseif ($category_id == 2) {
            // Spare parts
            $listings->load([
                'sparePart' => function ($query) {
                    $query->with([
                        'bikePartBrand:id,name',
                        'bikePartCategory:id,name',
                        'motorcycleAssociations.brand:id,name',
                        'motorcycleAssociations.model:id,name',
                        'motorcycleAssociations.year:id,year'
                    ]);
                }
            ]);
        } elseif ($category_id == 3) {
            // License plates
            $listings->load([
                'licensePlate' => function ($query) {
                    $query->with([
                        'format:id,name,pattern,country',
                        'city:id,name',
                        'country:id,name',
                        'fieldValues.formatField:id,field_name,position,field_type,field_label,is_required,max_length,validation_pattern'
                    ]);
                }
            ]);
        }
    
        // ✅ Pour les enchères, charger la dernière enchère
        $listingIds = $listings->pluck('id');
        $currentBids = DB::table('auction_histories')
            ->whereIn('listing_id', $listingIds)
            ->select('listing_id', DB::raw('MAX(bid_amount) as current_bid'))
            ->groupBy('listing_id')
            ->pluck('current_bid', 'listing_id');
    
        // ✅ Formater les résultats selon le format de filterMotorcycles
        $formattedListings = $listings->map(function ($listing) use ($user, $currentBids) {
            $isInWishlist = false;
    
            if ($user) {
                $isInWishlist = DB::table('wishlists')
                    ->where('user_id', $user->id)
                    ->where('listing_id', $listing->id)
                    ->exists();
            }
    
            // ✅ Déterminer le prix à afficher (comme filterMotorcycles)
            $displayPrice = $listing->price;
            $isAuction = false;
            $currentBid = $currentBids[$listing->id] ?? null;
    
            if (!$displayPrice && $listing->auction_enabled) {
                $displayPrice = $currentBid ?: $listing->minimum_bid;
                $isAuction = true;
            }
    
            // ✅ Récupérer le symbole de devise
            $currencySymbol = $listing->country?->currencyExchangeRate?->currency_symbol ?? 'MAD';
    
            // ✅ Garder toutes les colonnes originales + ajouter les nouvelles
            $baseData = [
                'id' => $listing->id,
                'title' => $listing->title,
                'description' => $listing->description,
                'price' => $listing->price, // ✅ Prix original de la DB
                'category_id' => $listing->category_id,
                'auction_enabled' => $listing->auction_enabled,
                'minimum_bid' => $listing->minimum_bid,
                'allow_submission' => $listing->allow_submission,
                'listing_type_id' => $listing->listing_type_id,
                'contacting_channel' => $listing->contacting_channel,
                'seller_type' => $listing->seller_type,
                'status' => $listing->status,
                'created_at' => $listing->created_at->format('Y-m-d H:i:s'),
                'city' => $listing->city?->name,
                'country' => $listing->country?->name,
                'images' => $listing->images->pluck('image_url'),
                'wishlist' => $isInWishlist,
                
                // ✅ NOUVELLES COLONNES AJOUTÉES (comme filterMotorcycles)
                'display_price' => $displayPrice, // Prix à afficher (prix fixe ou enchère)
                'is_auction' => $isAuction, // Boolean pour identifier les enchères
                'current_bid' => $currentBid, // Montant de la dernière enchère
                'currency' => $currencySymbol, // Symbole de devise
            ];
    
            // ✅ Ajouter les données spécifiques par catégorie
            if ($listing->category_id == 1 && $listing->motorcycle) {
                // Motorcycle data
                $baseData['motorcycle'] = [
                    'brand' => $listing->motorcycle->brand?->name ?? null,
                    'model' => $listing->motorcycle->model?->name ?? null,
                    'year' => $listing->motorcycle->year?->year ?? null,
                    'type' => $listing->motorcycle->type?->name ?? null,
                    'engine' => $listing->motorcycle->engine,
                    'mileage' => $listing->motorcycle->mileage,
                    'body_condition' => $listing->motorcycle->body_condition,
                    'modified' => $listing->motorcycle->modified,
                    'insurance' => $listing->motorcycle->insurance,
                    'general_condition' => $listing->motorcycle->general_condition,
                    'vehicle_care' => $listing->motorcycle->vehicle_care,
                    'transmission' => $listing->motorcycle->transmission,
                ];
            } elseif ($listing->category_id == 2 && $listing->sparePart) {
                // Spare part data
                $baseData['spare_part'] = [
                    'condition' => $listing->sparePart->condition,
                    'brand' => $listing->sparePart->bikePartBrand?->name ?? null,
                    'category' => $listing->sparePart->bikePartCategory?->name ?? null,
                    'compatible_motorcycles' => $listing->sparePart->motorcycleAssociations->map(function ($association) {
                        return [
                            'brand' => $association->brand?->name ?? null,
                            'model' => $association->model?->name ?? null,
                            'year' => $association->year?->year ?? null,
                        ];
                    })->toArray(),
                ];
            } elseif ($listing->category_id == 3 && $listing->licensePlate) {
                // License plate data
                $licensePlate = $listing->licensePlate;
    
                $baseData['license_plate'] = [
                    'plate_format' => [
                        'id' => $licensePlate->format?->id ?? null,
                        'name' => $licensePlate->format?->name ?? null,
                        'pattern' => $licensePlate->format?->pattern ?? null,
                        'country' => $licensePlate->format?->country ?? null,
                    ],
                    'city' => $licensePlate->city?->name ?? null,
                    'country' => $licensePlate->country?->name ?? null,
                    'country_id' => $licensePlate->country_id,
                    'fields' => $licensePlate->fieldValues->map(function ($fieldValue) {
                        return [
                            'field_id' => $fieldValue->formatField?->id ?? null,
                            'field_name' => $fieldValue->formatField?->field_name ?? null,
                            'field_position' => $fieldValue->formatField?->position ?? null,
                            'field_type' => $fieldValue->formatField?->field_type ?? null,
                            'field_label' => $fieldValue->formatField?->field_label ?? null,
                            'is_required' => $fieldValue->formatField?->is_required ?? null,
                            'max_length' => $fieldValue->formatField?->max_length ?? null,
                            'validation_pattern' => $fieldValue->formatField?->validation_pattern ?? null,
                            'value' => $fieldValue->field_value,
                        ];
                    })->toArray(),
                ];
            }
    
            return $baseData;
        });
    
        return response()->json([
            'message' => $message,
            'searched_country' => $countryName,
            'showing_all_countries' => $showingAllCountries,
            'total_listings' => $formattedListings->count(),
            'listings' => $formattedListings
        ]);
    }
    /**
     * @OA\Get(
     *     path="/api/listings/city/{city_id}",
     *     summary="Get listings by city",
     *     tags={"Listings"},
     *     @OA\Parameter(
     *         name="city_id",
     *         in="path",
     *         required=true,
     *         description="City ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of listings",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="price", type="number"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="city", type="string"),
     *                 @OA\Property(property="country", type="string"),
     *                 @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="wishlist", type="boolean")
     *             )
     *         )
     *     )
     * )
     */

    public function getByCity($city_id)
    {
        $user = Auth::user();

        $listings = Listing::with(['images', 'city', 'country'])
            ->where('city_id', $city_id)
            ->where('status', 'published')

            ->get()
            ->map(function ($listing) use ($user) {
                $isInWishlist = false;

                if ($user) {
                    $isInWishlist = DB::table('wishlists')
                        ->where('user_id', $user->id)
                        ->where('listing_id', $listing->id)
                        ->exists();
                }

                return [
                    'id' => $listing->id,
                    'title' => $listing->title,
                    'description' => $listing->description,
                    'price' => $listing->price,
                    'created_at' => $listing->created_at->format('Y-m-d H:i:s'),
                    'city' => $listing->city?->name,
                    'country' => $listing->country?->name,
                    'images' => $listing->images->pluck('image_url'),
                    'wishlist' => $isInWishlist,
                ];
            });

        return response()->json($listings);
    }
    /**
     * @OA\Get(
     *     path="/api/listings/filter",
     *     summary="Filter listings",
     *     tags={"Listings"},
     *     @OA\Parameter(
     *         name="city_id",
     *         in="query",
     *         required=false,
     *         description="City ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="country_id",
     *         in="query",
     *         required=false,
     *         description="Country ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         required=false,
     *         description="Category ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Filtered listings",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="price", type="number"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="city", type="string"),
     *                 @OA\Property(property="country", type="string"),
     *                 @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="wishlist", type="boolean")
     *             )
     *         )
     *     )
     * )
     */
    public function filter(Request $request)
    {
        $user = Auth::user();

        $query = Listing::with(['images', 'city', 'country'])
            ->where('status', 'published'); // ✅ afficher seulement les annonces publiées

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->city_id);
        }

        if ($request->filled('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $listings = $query->get()->map(function ($listing) use ($user) {
            $isInWishlist = false;

            if ($user) {
                $isInWishlist = DB::table('wishlists')
                    ->where('user_id', $user->id)
                    ->where('listing_id', $listing->id)
                    ->exists();
            }

            return [
                'id' => $listing->id,
                'title' => $listing->title,
                'description' => $listing->description,
                'price' => $listing->price,
                'created_at' => $listing->created_at->format('Y-m-d H:i:s'),
                'city' => $listing->city?->name,
                'country' => $listing->country?->name,
                'images' => $listing->images->pluck('image_url'),
                'wishlist' => $isInWishlist,
            ];
        });

        return response()->json($listings);
    }

    /**
     * @OA\Get(
     *     path="/api/listings/latest/{city_id}",
     *     summary="Get latest listings by city",
     *     tags={"Listings"},
     *     @OA\Parameter(
     *         name="city_id",
     *         in="path",
     *         required=true,
     *         description="City ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Latest 10 listings",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="price", type="number"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="city", type="string"),
     *                 @OA\Property(property="country", type="string"),
     *                 @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="wishlist", type="boolean")
     *             )
     *         )
     *     )
     * )
     */
    public function getLastByCity($city_id)
    {
        $user = Auth::user();

        $listings = Listing::with(['images', 'city', 'country'])
            ->where('city_id', $city_id)->where('status', 'published')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(function ($listing) use ($user) {
                $isInWishlist = false;

                if ($user) {
                    $isInWishlist = DB::table('wishlists')
                        ->where('user_id', $user->id)
                        ->where('listing_id', $listing->id)
                        ->exists();
                }

                return [
                    'id' => $listing->id,
                    'title' => $listing->title,
                    'description' => $listing->description,
                    'price' => $listing->price,
                    'created_at' => $listing->created_at->format('Y-m-d H:i:s'),
                    'city' => $listing->city?->name,
                    'country' => $listing->country?->name,
                    'images' => $listing->images->pluck('image_url'),
                    'wishlist' => $isInWishlist,
                ];
            });

        return response()->json($listings);
    }

    /**
     * @OA\Get(
     *     path="/api/listings/{id}",
     *     summary="Get listing by ID",
     *     tags={"Listings"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Listing ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Listing details",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="price", type="number"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="city", type="string"),
     *             @OA\Property(property="country", type="string"),
     *             @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="wishlist", type="boolean"),
     *             @OA\Property(property="category_id", type="integer"),
     *             @OA\Property(property="submission", type="object", nullable=true),
     *             @OA\Property(property="seller", type="object", nullable=true),
     *             @OA\Property(property="motorcycle", type="object", nullable=true),
     *             @OA\Property(property="spare_part", type="object", nullable=true),
     *             @OA\Property(property="license_plate", type="object", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Listing not found"
     *     )
     * )
     */
    public function getById($id)
    {
        $user = Auth::user();

        $listing = Listing::with([
            'images',
            'city',
            'country',
            'seller',
            'motorcycle.brand',
            'motorcycle.model',
            'motorcycle.year',
            'sparePart.bikePartBrand',
            'sparePart.bikePartCategory',
            'sparePart.motorcycleAssociations.brand',
            'sparePart.motorcycleAssociations.model',
            'sparePart.motorcycleAssociations.year',
            'licensePlate.format',
            'licensePlate.city',
            'licensePlate.fieldValues.formatField'
        ])->where('id', $id)
            ->where('status', 'published')
            ->first();

        if (!$listing) {
            return response()->json(['message' => 'Listing not found'], 404);
        }

        $isInWishlist = false;
        if ($user) {
            $isInWishlist = DB::table('wishlists')
                ->where('user_id', $user->id)
                ->where('listing_id', $listing->id)
                ->exists();
        }

        $data = [
            'id' => $listing->id,
            'title' => $listing->title,
            'description' => $listing->description,
            'created_at' => $listing->created_at->format('Y-m-d H:i:s'),
            'city' => $listing->city?->name,
            'country' => $listing->country?->name,
            'images' => $listing->images->pluck('image_url'),
            'wishlist' => $isInWishlist,
            'category_id' => $listing->category_id,
            'allow_submission' => $listing->allow_submission,
            'auction_enabled' => $listing->auction_enabled,
            'minimum_bid' => $listing->minimum_bid,
            'listing_type_id' => $listing->listing_type_id,
            'contacting_channel' => $listing->contacting_channel,
            'seller_type' => $listing->seller_type,
            'status' => $listing->status,
        ];

        if (!$listing->allow_submission) {
            $data['price'] = $listing->price;
        }

        if ($listing->allow_submission) {
            $submissions = DB::table('submissions')
                ->where('listing_id', $listing->id)
                ->get();

            $data['submissions'] = $submissions->map(function ($submission) {
                return [
                    'id' => $submission->id,
                    'user_id' => $submission->user_id,
                    'amount' => $submission->amount,
                    'submission_date' => $submission->submission_date,
                    'status' => $submission->status,
                    'min_soom' => $submission->min_soom,
                ];
            });
        } else {
            $data['seller'] = [
                'id' => $listing->seller?->id,
                'name' => $listing->seller?->first_name . ' ' . $listing->seller?->last_name,
                'email' => $listing->seller?->email,
                'phone' => $listing->seller?->phone,
                'address' => $listing->seller?->address,
                'profile_image' => $listing->seller?->profile_image,
                'member_since' => $listing->seller?->created_at->format('Y-m-d H:i:s'),
            ];
        }

        // Motorcycle category
        if ($listing->category_id == 1 && $listing->motorcycle) {
            $data['motorcycle'] = [
                'brand' => $listing->motorcycle->brand?->name,
                'model' => $listing->motorcycle->model?->name,
                'year' => $listing->motorcycle->year?->year,
                'engine' => $listing->motorcycle->engine,
                'mileage' => $listing->motorcycle->mileage,
                'body_condition' => $listing->motorcycle->body_condition,
                'modified' => $listing->motorcycle->modified,
                'insurance' => $listing->motorcycle->insurance,
                'general_condition' => $listing->motorcycle->general_condition,
                'vehicle_care' => $listing->motorcycle->vehicle_care,
                'transmission' => $listing->motorcycle->transmission,
            ];
        }

        // Spare part category
        if ($listing->category_id == 2 && $listing->sparePart) {
            $data['spare_part'] = [
                'condition' => $listing->sparePart->condition,
                'brand' => $listing->sparePart->bikePartBrand?->name,
                'category' => $listing->sparePart->bikePartCategory?->name,
                'compatible_motorcycles' => $listing->sparePart->motorcycleAssociations->map(function ($association) {
                    return [
                        'brand' => $association->brand?->name,
                        'model' => $association->model?->name,
                        'year' => $association->year?->year,
                    ];
                }),
            ];
        }

        // License plate category
        if ($listing->category_id == 3 && $listing->licensePlate) {
            $licensePlate = $listing->licensePlate;

            $data['license_plate'] = [
                'plate_format' => [
                    'id' => $licensePlate->format?->id,
                    'name' => $licensePlate->format?->name,
                    'pattern' => $licensePlate->format?->pattern,
                    'country' => $licensePlate->format?->country,
                ],
                'city' => $licensePlate->city?->name,
                'country_id' => $licensePlate->country_id,
                'fields' => $licensePlate->fieldValues->map(function ($fieldValue) {
                    return [
                        'field_id' => $fieldValue->formatField?->id,
                        'field_name' => $fieldValue->formatField?->field_name,
                        'position' => $fieldValue->formatField?->position,
                        'character_type' => $fieldValue->formatField?->character_type,
                        'is_required' => $fieldValue->formatField?->is_required,
                        'min_length' => $fieldValue->formatField?->min_length,
                        'max_length' => $fieldValue->formatField?->max_length,
                        'value' => $fieldValue->field_value,
                    ];
                })->toArray(),
            ];
        }

        return response()->json($data);
    }

    public function getDebugInfo($id)
    {
        $user = Auth::user();
        $listing = Listing::find($id);

        $debugInfo = [
            'authenticated_user' => $user ? [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->first_name . ' ' . $user->last_name,
            ] : null,
            'listing' => $listing ? [
                'id' => $listing->id,
                'title' => $listing->title,
                'status' => $listing->status,
            ] : null,
        ];

        if ($user && $listing) {
            // Construire la requête
            $query = DB::table('wishlists')
                ->where('user_id', $user->id)
                ->where('listing_id', $listing->id);

            $sql = $query->toSql();
            $bindings = $query->getBindings();

            $debugInfo['sql_query'] = $sql;
            $debugInfo['bindings'] = $bindings;

            // Exécuter la requête
            $wishlistExists = $query->exists();
            $wishlistData = $query->get();

            $debugInfo['wishlist_check'] = [
                'exists' => $wishlistExists,
                'data' => $wishlistData->toArray(),
                'count' => $wishlistData->count(),
            ];

            // Toutes les wishlists pour cet utilisateur
            $allWishlists = DB::table('wishlists')
                ->where('user_id', $user->id)
                ->get();

            $debugInfo['all_user_wishlists'] = $allWishlists->toArray();

            // Toutes les wishlists pour ce listing
            $allListingWishlists = DB::table('wishlists')
                ->where('listing_id', $listing->id)
                ->get();

            $debugInfo['all_listing_wishlists'] = $allListingWishlists->toArray();
        }

        return response()->json($debugInfo, 200);
    }



    /**
     * @OA\Get(
     *     path="/api/listings",
     *     summary="Get all listings with pagination",
     *     tags={"Listings"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Items per page",
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of listings",
     *         @OA\JsonContent(
     *             @OA\Property(property="current_page", type="integer"),
     *             @OA\Property(property="last_page", type="integer"),
     *             @OA\Property(property="total", type="integer"),
     *             @OA\Property(property="per_page", type="integer"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="title", type="string"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="price", type="number"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="city", type="string"),
     *                     @OA\Property(property="country", type="string"),
     *                     @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *                     @OA\Property(property="wishlist", type="boolean"),
     *                     @OA\Property(property="category_id", type="integer"),
     *                     @OA\Property(
     *                         property="motorcycle",
     *                         type="object",
     *                         nullable=true
     *                     ),
     *                     @OA\Property(
     *                         property="license_plate",
     *                         type="object",
     *                         nullable=true
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */

    public function getAll(Request $request)
    {
        $user = Auth::user();
        $perPage = 10;

        $listings = Listing::with(['images', 'city', 'country'])->where('status', 'published')
            ->paginate($perPage);

        $data = $listings->map(function ($listing) use ($user) {
            $isInWishlist = false;

            if ($user) {
                $isInWishlist = DB::table('wishlists')
                    ->where('user_id', $user->id)
                    ->where('listing_id', $listing->id)
                    ->exists();
            }

            $item = [
                'id' => $listing->id,
                'title' => $listing->title,
                'description' => $listing->description,
                'price' => $listing->price,
                'created_at' => $listing->created_at->format('Y-m-d H:i:s'),
                'city' => $listing->city?->name,
                'country' => $listing->country?->name,
                'images' => $listing->images->pluck('image_url'),
                'wishlist' => $isInWishlist,
                'category_id' => $listing->category_id,
            ];

            if ($listing->category_id == 1) {
                $motorcycle = Motorcycle::with(['brand', 'model', 'year', 'type'])
                    ->where('listing_id', $listing->id)
                    ->first();

                if ($motorcycle) {
                    $item['motorcycle'] = [
                        'engine' => $motorcycle->engine,
                        'mileage' => $motorcycle->mileage,
                        'body_condition' => $motorcycle->body_condition,
                        'modified' => $motorcycle->modified,
                        'insurance' => $motorcycle->insurance,
                        'general_condition' => $motorcycle->general_condition,
                        'vehicle_care' => $motorcycle->vehicle_care,
                        'transmission' => $motorcycle->transmission,
                        'brand' => $motorcycle->brand?->name,
                        'model' => $motorcycle->model?->name,
                        'year' => $motorcycle->year?->year,
                        'type' => $motorcycle->type?->name,
                    ];
                }
            }

            if ($listing->category_id == 3) {
                $plate = LicensePlate::with(['type', 'color'])
                    ->where('listing_id', $listing->id)
                    ->first();

                if ($plate) {
                    $item['license_plate'] = [
                        'characters' => $plate->characters,
                        'digits_count' => $plate->digits_count,
                        'country_id' => $plate->country_id,
                        'type' => $plate->type?->name,
                        'color' => $plate->color?->name,
                    ];
                }
            }

            return $item;
        });

        return response()->json([
            'current_page' => $listings->currentPage(),
            'last_page' => $listings->lastPage(),
            'total' => $listings->total(),
            'per_page' => $listings->perPage(),
            'data' => $data,
        ]);
    }


    /**
     * @OA\Get(
     *     path="/api/my-listing",
     *     summary="Récupérer les annonces de l'utilisateur connecté avec les détails selon la catégorie",
     *     tags={"Listings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des annonces de l'utilisateur",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function my_listing()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $listings = Listing::where('seller_id', $user->id)
            ->with([
                'images',
                'country',
                'city',
                'listingType',
                'motorcycle',       // relation hasOne
                'sparePart.motorcycleAssociations.brand',
                'sparePart.motorcycleAssociations.model',
                'sparePart.motorcycleAssociations.year',
                'licensePlate'
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($listing) {
                $data = [
                    'id' => $listing->id,
                    'title' => $listing->title,
                    'description' => $listing->description,
                    'price' => $listing->price,
                    'category_id' => $listing->category_id,
                    'status' => $listing->status,
                    'created_at' => $listing->created_at,
                    'images' => $listing->images,
                    'country' => $listing->country,
                    'city' => $listing->city,
                    'listing_type' => $listing->listingType,
                ];

                // Ajouter les détails spécifiques à la catégorie
                if ($listing->category_id == 1 && $listing->motorcycle) {
                    $data['details'] = $listing->motorcycle;
                } elseif ($listing->category_id == 2 && $listing->sparePart) {
                    $data['details'] = $listing->sparePart;
                } elseif ($listing->category_id == 3 && $listing->licensePlate) {
                    $data['details'] = $listing->licensePlate;
                } else {
                    $data['details'] = null;
                }

                return $data;
            });

        return response()->json($listings, 200);
    }


    /**
     * @OA\Get(
     *     path="/api/pricing",
     *     summary="Get price by model ID",
     *     tags={"Listings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="model_id",
     *         in="query",
     *         required=true,
     *         description="Model ID"
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         required=true,
     *         description="Category ID"
     *     ),
     *     @OA\Parameter(
     *         name="country_id",
     *         in="query",
     *         required=true,
     *         description="Country ID"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Price details"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not found"
     *     )
     * )
     */


    public function getPriceByModelId(Request $request)
    {
        $modelId = $request->input('model_id');
        $categoryId = $request->input('category_id');
        $countryId = $request->input('country_id');

        $currency = CurrencyExchangeRate::where('country_id', $countryId)->first();

        if (!$currency) {
            return response()->json([
                'message' => 'No exchange rate found for this country'
            ], 404);
        }

        $exchangeRate = $currency->exchange_rate;
        $currencySymbol = $currency->currency_symbol;

        if ($categoryId == 1 && $modelId) {
            $model = MotorcycleModel::find($modelId);

            if (!$model) {
                return response()->json([
                    'message' => 'No motorcycle model found with this ID'
                ], 404);
            }

            $typeId = $model->type_id;

            $pricingRule = PricingRulesMotorcycle::where('motorcycle_type_id', $typeId)->first();

            if (!$pricingRule) {
                return response()->json([
                    'message' => 'No pricing rule found for this motorcycle type'
                ], 404);
            }

            $priceConverted = round($pricingRule->price * $exchangeRate, 2);

            return response()->json([
                'model_id' => $modelId,
                'motorcycle_type_id' => $typeId,
                'price_sar' => $pricingRule->price,
                'converted_price' => $priceConverted,
                'currency_symbol' => $currencySymbol
            ]);
        } elseif ($categoryId == 2 && $modelId) {
            $pricingRule = PricingRulesSparepart::where('bike_part_category_id', $modelId)->first();

            if (!$pricingRule) {
                return response()->json([
                    'message' => 'No pricing rule found for this bike part category'
                ], 404);
            }

            $priceConverted = round($pricingRule->price * $exchangeRate, 2);

            return response()->json([
                'bike_part_category_id' => $modelId,
                'price_sar' => $pricingRule->price,
                'converted_price' => $priceConverted,
                'currency_symbol' => $currencySymbol
            ]);
        }

        // 👇 Ajout pour category_id == 3 (plaque)
        elseif ($categoryId == 3) {
            // Just fetch the first pricing rule from the table
            $pricingRule = PricingRulesLicencePlate::first();

            if (!$pricingRule) {
                return response()->json([
                    'message' => 'No pricing rule found for licence plates'
                ], 404);
            }

            $priceConverted = round($pricingRule->price * $exchangeRate, 2);

            return response()->json([
                'licence_plate_rule_id' => $pricingRule->id,
                'price_sar' => $pricingRule->price,
                'converted_price' => $priceConverted,
                'currency_symbol' => $currencySymbol
            ]);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/brands/listings-count",
     *     summary="Get motorcycle brands with listing count",
     *     tags={"Listings"},
     *     @OA\Response(
     *         response=200,
     *         description="List of motorcycle brands with their listing counts",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="listings_count", type="integer")
     *             )
     *         )
     *     )
     * )
     */

    public function getBrandsWithListingCount()
    {
        $motorcycle_brands = MotorcycleBrand::select('motorcycle_brands.id', 'motorcycle_brands.name')
            ->join('motorcycles', 'motorcycle_brands.id', '=', 'motorcycles.brand_id')
            ->join('listings', 'motorcycles.listing_id', '=', 'listings.id')
            ->where('listings.status', 'published') // Ajouter cette ligne
            ->groupBy('motorcycle_brands.id', 'motorcycle_brands.name')
            ->selectRaw('COUNT(listings.id) as listings_count')
            ->get();

        return response()->json([
            'motorcycle_brands' => $motorcycle_brands
        ]);
    }


    /**
     * @OA\Get(
     *     path="/api/brands/{brandId}/models-with-listings",
     *     summary="Get motorcycle models with listings for a specific brand",
     *     tags={"Listings"},
     *     @OA\Parameter(
     *         name="brandId",
     *         in="path",
     *         required=true,
     *         description="Brand ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of motorcycle models with their listing counts",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="models",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="listings_count", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Brand not found"
     *     )
     * )
     */
    public function getModelsWithListingsByBrand($brandId)
    {
        $brand = MotorcycleBrand::find($brandId);

        if (!$brand) {
            return response()->json([
                'message' => 'Brand not found'
            ], 404);
        }

        $models = MotorcycleModel::select('motorcycle_models.id', 'motorcycle_models.name')
            ->join('motorcycles', 'motorcycle_models.id', '=', 'motorcycles.model_id')
            ->join('listings', 'motorcycles.listing_id', '=', 'listings.id')
            ->where('motorcycles.brand_id', $brandId)
            ->where('listings.status', 'published')
            ->groupBy('motorcycle_models.id', 'motorcycle_models.name')
            ->selectRaw('COUNT(listings.id) as listings_count')
            ->orderBy('motorcycle_models.name')
            ->get();

        return response()->json([
            'brand' => [
                'id' => $brand->id,
                'name' => $brand->name
            ],
            'models' => $models
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/brands/{brandId}/models/{modelId}/years-with-listings",
     *     summary="Get motorcycle years with listings for a specific brand and model",
     *     tags={"Listings"},
     *     @OA\Parameter(
     *         name="brandId",
     *         in="path",
     *         required=true,
     *         description="Brand ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="modelId",
     *         in="path",
     *         required=true,
     *         description="Model ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of years with their listing counts",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="years",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="year_id", type="integer"),
     *                     @OA\Property(property="year", type="integer"),
     *                     @OA\Property(property="listings_count", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Brand or Model not found"
     *     )
     * )
     */
    public function getYearsWithListingsByBrandAndModel($brandId, $modelId)
    {
        $brand = MotorcycleBrand::find($brandId);
        $model = MotorcycleModel::find($modelId);

        if (!$brand || !$model) {
            return response()->json([
                'message' => 'Brand or Model not found'
            ], 404);
        }

        $years = Motorcycle::select('motorcycles.year_id', 'motorcycle_years.year')
            ->join('listings', 'motorcycles.listing_id', '=', 'listings.id')
            ->join('motorcycle_years', 'motorcycles.year_id', '=', 'motorcycle_years.id')
            ->where('motorcycles.brand_id', $brandId)
            ->where('motorcycles.model_id', $modelId)
            ->where('listings.status', 'published')
            ->groupBy('motorcycles.year_id', 'motorcycle_years.year')
            ->selectRaw('COUNT(listings.id) as listings_count')
            ->orderBy('motorcycle_years.year', 'DESC')
            ->get();

        return response()->json([
            'brand' => [
                'id' => $brand->id,
                'name' => $brand->name
            ],
            'model' => [
                'id' => $model->id,
                'name' => $model->name
            ],
            'years' => $years
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/categorie/listings-count",
     *     summary="Get motorcycle categorie with listing count",
     *     tags={"Listings"},
     *     @OA\Response(
     *         response=200,
     *         description="List of motorcycle categorie with their listing counts",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="motorcycle_types",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="listings_count", type="integer")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getTypesWithListingCount()
    {
        $motorcycle_types = MotorcycleType::select('motorcycle_types.id', 'motorcycle_types.name')
            ->join('motorcycles', 'motorcycle_types.id', '=', 'motorcycles.type_id')
            ->join('listings', 'motorcycles.listing_id', '=', 'listings.id')
            ->where('listings.status', 'published')
            ->groupBy('motorcycle_types.id', 'motorcycle_types.name')
            ->selectRaw('COUNT(listings.id) as listings_count')
            ->orderBy('motorcycle_types.name')
            ->get();

        return response()->json([
            'motorcycle_types' => $motorcycle_types
        ]);
    }


    /**
     * @OA\Get(
     *     path="/api/categories/{categoryId}/price-range",
     *     summary="Get price range for a specific category",
     *     description="Retrieve minimum and maximum prices for listings in a specific category",
     *     tags={"Categories"},
     *     @OA\Parameter(
     *         name="categoryId",
     *         in="path",
     *         required=true,
     *         description="Category ID (1=Motorcycle, 2=SparePart, 3=LicensePlate)",
     *         @OA\Schema(
     *             type="integer",
     *             enum={1, 2, 3},
     *             example=1
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Price range retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Price range retrieved successfully"),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="min_price", type="number", format="float", example=5000.00),
     *             @OA\Property(property="max_price", type="number", format="float", example=25000.00),
     *             @OA\Property(property="total_listings", type="integer", example=45)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Invalid category ID",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid category_id. Only categories 1, 2, or 3 are allowed.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to retrieve price range"),
     *             @OA\Property(property="details", type="string", example="Database connection failed")
     *         )
     *     )
     * )
     */
    public function getPriceRangeByCategory($categoryId)
    {
        try {
            // Vérifier si la catégorie existe et est valide
            if (!in_array($categoryId, [1, 2, 3])) {
                return response()->json([
                    'message' => 'Invalid category_id. Only categories 1, 2, or 3 are allowed.',
                ], 422);
            }

            // ✅ Récupérer les prix incluant les minimum_bid des enchères
            $priceRange = Listing::where('category_id', $categoryId)
                ->where('status', 'published')
                ->selectRaw('
                    MIN(
                        CASE
                            WHEN price IS NOT NULL THEN price
                            WHEN auction_enabled = 1 AND minimum_bid IS NOT NULL THEN minimum_bid
                            ELSE NULL
                        END
                    ) as min_price,
                    MAX(
                        CASE
                            WHEN price IS NOT NULL THEN price
                            WHEN auction_enabled = 1 AND minimum_bid IS NOT NULL THEN minimum_bid
                            ELSE NULL
                        END
                    ) as max_price,
                    COUNT(*) as total_listings,
                    SUM(CASE WHEN price IS NOT NULL THEN 1 ELSE 0 END) as fixed_price_listings,
                    SUM(CASE WHEN auction_enabled = 1 AND price IS NULL THEN 1 ELSE 0 END) as auction_listings
                ')
                ->first();

            // Vérifier s'il y a des listings pour cette catégorie
            if ($priceRange->total_listings == 0) {
                return response()->json([
                    'message' => 'No active listings found for this category.',
                    'category_id' => $categoryId,
                    'min_price' => null,
                    'max_price' => null,
                    'total_listings' => 0,
                    'fixed_price_listings' => 0,
                    'auction_listings' => 0
                ], 200);
            }

            return response()->json([
                'message' => 'Price range retrieved successfully',
                'category_id' => $categoryId,
                'min_price' => $priceRange->min_price ? (float) $priceRange->min_price : null,
                'max_price' => $priceRange->max_price ? (float) $priceRange->max_price : null,
                'total_listings' => $priceRange->total_listings,
                'fixed_price_listings' => $priceRange->fixed_price_listings,
                'auction_listings' => $priceRange->auction_listings,
                'currency' => config('paytabs.currency', 'MAD')
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve price range',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    private function getCategoryName($categoryId)
    {
        $categories = [
            1 => 'Motorcycles',
            2 => 'Spare Parts',
            3 => 'License Plates'
        ];

        return $categories[$categoryId] ?? 'Unknown';
    }



    public function getBikePartCategoriesWithListingCount()
    {
        $bike_part_categories = BikePartCategory::select('bike_part_categories.id', 'bike_part_categories.name')
            ->leftJoin('spare_parts', 'bike_part_categories.id', '=', 'spare_parts.bike_part_category_id')
            ->leftJoin('listings', 'spare_parts.listing_id', '=', 'listings.id')
            ->groupBy('bike_part_categories.id', 'bike_part_categories.name')
            ->selectRaw('COUNT(listings.id) as listings_count')
            ->get();

        return response()->json([
            'bike_part_categories' => $bike_part_categories
        ]);
    }

    public function getBikePartBrandsWithListingCount()
    {
        $bike_part_brands = BikePartBrand::select('bike_part_brands.id', 'bike_part_brands.name')
            ->leftJoin('spare_parts', 'bike_part_brands.id', '=', 'spare_parts.bike_part_brand_id')
            ->leftJoin('listings', 'spare_parts.listing_id', '=', 'listings.id')
            ->groupBy('bike_part_brands.id', 'bike_part_brands.name')
            ->selectRaw('COUNT(listings.id) as listings_count')
            ->get();

        return response()->json([
            'bike_part_brands' => $bike_part_brands
        ]);
    }

    /**
     *swagger get
     * @OA\Get(
     *     path="/api/listings/draft",
     *     summary="Get draft listings for the authenticated seller",
     *     tags={"Listings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Draft listings fetched successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Draft listings fetched successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="price", type="number"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="city", type="string"),
     *                 @OA\Property(property="country", type="string"),
     *                 @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="category_id", type="integer"),
     *                 @OA\Property(property="motorcycle", type="object", nullable=true),
     *                 @OA\Property(property="sparePart", type="object", nullable=true),
     *                 @OA\Property(property="licensePlate", type="object", nullable=true)
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to fetch draft listings"),
     *             @OA\Property(property="details", type="string", example="Database connection failed")
     *         )
     *     )
     * )
     */
    public function getDraftListings()
    {
        try {
            $sellerId = Auth::id();
            if (!$sellerId) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $draftListings = Listing::with([
                'images',
                'category',
                'country',
                'city',
                'motorcycle.brand',
                'motorcycle.model',
                'motorcycle.year',
                'motorcycle.type',
                'sparePart.bikePartBrand',
                'sparePart.bikePartCategory',
                'sparePart.motorcycles.brand',
                'sparePart.motorcycles.model',
                'sparePart.motorcycles.year',
                'licensePlate.format',
                'licensePlate.country',
                'licensePlate.city',
                'licensePlate.fieldValues.formatField', // ✅ Fixed: using formatField relationship
            ])
                ->where('seller_id', $sellerId)
                ->where('status', 'draft')
                ->get();

            return response()->json([
                'message' => 'Draft listings fetched successfully',
                'data' => $draftListings
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch draft listings',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Summary of getDraftListingById
     * swagger get
     * @OA\Get(
     *     path="/api/listings/draft/{id}",
     *     summary="Get a specific draft listing by ID for the authenticated seller",
     *     tags={"Listings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the draft listing",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Draft listing fetched successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Draft listing fetched successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=123),
     *                 @OA\Property(property="title", type="string", example="My Listing Title"),
     *                 @OA\Property(property="description", type="string", example="Description of the listing"),
     *                 @OA\Property(property="price", type="number", format="float", example=1999.99),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-07-08T15:30:00Z"),
     *
     *                 @OA\Property(property="city", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Casablanca")
     *                 ),
     *                 @OA\Property(property="country", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Morocco")
     *                 ),
     *
     *                 @OA\Property(property="images", type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=10),
     *                         @OA\Property(property="image_url", type="string", example="https://example.com/image.jpg")
     *                     )
     *                 ),
     *
     *                 @OA\Property(property="category_id", type="integer", example=2),
     *
     *                 @OA\Property(property="motorcycle", type="object", nullable=true),
     *                 @OA\Property(property="sparePart", type="object", nullable=true),
     *                 @OA\Property(property="licensePlate", type="object", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Draft listing not found or access denied",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Draft listing not found or access denied")
     *         )
     *     )
     * )
     */
    public function getDraftListingById($id)
    {
        try {
            $sellerId = Auth::id();
            if (!$sellerId) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $listing = Listing::with([
                'images',
                'category',
                'country',
                'city',
                'motorcycle.brand',
                'motorcycle.model',
                'motorcycle.year',
                'motorcycle.type',
                'sparePart.bikePartBrand',
                'sparePart.bikePartCategory',
                'sparePart.motorcycles.brand',
                'sparePart.motorcycles.model',
                'sparePart.motorcycles.year',
                'licensePlate.format',
                'licensePlate.country',
                'licensePlate.city',
                'licensePlate.fieldValues.formatField', // ✅ Fixed: using formatField relationship
            ])
                ->where('id', $id)
                ->where('seller_id', $sellerId)
                ->where('status', 'draft')
                ->first();

            if (!$listing) {
                return response()->json(['message' => 'Draft listing not found or access denied'], 404);
            }

            return response()->json([
                'message' => 'Draft listing fetched successfully',
                'data' => $listing,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch draft listing',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/listings/draft/{id}",
     *     summary="Delete a single draft listing by ID",
     *     tags={"Listings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the draft listing to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Draft listing deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Draft listing deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Listing not found or not a draft",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Draft listing not found or unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to delete listing"),
     *             @OA\Property(property="details", type="string", example="Database error")
     *         )
     *     )
     * )
     */
    public function deleteDraftListingById($id)
    {
        try {
            $userId = Auth::id();

            $listing = Listing::where('id', $id)
                ->where('seller_id', $userId)
                ->where('status', 'draft')
                ->first();

            if (!$listing) {
                return response()->json([
                    'message' => 'Draft listing not found or unauthorized'
                ], 404);
            }

            // Delete related auction_histories
            AuctionHistory::where('listing_id', $listing->id)->delete();

            // Delete the listing (this will also cascade delete related records if set up properly)
            $listing->delete();

            return response()->json([
                'message' => 'Draft listing deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete listing',
                'details' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * @OA\Get(
     *     path="/api/listings/recent",
     *     summary="Get last 10 listings (all categories)",
     *     tags={"Listings"},
     *     @OA\Parameter(
     *         name="country",
     *         in="query",
     *         required=false,
     *         description="Filter by country name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of recent listings",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="searched_country", type="string"),
     *             @OA\Property(property="showing_all_countries", type="boolean"),
     *             @OA\Property(property="total_listings", type="integer"),
     *             @OA\Property(
     *                 property="listings",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="title", type="string"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="price", type="number"),
     *                     @OA\Property(property="category_id", type="integer"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="city", type="string"),
     *                     @OA\Property(property="country", type="string"),
     *                     @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *                     @OA\Property(property="wishlist", type="boolean"),
     *                     @OA\Property(property="motorcycle", type="object", nullable=true),
     *                     @OA\Property(property="spare_part", type="object", nullable=true),
     *                     @OA\Property(property="license_plate", type="object", nullable=true)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    /**
     * @OA\Get(
     *     path="/api/recent",
     *     summary="Get last 10 listings (all categories)",
     *     tags={"Listings"},
     *     @OA\Parameter(
     *         name="country",
     *         in="query",
     *         required=false,
     *         description="Filter by country name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of recent listings",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="searched_country", type="string"),
     *             @OA\Property(property="showing_all_countries", type="boolean"),
     *             @OA\Property(property="total_listings", type="integer"),
     *             @OA\Property(
     *                 property="listings",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="title", type="string"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="price", type="number"),
     *                     @OA\Property(property="category_id", type="integer"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="city", type="string"),
     *                     @OA\Property(property="country", type="string"),
     *                     @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *                     @OA\Property(property="motorcycle", type="object", nullable=true),
     *                     @OA\Property(property="spare_part", type="object", nullable=true),
     *                     @OA\Property(property="license_plate", type="object", nullable=true)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getRecentListings(Request $request)
    {
        $countryName = $request->get('country');
        $showingAllCountries = false;
        $message = '';

        // Build the base query for all categories
        $query = Listing::with([
            'images',
            'city',
            'country',
            'motorcycle.brand',
            'motorcycle.model',
            'motorcycle.year',
            'sparePart.bikePartBrand',
            'sparePart.bikePartCategory',
            'sparePart.motorcycleAssociations.brand',
            'sparePart.motorcycleAssociations.model',
            'sparePart.motorcycleAssociations.year',
            'licensePlate.format',
            'licensePlate.city',
            'licensePlate.country',
            'licensePlate.fieldValues.formatField'
        ])
            ->where('status', 'published')
            ->orderBy('created_at', 'desc')
            ->limit(10); // Limit to 10 most recent listings

        // Add country filter if provided
        if ($countryName) {
            // First, try to find listings for the specific country
            $countryFilteredQuery = clone $query;
            $countryFilteredQuery->whereHas('country', function ($q) use ($countryName) {
                $q->where('name', 'LIKE', '%' . $countryName . '%');
            });

            $countryListings = $countryFilteredQuery->get();

            if ($countryListings->isEmpty()) {
                // No listings found for this country, show all countries (still limited to 10)
                $listings = $query->get();
                $showingAllCountries = true;
                $message = "No recent listings found for '{$countryName}'. Showing last 10 listings from all countries instead.";
            } else {
                // Listings found for the specific country
                $listings = $countryListings;
                $message = "Showing last " . $listings->count() . " listings for '{$countryName}'.";
            }
        } else {
            // No country filter, show last 10 listings from all countries
            $listings = $query->get();
            $message = "Showing last 10 listings from all categories.";
        }

        $formattedListings = $listings->map(function ($listing) {

            // Base listing data
            $listingData = [
                'id' => $listing->id,
                'title' => $listing->title,
                'description' => $listing->description,
                'price' => $listing->price,
                'category_id' => $listing->category_id,
                'auction_enabled' => $listing->auction_enabled,
                'minimum_bid' => $listing->minimum_bid,
                'allow_submission' => $listing->allow_submission,
                'listing_type_id' => $listing->listing_type_id,
                'contacting_channel' => $listing->contacting_channel,
                'seller_type' => $listing->seller_type,
                'status' => $listing->status,
                'created_at' => $listing->created_at->format('Y-m-d H:i:s'),
                'city' => $listing->city?->name,
                'country' => $listing->country?->name,
                'images' => $listing->images->pluck('image_url'),
            ];

            // Category-specific data
            if ($listing->category_id == 1 && $listing->motorcycle) {
                // Motorcycle data
                $listingData['motorcycle'] = [
                    'brand' => $listing->motorcycle->brand?->name,
                    'model' => $listing->motorcycle->model?->name,
                    'year' => $listing->motorcycle->year?->year,
                    'engine' => $listing->motorcycle->engine,
                    'mileage' => $listing->motorcycle->mileage,
                    'body_condition' => $listing->motorcycle->body_condition,
                    'modified' => $listing->motorcycle->modified,
                    'insurance' => $listing->motorcycle->insurance,
                    'general_condition' => $listing->motorcycle->general_condition,
                    'vehicle_care' => $listing->motorcycle->vehicle_care,
                    'transmission' => $listing->motorcycle->transmission,
                ];
            } elseif ($listing->category_id == 2 && $listing->sparePart) {
                // Spare part data
                $listingData['spare_part'] = [
                    'condition' => $listing->sparePart->condition,
                    'brand' => $listing->sparePart->bikePartBrand?->name,
                    'category' => $listing->sparePart->bikePartCategory?->name,
                    'compatible_motorcycles' => $listing->sparePart->motorcycleAssociations->map(function ($association) {
                        return [
                            'brand' => $association->brand?->name,
                            'model' => $association->model?->name,
                            'year' => $association->year?->year,
                        ];
                    }),
                ];
            } elseif ($listing->category_id == 3 && $listing->licensePlate) {
                // License plate data with format and field values
                $licensePlate = $listing->licensePlate;

                $listingData['license_plate'] = [
                    'plate_format' => [
                        'id' => $licensePlate->format?->id,
                        'name' => $licensePlate->format?->name,
                        'pattern' => $licensePlate->format?->pattern,
                        'country' => $licensePlate->format?->country,
                    ],
                    'city' => $licensePlate->city?->name,
                    'country' => $licensePlate->country?->name,
                    'country_id' => $licensePlate->country_id,
                    'fields' => $licensePlate->fieldValues->map(function ($fieldValue) {
                        return [
                            'field_id' => $fieldValue->formatField?->id,
                            'field_name' => $fieldValue->formatField?->field_name,
                            'field_position' => $fieldValue->formatField?->position,
                            'field_type' => $fieldValue->formatField?->field_type,
                            'field_label' => $fieldValue->formatField?->field_label,
                            'is_required' => $fieldValue->formatField?->is_required,
                            'max_length' => $fieldValue->formatField?->max_length,
                            'validation_pattern' => $fieldValue->formatField?->validation_pattern,
                            'value' => $fieldValue->field_value,
                        ];
                    })->toArray(),
                ];
            }

            return $listingData;
        });

        return response()->json([
            'message' => $message,
            'searched_country' => $countryName,
            'showing_all_countries' => $showingAllCountries,
            'total_listings' => $formattedListings->count(),
            'listings' => $formattedListings
        ]);
    }
}
