<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayTabsService
{
    private $profileId;
    private $serverKey;
    private $currency;
    private $region;
    private $baseUrl;

    // PayTabs regions and their endpoints
    const BASE_URLS = [
        'ARE' => [
            'title' => 'United Arab Emirates',
            'endpoint' => 'https://secure.paytabs.com/'
        ],
        'SAU' => [
            'title' => 'Saudi Arabia',
            'endpoint' => 'https://secure.paytabs.sa/'
        ],
        'OMN' => [
            'title' => 'Oman',
            'endpoint' => 'https://secure-oman.paytabs.com/'
        ],
        'JOR' => [
            'title' => 'Jordan',
            'endpoint' => 'https://secure-jordan.paytabs.com/'
        ],
        'EGY' => [
            'title' => 'Egypt',
            'endpoint' => 'https://secure-egypt.paytabs.com/'
        ],
        'GLOBAL' => [
            'title' => 'Global',
            'endpoint' => 'https://secure-global.paytabs.com/'
        ]
    ];

    // Supported payment methods with their currencies
    const PAYMENT_TYPES = [
        '0' => ['name' => 'all', 'title' => 'PayTabs - All', 'currencies' => null],
        '1' => ['name' => 'stcpay', 'title' => 'PayTabs - StcPay', 'currencies' => ['SAR']],
        '2' => ['name' => 'stcpayqr', 'title' => 'PayTabs - StcPay(QR)', 'currencies' => ['SAR']],
        '3' => ['name' => 'applepay', 'title' => 'PayTabs - ApplePay', 'currencies' => ['AED', 'SAR']],
        '4' => ['name' => 'omannet', 'title' => 'PayTabs - OmanNet', 'currencies' => ['OMR']],
        '5' => ['name' => 'mada', 'title' => 'PayTabs - Mada', 'currencies' => ['SAR']],
        '6' => ['name' => 'creditcard', 'title' => 'PayTabs - CreditCard', 'currencies' => null],
        '7' => ['name' => 'sadad', 'title' => 'PayTabs - Sadad', 'currencies' => ['SAR']],
        '8' => ['name' => 'atfawry', 'title' => 'PayTabs - @Fawry', 'currencies' => ['EGP']],
        '9' => ['name' => 'knet', 'title' => 'PayTabs - KnPay', 'currencies' => ['KWD']],
        '10' => ['name' => 'amex', 'title' => 'PayTabs - Amex', 'currencies' => ['AED', 'SAR']],
        '11' => ['name' => 'valu', 'title' => 'PayTabs - valU', 'currencies' => ['EGP']],
    ];

    public function __construct()
    {
        $this->profileId = config('paytabs.profile_id');
        $this->serverKey = config('paytabs.server_key');
        $this->currency = config('paytabs.currency', 'USD');
        $this->region = config('paytabs.region', 'ARE');

        // Use the correct endpoint based on region
        $this->baseUrl = self::BASE_URLS[$this->region]['endpoint'] ?? self::BASE_URLS['ARE']['endpoint'];
    }

    /**
     * Test de connexion à PayTabs
     */
    public function testConnection()
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => $this->serverKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($this->baseUrl . 'payment/request', [
                'profile_id' => (int) $this->profileId,
                'tran_type' => 'sale',
                'tran_class' => 'ecom',
                'cart_id' => 'test_' . time(),
                'cart_description' => 'PayTabs Connection Test',
                'cart_currency' => $this->currency,
                'cart_amount' => 1.00,
                'callback' => route('paytabs.callback'),
                'return' => route('paytabs.return'),
                'customer_details' => [
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'phone' => '1234567890',
                    'street1' => 'Test Street',
                    'city' => 'Test City',
                    'state' => 'Test State',
                    'country' => $this->region,
                    'zip' => '12345',
                    'ip' => request()->ip() ?: '127.0.0.1'
                ],
                'hide_shipping' => true
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('PayTabs Test Connection Success', $data);
                return [
                    'success' => true,
                    'message' => 'PayTabs connection successful',
                    'data' => $data
                ];
            } else {
                Log::error('PayTabs Test Connection Failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'headers' => $response->headers()
                ]);
                return [
                    'success' => false,
                    'message' => 'PayTabs connection failed',
                    'error' => $response->body()
                ];
            }
        } catch (Exception $e) {
            Log::error('PayTabs Test Exception', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'PayTabs test failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Créer une transaction de paiement pour un listing
     */
    public function createPaymentForListing($listing, $user, $amount = null, $paymentMethods = ['creditcard'])
    {
        try {
            $cartId = 'listing_' . $listing->id . '_' . time();
            $paymentAmount = $amount ?? $listing->price;

            $payload = [
                'profile_id' => (int) $this->profileId,
                'tran_type' => 'sale',
                'tran_class' => 'ecom',
                'cart_id' => $cartId,
                'cart_description' => 'Payment for listing: ' . $listing->title,
                'cart_currency' => $this->currency,
                'cart_amount' => (float) $paymentAmount,
                'callback' => route('paytabs.callback'),
                'return' => route('paytabs.return'),
                'customer_details' => [
                    'name' => $user->name ?? 'User',
                    'email' => $user->email,
                    'phone' => $user->phone ?? '1234567890',
                    'street1' => $user->address ?? 'Default Address',
                    'city' => $user->city ?? 'Default City',
                    'state' => $user->state ?? 'Default State',
                    'country' => $this->region,
                    'zip' => $user->postal_code ?? '12345',
                    'ip' => request()->ip() ?: '127.0.0.1'
                ],
                'user_defined' => [
                    'listing_id' => (string) $listing->id,
                    'user_id' => (string) $user->id
                ],
                'hide_shipping' => true
            ];

            // Add payment methods if specified
            if (!empty($paymentMethods) && !in_array('all', $paymentMethods)) {
                $payload['payment_methods'] = $paymentMethods;
            }

            $response = Http::withHeaders([
                'Authorization' => $this->serverKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($this->baseUrl . 'payment/request', $payload);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('PayTabs Payment Created', ['cart_id' => $cartId, 'data' => $data]);

                return [
                    'success' => true,
                    'payment_url' => $data['redirect_url'] ?? null,
                    'tran_ref' => $data['tran_ref'] ?? null,
                    'cart_id' => $cartId,
                    'data' => $data
                ];
            } else {
                Log::error('PayTabs Payment Creation Failed', [
                    'cart_id' => $cartId,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to create payment',
                    'error' => $response->body()
                ];
            }
        } catch (Exception $e) {
            Log::error('PayTabs Payment Exception', [
                'listing_id' => $listing->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Payment creation failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Vérifier le statut d'une transaction
     */
    public function verifyPayment($tranRef)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => $this->serverKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($this->baseUrl . 'payment/query', [
                'profile_id' => (int) $this->profileId,
                'tran_ref' => $tranRef
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('PayTabs Payment Verification', ['tran_ref' => $tranRef, 'data' => $data]);
                return [
                    'success' => true,
                    'data' => $data
                ];
            } else {
                Log::error('PayTabs Payment Verification Failed', [
                    'tran_ref' => $tranRef,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [
                    'success' => false,
                    'error' => $response->body()
                ];
            }
        } catch (Exception $e) {
            Log::error('PayTabs Verification Exception', [
                'tran_ref' => $tranRef,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get supported payment methods for current region/currency
     */
    public function getSupportedPaymentMethods()
    {
        $supportedMethods = [];

        foreach (self::PAYMENT_TYPES as $key => $method) {
            $currencies = $method['currencies'];

            // If method supports all currencies or current currency is supported
            if ($currencies === null || in_array($this->currency, $currencies)) {
                $supportedMethods[$key] = $method;
            }
        }

        return $supportedMethods;
    }

    /**
     * Check if payment method is supported for current currency
     */
    public function isPaymentMethodSupported($paymentMethod)
    {
        $supportedMethods = $this->getSupportedPaymentMethods();

        foreach ($supportedMethods as $method) {
            if ($method['name'] === $paymentMethod) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtenir la configuration actuelle
     */
    public function getConfig()
    {
        $supportedMethods = $this->getSupportedPaymentMethods();

        return [
            'profile_id' => $this->profileId ? 'Configured' : 'Missing',
            'server_key' => $this->serverKey ? 'Configured' : 'Missing',
            'currency' => $this->currency,
            'region' => $this->region,
            'region_title' => self::BASE_URLS[$this->region]['title'] ?? 'Unknown',
            'base_url' => $this->baseUrl,
            'supported_payment_methods' => array_column($supportedMethods, 'title', 'name')
        ];
    }

    /**
     * Get available regions
     */
    public static function getAvailableRegions()
    {
        return array_map(function($region) {
            return $region['title'];
        }, self::BASE_URLS);
    }
}
