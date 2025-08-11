<?php

namespace App\Services;

class PayTabsHelper
{
    /**
     * Get payment method name by key
     */
    public static function getPaymentMethodName($key)
    {
        return PayTabsService::PAYMENT_TYPES[$key]['name'] ?? null;
    }

    /**
     * Check if payment method is allowed for currency
     */
    public static function isPaymentMethodAllowed($methodName, $currencyCode)
    {
        $method = null;
        foreach (PayTabsService::PAYMENT_TYPES as $paymentType) {
            if ($paymentType['name'] === $methodName) {
                $method = $paymentType;
                break;
            }
        }

        if (!$method) {
            return false;
        }

        $supportedCurrencies = $method['currencies'];
        if ($supportedCurrencies === null) {
            return true; // Supports all currencies
        }

        return in_array(strtoupper($currencyCode), $supportedCurrencies);
    }

    /**
     * Get the first non-empty value from the provided variables
     */
    public static function getNonEmpty(...$vars)
    {
        foreach ($vars as $var) {
            if (!empty($var)) {
                return $var;
            }
        }
        return null;
    }

    /**
     * Convert non-English digits to English (for postal codes, etc.)
     */
    public static function convertArabicToEnglish($string)
    {
        $arabicDigits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $string = str_replace($arabicDigits, $englishDigits, $string);
        $string = str_replace($persianDigits, $englishDigits, $string);

        return $string;
    }

    /**
     * Validate and clean customer details
     */
    public static function cleanCustomerDetails($details)
    {
        return [
            'name' => self::fillIfEmpty($details['name'] ?? 'Customer'),
            'email' => $details['email'] ?? 'customer@example.com',
            'phone' => $details['phone'] ?? '1234567890',
            'street1' => self::fillIfEmpty($details['street1'] ?? 'Default Street'),
            'city' => self::fillIfEmpty($details['city'] ?? 'Default City'),
            'state' => self::fillIfEmpty($details['state'] ?? 'Default State'),
            'country' => $details['country'] ?? 'ARE',
            'zip' => self::convertArabicToEnglish($details['zip'] ?? '12345'),
            'ip' => $details['ip'] ?? request()->ip() ?: '127.0.0.1'
        ];
    }

    /**
     * Fill empty string with 'NA' if it contains only non-word characters
     */
    private static function fillIfEmpty($string)
    {
        if (empty(preg_replace('/[\W]/', '', $string))) {
            return $string . 'NA';
        }
        return $string;
    }

    /**
     * Get recommended payment methods for region/currency
     */
    public static function getRecommendedPaymentMethods($region, $currency)
    {
        $recommendations = [
            'ARE' => [
                'AED' => ['creditcard', 'applepay', 'amex'],
                'USD' => ['creditcard'],
            ],
            'SAU' => [
                'SAR' => ['creditcard', 'mada', 'stcpay', 'applepay', 'sadad'],
                'USD' => ['creditcard'],
            ],
            'EGY' => [
                'EGP' => ['creditcard', 'atfawry', 'valu'],
                'USD' => ['creditcard'],
            ],
            'KWD' => [
                'KWD' => ['creditcard', 'knet'],
                'USD' => ['creditcard'],
            ],
            'OMN' => [
                'OMR' => ['creditcard', 'omannet'],
                'USD' => ['creditcard'],
            ]
        ];

        return $recommendations[$region][$currency] ?? ['creditcard'];
    }

    /**
     * Format amount for PayTabs (ensure 2 decimal places)
     */
    public static function formatAmount($amount)
    {
        return round((float) $amount, 2);
    }

    /**
     * Generate unique cart ID
     */
    public static function generateCartId($prefix = 'cart')
    {
        return $prefix . '_' . time() . '_' . rand(1000, 9999);
    }

    /**
     * Validate PayTabs response
     */
    public static function validateResponse($response)
    {
        return [
            'is_successful' => ($response['payment_result']['response_status'] ?? '') === 'A',
            'is_declined' => ($response['payment_result']['response_status'] ?? '') === 'D',
            'is_pending' => ($response['payment_result']['response_status'] ?? '') === 'P',
            'response_code' => $response['payment_result']['response_code'] ?? '',
            'response_message' => $response['payment_result']['response_message'] ?? '',
            'transaction_id' => $response['tran_ref'] ?? '',
            'cart_id' => $response['cart_id'] ?? '',
        ];
    }
}
