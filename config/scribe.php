<?php

use App\Modules\Survey\Http\Documentation\SurveyLogicOpenApiGenerator;
use Knuckles\Scribe\Config\AuthIn;

use function Knuckles\Scribe\Config\configureStrategy;

use Knuckles\Scribe\Config\Defaults;
use Knuckles\Scribe\Extracting\Strategies;

return [
    // The HTML <title> for the generated documentation.
    'title' => 'Dialog Platform API Documentation',

    // A short description of your API. Will be included in the docs webpage, Postman collection and OpenAPI spec.
    'description' => 'Complete REST API reference for the Dialog survey research platform. Covers Authentication, Business Survey Management, Respondent Participation Flow, and Admin Moderation.',

    // Text to place in the "Introduction" section, right after the `description`. Markdown and HTML are supported.
    'intro_text' => <<<'INTRO'
This documentation provides the complete API contract for frontend integration of the Dialog platform.

### Conventions & Principles
- **Base URL**: `/api/v1`
- **Error Envelope**: All error responses return HTTP 4xx/5xx with the standard envelope: `{"error": {"code": "ERROR_CODE", "message": "Human readable message", "fields": {...}}}`.
- **Multilingual Fields**: Multi-locale fields (`title`, `description`, etc.) accept JSON objects keyed by ISO language codes (`uz`, `ru`, `en`).
- **Respondent Flow**: Progression through survey pages is fully directed by the backend `SurveyFlow` response contract.
INTRO,

    // The base URL displayed in the docs.
    'base_url' => config('app.url', 'http://localhost:8000'),

    // Routes to include in the docs
    'routes' => [
        [
            'match' => [
                'prefixes' => ['api/v1/*'],
                'domains' => ['*'],
            ],
            'include' => [],
            'exclude' => [
                'api/v1/ping',
                'api/v1/auth/ping',
                'api/v1/business/ping',
                'api/v1/respondent/ping',
                'api/v1/admin/ping',
            ],
        ],
    ],

    'type' => 'static',

    'theme' => 'default',

    'static' => [
        'output_path' => 'public/docs',
    ],

    'laravel' => [
        'add_routes' => true,
        'docs_url' => '/docs',
        'assets_directory' => null,
        'middleware' => [],
    ],

    'external' => [
        'html_attributes' => [],
    ],

    'try_it_out' => [
        'enabled' => true,
        'base_url' => null,
        'use_csrf' => false,
        'csrf_url' => '/sanctum/csrf-cookie',
    ],

    'auth' => [
        'enabled' => true,
        'default' => false,
        'in' => AuthIn::BEARER->value,
        'name' => 'Authorization',
        'use_value' => env('SCRIBE_AUTH_KEY'),
        'placeholder' => '{YOUR_AUTH_TOKEN}',
        'extra_info' => 'Pass Authorization: Bearer <plainTextToken> using the token returned by /api/v1/auth/login or /api/v1/auth/register. Cookie-only SPA authentication is not implemented.',
    ],

    'example_languages' => [
        'bash',
        'javascript',
    ],

    'postman' => [
        'enabled' => true,
        'overrides' => [
            'info.name' => 'Dialog API Collection',
            'info.description' => 'Dialog Survey Platform API collection for frontend engineers',
            'info.version' => '1.0.0',
        ],
    ],

    'openapi' => [
        'enabled' => true,
        'version' => '3.0.3',
        'overrides' => [
            'info.title' => 'Dialog Platform API',
            'info.version' => '1.0.0',
            'info.description' => 'Dialog survey platform API specification for frontend integration.',
        ],
        'generators' => [SurveyLogicOpenApiGenerator::class],
    ],

    'groups' => [
        'default' => 'General',
        'order' => [
            'Authentication',
            'Business',
            'Respondent',
            'Admin',
        ],
    ],

    'logo' => false,

    'last_updated' => 'Last updated: {date:F j, Y}',

    'examples' => [
        'faker_seed' => 1234,
        'models_source' => ['factoryCreate', 'factoryMake', 'databaseFirst'],
    ],

    'strategies' => [
        'metadata' => [
            ...Defaults::METADATA_STRATEGIES,
        ],
        'headers' => [
            ...Defaults::HEADERS_STRATEGIES,
            Strategies\StaticData::withSettings(data: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]),
        ],
        'urlParameters' => [
            ...Defaults::URL_PARAMETERS_STRATEGIES,
        ],
        'queryParameters' => [
            ...Defaults::QUERY_PARAMETERS_STRATEGIES,
        ],
        'bodyParameters' => [
            ...Defaults::BODY_PARAMETERS_STRATEGIES,
        ],
        'responses' => configureStrategy(
            Defaults::RESPONSES_STRATEGIES,
            Strategies\Responses\ResponseCalls::withSettings(
                only: ['GET *'],
                config: [
                    'app.debug' => false,
                ]
            )
        ),
        'responseFields' => [
            ...Defaults::RESPONSE_FIELDS_STRATEGIES,
        ],
    ],

    'database_connections_to_transact' => [config('database.default')],

    'fractal' => [
        'serializer' => null,
    ],
];
