<?php

namespace App\Http\Middleware;

use App\Helpers\CountryHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The app reads the visitor's country from X-Forwarded-Country (full English name) and
 * X-Forwarded-Continent, which the old Cloudways nginx injected with GeoIP. Behind Cloudflare the
 * country arrives as CF-IPCountry (ISO code; CF-IPContinent only with the "visitor location headers"
 * transform). Translate them into the headers the app expects, overwriting anything the client sent.
 * Without CF-IPCountry (local setups) the request is left untouched.
 */
class VisitorCountryFromCloudflare
{
    public function handle(Request $request, Closure $next): Response
    {
        $code = strtoupper((string) $request->headers->get('CF-IPCountry'));

        if ($code !== '') {
            // XX = unknown, T1 = Tor: no country, same as before (callers fall back to their default)
            $country = preg_match('/^[A-Z]{2}$/', $code) ? CountryHelper::getCountryInfo($code) : null;
            $this->set($request, 'X-Forwarded-Country', $country['name'] ?? null);

            $continent = strtoupper((string) $request->headers->get('CF-IPContinent'));
            $this->set($request, 'X-Forwarded-Continent', preg_match('/^[A-Z]{2}$/', $continent) ? $continent : null);
        }

        return $next($request);
    }

    // Controllers read $_SERVER directly, so keep the superglobal, the request's server bag and its headers in sync.
    private function set(Request $request, string $header, ?string $value): void
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $header));

        if ($value === null) {
            unset($_SERVER[$key]);
            $request->server->remove($key);
            $request->headers->remove($header);

            return;
        }

        $_SERVER[$key] = $value;
        $request->server->set($key, $value);
        $request->headers->set($header, $value);
    }
}
