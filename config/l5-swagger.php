<?php

$adminControllers = [];
$appControllers = [];

// Helper function removed in favor of inline logic to optimize file reading

// Scanning directories to categorize files
$controllersPath = base_path('app/Http/Controllers');
$allFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($controllersPath));

foreach ($allFiles as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getPathname();
        $fileName = basename($path);

        // Explicitly handle Swagger controllers
        if (str_contains($path, 'SwaggerController.php')) {
            $appControllers[] = $path;
            continue;
        }
        if (str_contains($path, 'SwaggerAdminController.php')) {
            $adminControllers[] = $path;
            continue;
        }

        // Read content once
        $content = file_get_contents($path);
        if ($content === false)
            continue;

        // Check for "Management" tag (indicates Admin relevance)
        $hasManagementTag = str_contains($content, 'Management"');

        // Check if there are any tags that are NOT Management (indicates App relevance)
        $hasNonManagementTag = false;

        // 1. Check explicit @OA\Tag(name="...")
        if (preg_match_all('/@OA\\\\Tag\s*\(\s*name\s*=\s*"([^"]+)"/', $content, $matches)) {
            foreach ($matches[1] as $tag) {
                if (!str_contains($tag, 'Management')) {
                    $hasNonManagementTag = true;
                    break;
                }
            }
        }

        // 2. Check tags={"..."} assignments in annotations
        if (!$hasNonManagementTag && preg_match_all('/tags\s*=\s*\{([^}]+)\}/', $content, $matches)) {
            foreach ($matches[1] as $tagList) {
                // $tagList could be '"Tag1", "Tag2"'
                // Extract individual tags
                if (preg_match_all('/"([^"]+)"/', $tagList, $tags)) {
                    foreach ($tags[1] as $tag) {
                        if (!str_contains($tag, 'Management')) {
                            $hasNonManagementTag = true;
                            break 2; // Break both loops
                        }
                    }
                }
            }
        }

        // Fallback: If NO tags are found at all, and it's NOT an Admin path, assume it belongs to App.
        // But if it has Management tags (and no others), hasNonManagementTag remains false.
        if (!$hasManagementTag && !$hasNonManagementTag) {
            $hasNonManagementTag = true;
        }

        // Check for Admin path/name (Strict Admin)
        $normalized = str_replace(['\\', '/'], '/', $path);
        $isAdminPath = stripos($normalized, '/Http/Controllers/Admin/') !== false || str_contains($fileName, 'Admin');

        // Logic:
        // Admin Docs = AdminPath OR HasManagementTag
        if ($isAdminPath || $hasManagementTag) {
            $adminControllers[] = $path;
        }

        // App Docs = NOT AdminPath AND HasNonManagementTag
        // This ensures User Management (Pure Admin) is excluded from App.
        // But Banner (Mixed) is included in App.
        if (!$isAdminPath && $hasNonManagementTag) {
            $appControllers[] = $path;
        }
    }
}

// Add Models to both
$modelsPath = base_path('app/Models');
$appControllers[] = $modelsPath;
$adminControllers[] = $modelsPath;

return [
    'default' => 'default',
    'documentations' => [
        'default' => [
            'api' => [
                'title' => 'DabApp API Documentation',
            ],

            'routes' => [
                /*
                 * Route for accessing api documentation interface
                 */
                'api' => 'api/documentation',
                'docs' => 'api/docs',
                'oauth2_callback' => 'api/oauth2-callback',
            ],
            'paths' => [
                /*
                 * Edit to include full URL in ui for assets
                 */
                'use_absolute_path' => env('L5_SWAGGER_USE_ABSOLUTE_PATH', true),

                /*
                 * Edit to set path where swagger ui assets should be stored
                 */
                'swagger_ui_assets_path' => env('L5_SWAGGER_UI_ASSETS_PATH', 'vendor/swagger-api/swagger-ui/dist/'),

                /*
                 * File name of the generated json documentation file
                 */
                'docs_json' => 'api-docs.json',

                /*
                 * File name of the generated YAML documentation file
                 */
                'docs_yaml' => 'api-docs.yaml',

                /*
                 * Set this to `json` or `yaml` to determine which documentation file to use in UI
                 */
                'format_to_use_for_docs' => env('L5_FORMAT_TO_USE_FOR_DOCS', 'json'),

                /*
                 * Absolute paths to directory containing the swagger annotations are stored.
                 */
                'annotations' => $appControllers,
            ],
        ],

        'admin' => [
            'api' => [
                'title' => 'DabApp Admin API Documentation',
            ],

            'routes' => [
                /*
                 * Route for accessing api documentation interface
                 */
                'api' => 'admin/documentation',
                'docs' => 'admin/docs',
                'oauth2_callback' => 'admin/oauth2-callback',
            ],
            'paths' => [
                /*
                 * Edit to include full URL in ui for assets
                 */
                'use_absolute_path' => env('L5_SWAGGER_USE_ABSOLUTE_PATH', true),

                /*
                 * Edit to set path where swagger ui assets should be stored
                 */
                'swagger_ui_assets_path' => env('L5_SWAGGER_UI_ASSETS_PATH', 'vendor/swagger-api/swagger-ui/dist/'),

                /*
                 * File name of the generated json documentation file
                 */
                'docs_json' => 'admin-docs.json',

                /*
                 * File name of the generated YAML documentation file
                 */
                'docs_yaml' => 'admin-docs.yaml',

                /*
                 * Set this to `json` or `yaml` to determine which documentation file to use in UI
                 */
                'format_to_use_for_docs' => env('L5_FORMAT_TO_USE_FOR_DOCS', 'json'),

                /*
                 * Absolute paths to directory containing the swagger annotations are stored.
                 */
                'annotations' => $adminControllers,
            ],
        ],
    ],
    'defaults' => [
        'routes' => [
            /*
             * Route for accessing parsed swagger annotations.
             */
            'docs' => 'docs',

            /*
             * Route for Oauth2 authentication callback.
             */
            'oauth2_callback' => 'api/oauth2-callback',

            /*
             * Middleware allows to prevent unexpected access to API documentation
             */
            'middleware' => [
                'api' => ['swagger.auth'],
                'asset' => [],
                'docs' => ['swagger.auth'],
                'oauth2_callback' => [],
            ],

            /*
             * Route Group options
             */
            'group_options' => [],
        ],

        'paths' => [
            /*
             * Absolute path to location where parsed annotations will be stored
             */
            'docs' => storage_path('api-docs'),

            /*
             * Absolute path to directory where to export views
             */
            'views' => base_path('resources/views/vendor/l5-swagger'),

            /*
             * Edit to set the api's base path
             */
            'base' => env('L5_SWAGGER_BASE_PATH', null),

            /*
             * Absolute path to directories that should be excluded from scanning
             * @deprecated Please use `scanOptions.exclude`
             * `scanOptions.exclude` overwrites this
             */
            'excludes' => [],
        ],

        'scanOptions' => [
            /**
             * Configuration for default processors. Allows to pass processors configuration to swagger-php.
             *
             * @link https://zircote.github.io/swagger-php/reference/processors.html
             */
            'default_processors_configuration' => [
                /** Example */
                /**
                 * 'operationId.hash' => true,
                 * 'pathFilter' => [
                 * 'tags' => [
                 * '/pets/',
                 * '/store/',
                 * ],
                 * ],.
                 */
            ],

            /**
             * analyser: defaults to \OpenApi\StaticAnalyser .
             *
             * @see \OpenApi\scan
             */
            'analyser' => null,

            /**
             * analysis: defaults to a new \OpenApi\Analysis .
             *
             * @see \OpenApi\scan
             */
            'analysis' => null,

            /**
             * Custom query path processors classes.
             *
             * @link https://github.com/zircote/swagger-php/tree/master/Examples/processors/schema-query-parameter
             * @see \OpenApi\scan
             */
            'processors' => [
                // new \App\SwaggerProcessors\SchemaQueryParameter(),
            ],

            /**
             * pattern: string       $pattern File pattern(s) to scan (default: *.php) .
             *
             * @see \OpenApi\scan
             */
            'pattern' => null,

            /*
             * Absolute path to directories that should be excluded from scanning
             * @note This option overwrites `paths.excludes`
             * @see \OpenApi\scan
             */
            'exclude' => [
                base_path('app/Console'),
                base_path('app/Exceptions'),
                base_path('app/Providers'),
                base_path('app/Http/Middleware'),
            ],

            /*
             * Allows to generate specs either for OpenAPI 3.0.0 or OpenAPI 3.1.0.
             * By default the spec will be in version 3.0.0
             */
            'open_api_spec_version' => env('L5_SWAGGER_OPEN_API_SPEC_VERSION', \L5Swagger\Generator::OPEN_API_DEFAULT_SPEC_VERSION),
        ],

        /*
         * API security definitions. Will be generated into documentation file.
         */
        'securityDefinitions' => [
            'securitySchemes' => [
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT',
                    'description' => 'Enter token in format: Bearer <your-token>'
                ],
            ],
            'security' => [
                /*
                 * Examples of Securities
                 */
                [
                    'bearerAuth' => []
                ],
            ],
        ],

        /*
         * Set this to `true` in development mode so that docs would be regenerated on each request
         * Set this to `false` to disable swagger generation on production
         */
        'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', false),

        /*
         * Set this to `true` to generate a copy of documentation in yaml format
         */
        'generate_yaml_copy' => env('L5_SWAGGER_GENERATE_YAML_COPY', false),

        /*
         * Edit to trust the proxy's ip address - needed for AWS Load Balancer
         * string[]
         */
        'proxy' => false,

        /*
         * Configs plugin allows to fetch external configs instead of passing them to SwaggerUIBundle.
         * See more at: https://github.com/swagger-api/swagger-ui#configs-plugin
         */
        'additional_config_url' => null,

        /*
         * Apply a sort to the operation list of each API. It can be 'alpha' (sort by paths alphanumerically),
         * 'method' (sort by HTTP method).
         * Default is the order returned by the server unchanged.
         */
        'operations_sort' => env('L5_SWAGGER_OPERATIONS_SORT', null),

        /*
         * Pass the validatorUrl parameter to SwaggerUi init on the JS side.
         * A null value here disables validation.
         */
        'validator_url' => null,

        /*
         * Swagger UI configuration parameters
         */
        'ui' => [
            'display' => [
                'dark_mode' => env('L5_SWAGGER_UI_DARK_MODE', false),
                /*
                 * Controls the default expansion setting for the operations and tags. It can be :
                 * 'list' (expands only the tags),
                 * 'full' (expands the tags and operations),
                 * 'none' (expands nothing).
                 */
                'doc_expansion' => env('L5_SWAGGER_UI_DOC_EXPANSION', 'none'),

                /**
                 * If set, enables filtering. The top bar will show an edit box that
                 * you can use to filter the tagged operations that are shown. Can be
                 * Boolean to enable or disable, or a string, in which case filtering
                 * will be enabled using that string as the filter expression. Filtering
                 * is case-sensitive matching the filter expression anywhere inside
                 * the tag.
                 */
                'filter' => env('L5_SWAGGER_UI_FILTERS', true), // true | false
            ],

            'authorization' => [
                /*
                 * If set to true, it persists authorization data, and it would not be lost on browser close/refresh
                 */
                'persist_authorization' => env('L5_SWAGGER_UI_PERSIST_AUTHORIZATION', false),

                'oauth2' => [
                    /*
                     * If set to true, adds PKCE to AuthorizationCodeGrant flow
                     */
                    'use_pkce_with_authorization_code_grant' => false,
                ],
            ],
        ],
        /*
         * Constants which can be used in annotations
         */
        'constants' => [
            'L5_SWAGGER_CONST_HOST' => env('L5_SWAGGER_CONST_HOST', 'http://localhost:8000'),
        ],
    ],
];
