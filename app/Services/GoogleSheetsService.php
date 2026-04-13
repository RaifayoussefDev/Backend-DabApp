<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * Google Sheets API v4 integration via service account.
 *
 * Setup:
 * 1. Go to Google Cloud Console → create a project → enable "Google Sheets API"
 * 2. Create a Service Account → download the JSON key file
 * 3. Place the JSON key file at: storage/app/google-service-account.json
 * 4. Set GOOGLE_SERVICE_ACCOUNT_PATH in .env (default: storage/app/google-service-account.json)
 * 5. Share each Google Sheet with the service account email (editor permission)
 * 6. The google_sheet_id is the long ID from the sheet URL:
 *    https://docs.google.com/spreadsheets/d/{SHEET_ID}/edit
 */
class GoogleSheetsService
{
    private Client $http;
    private string $credentialsPath;
    private ?string $cachedToken = null;
    private int $tokenExpiry = 0;

    public function __construct()
    {
        $this->http = new Client(['timeout' => 15]);
        $this->credentialsPath = env(
            'GOOGLE_SERVICE_ACCOUNT_PATH',
            storage_path('app/google-service-account.json')
        );
    }

    /**
     * Append a row to the given Google Sheet.
     *
     * @param string $spreadsheetId  The sheet ID from the URL
     * @param array  $rowValues      Ordered array of cell values
     * @param string $range          Sheet range to append to (default: first sheet)
     */
    public function appendRow(string $spreadsheetId, array $rowValues, string $range = 'A:F'): bool
    {
        try {
            $token = $this->getAccessToken();

            $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$range}:append";

            $this->http->post($url, [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type'  => 'application/json',
                ],
                'query' => [
                    'valueInputOption'  => 'USER_ENTERED',
                    'insertDataOption'  => 'INSERT_ROWS',
                ],
                'json' => [
                    'values' => [$rowValues],
                ],
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('GoogleSheetsService: failed to append row', [
                'spreadsheet_id' => $spreadsheetId,
                'error'          => $e->getMessage(),
            ]);
            return false;
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Private: OAuth2 service account token
    // ──────────────────────────────────────────────────────────────

    private function getAccessToken(): string
    {
        if ($this->cachedToken && time() < $this->tokenExpiry - 60) {
            return $this->cachedToken;
        }

        $credentials = $this->loadCredentials();
        $jwt         = $this->buildJwt($credentials);

        $response = $this->http->post('https://oauth2.googleapis.com/token', [
            'form_params' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        $this->cachedToken = $data['access_token'];
        $this->tokenExpiry = time() + ($data['expires_in'] ?? 3600);

        return $this->cachedToken;
    }

    private function loadCredentials(): array
    {
        if (!file_exists($this->credentialsPath)) {
            throw new \RuntimeException(
                "Google service account file not found at: {$this->credentialsPath}"
            );
        }

        return json_decode(file_get_contents($this->credentialsPath), true);
    }

    private function buildJwt(array $credentials): string
    {
        $now = time();
        $header = base64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = base64url_encode(json_encode([
            'iss'   => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/spreadsheets',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));

        $data = "{$header}.{$payload}";

        $privateKey = openssl_pkey_get_private($credentials['private_key']);
        openssl_sign($data, $signature, $privateKey, 'SHA256');

        return "{$data}." . base64url_encode($signature);
    }
}

// ──────────────────────────────────────────────────────────────────────────────
// Helper (global scope, only defined once)
// ──────────────────────────────────────────────────────────────────────────────
if (!function_exists('base64url_encode')) {
    function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
