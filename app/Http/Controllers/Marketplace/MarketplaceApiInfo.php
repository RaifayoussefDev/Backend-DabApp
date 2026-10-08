<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use OpenApi\Attributes as OA;

/**
 * Root of the marketplace (mobile app + web storefront) Swagger doc. UI: /marketplace/documentation
 * Kept as an attribute so the "getting started" text can be real multi-line markdown.
 */
#[OA\Info(
    title: 'DabApp Marketplace API',
    version: '1.0.0',
    description: <<<'MD'
Storefront API of the parts & accessories marketplace, for the mobile app and the web.

## Before you call anything

| Rule | Value |
|---|---|
| Base path | `/api/marketplace/...` (same host as the rest of the DabApp API) |
| Header on **every** call | `Accept: application/json` |
| Logged-in calls | `Authorization: Bearer <JWT>` (the same token as the rest of the app). In this page click **Authorize** once and paste the token. |
| Uploads (logo, cover...) | `multipart/form-data`. A file field is sent as a file part, not as base64. |
| Files | Every `*_url` is an absolute URL, or `null` when there is no file. |
| Languages | Names come as `name` and `name_ar`. The app picks the one that matches the user's language. |

## Who can call what

| Level | Token | A guest gets |
|---|---|---|
| **Public** | not needed | the data |
| **User** | required | `401` with `requires_auth: true` |

A guest hitting a *User* endpoint gets exactly this. Open your login screen, then repeat the call:

```json
{ "success": false, "error": "Unauthenticated", "message": "You must be authenticated to access this resource.", "requires_auth": true, "action": "login" }
```

## Response shapes

| Case | Status | Body |
|---|---|---|
| Read | 200 | `{ "data": ... }` |
| Write | 200 / 201 | `{ "message": "...", "data": ... }` |
| List | 200 | `{ "data": [...], "meta": { "current_page", "per_page", "last_page", "total" } }`. Ask with `?page=1&per_page=15` (max 50). |
| Not found | 404 | `{ "message": "..." }` |
| Refused | 403 / 409 | `{ "message": "...", "code": "SOME_CODE" }`. **Use `code` in your logic, show `message` to the user.** |
| Invalid input | 422 | `{ "errors": { "field": ["message"] } }`. Show each message under its field. |

## Flows worth knowing

- **Browse**: categories, brands, shops. No login.
- **Become a seller**: `POST /vendor/apply`, then poll `GET /vendor/me` and use its `status` to pick the screen: `pending`, `rejected` (show `status_reason`, let the user resubmit), `suspended`, `active`.
MD
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'JWT of the logged-in user.'
)]
class MarketplaceApiInfo extends Controller
{
    // Holds the Swagger root attributes only.
}
