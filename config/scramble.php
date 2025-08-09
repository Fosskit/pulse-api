<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

return [
    /*
     * Your API path. By default, all routes starting with this path will be added to the docs.
     * If you need to change this behavior, you can add your custom routes resolver using `Scramble::routes()`.
     */
    'api_path' => 'api/v1',

    /*
     * Your API domain. By default, app domain is used. This is also a part of the default API routes
     * matcher, so when implementing your own, make sure you use this config if needed.
     */
    'api_domain' => null,

    /*
     * The path where your OpenAPI specification will be exported.
     */
    'export_path' => 'api-v1.json',

    'info' => [
        /*
         * API version.
         */
        'version' => env('API_VERSION', '1.0.0'),

        /*
         * API title.
         */
        'title' => 'EMR FHIR API',

        /*
         * Description rendered on the home page of the API documentation (`/docs/api`).
         */
        'description' => 'A comprehensive Electronic Medical Record (EMR) API system built on simplified HL7 FHIR standards. This API provides endpoints for managing patient information, clinical workflows, medications, service requests, billing, and facility management.',

        /*
         * Contact information for the API.
         */
        'contact' => [
            'name' => 'EMR FHIR API Support',
            'email' => env('API_CONTACT_EMAIL', 'support@emr-fhir.local'),
        ],

        /*
         * License information for the API.
         */
        'license' => [
            'name' => 'MIT',
            'url' => 'https://opensource.org/licenses/MIT',
        ],
    ],

    /*
     * Customize Stoplight Elements UI
     */
    'ui' => [
        /*
         * Define the title of the documentation's website. App name is used when this config is `null`.
         */
        'title' => 'EMR FHIR API Documentation',

        /*
         * Define the theme of the documentation. Available options are `light` and `dark`.
         */
        'theme' => 'light',

        /*
         * Hide the `Try It` feature. Enabled by default.
         */
        'hide_try_it' => false,

        /*
         * Hide the schemas in the Table of Contents. Enabled by default.
         */
        'hide_schemas' => false,

        /*
         * URL to an image that displays as a small square logo next to the title, above the table of contents.
         */
        'logo' => '',

        /*
         * Use to fetch the credential policy for the Try It feature. Options are: omit, include (default), and same-origin
         */
        'try_it_credentials_policy' => 'include',

        /*
         * There are three layouts for Elements:
         * - sidebar - (Elements default) Three-column design with a sidebar that can be resized.
         * - responsive - Like sidebar, except at small screen sizes it collapses the sidebar into a drawer that can be toggled open.
         * - stacked - Everything in a single column, making integrations with existing websites that have their own sidebar or other columns already.
         */
        'layout' => 'responsive',
    ],

    /*
     * The list of servers of the API. By default, when `null`, server URL will be created from
     * `scramble.api_path` and `scramble.api_domain` config variables. When providing an array, you
     * will need to specify the local server URL manually (if needed).
     */
    'servers' => [
        'Development' => env('APP_URL', 'http://localhost:8000') . '/api/v1',
        'Production' => env('API_PRODUCTION_URL', 'https://api.emr-fhir.com') . '/api/v1',
    ],

    /**
     * Determines how Scramble stores the descriptions of enum cases.
     * Available options:
     * - 'description' – Case descriptions are stored as the enum schema's description using table formatting.
     * - 'extension' – Case descriptions are stored in the `x-enumDescriptions` enum schema extension.
     *
     *    @see https://redocly.com/docs-legacy/api-reference-docs/specification-extensions/x-enum-descriptions
     * - false - Case descriptions are ignored.
     */
    'enum_cases_description_strategy' => 'description',

    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [
        /*
         * Security schemes for API authentication
         */
        'securitySchemes' => [
            'sanctum' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
                'description' => 'Laravel Sanctum authentication token',
            ],
        ],
        
        /*
         * Global security requirements
         */
        'security' => [
            ['sanctum' => []],
        ],
    ],

    /*
     * Custom OpenAPI tags for organizing endpoints
     */
    'tags' => [
        [
            'name' => 'Authentication',
            'description' => 'User authentication and authorization endpoints',
        ],
        [
            'name' => 'Patients',
            'description' => 'Patient management and demographics',
        ],
        [
            'name' => 'Visits',
            'description' => 'Patient visits and admissions',
        ],
        [
            'name' => 'Encounters',
            'description' => 'Clinical encounters and activities',
        ],
        [
            'name' => 'Clinical Forms',
            'description' => 'Clinical form templates and submissions',
        ],
        [
            'name' => 'Observations',
            'description' => 'Clinical observations and vital signs',
        ],
        [
            'name' => 'Reference Data',
            'description' => 'Taxonomy, facilities, and gazetteer data',
        ],
    ],
];
