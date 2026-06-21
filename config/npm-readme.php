<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Cache Duration
    |--------------------------------------------------------------------------
    |
    | Minutes the rendered README HTML is cached per package (keyed by
    | `npm_readme:{package}` on the default cache store).
    |
    */
    'cache_minutes' => (int) env('NPM_README_CACHE_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Registry URL
    |--------------------------------------------------------------------------
    |
    | Base URL of the npm registry. The package document is fetched from
    | `{registry_url}/{package}` and its inline `readme` field is rendered.
    |
    */
    'registry_url' => env('NPM_README_REGISTRY_URL', 'https://registry.npmjs.org'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The timeout, in seconds, applied to the registry request.
    |
    */
    'timeout' => (int) env('NPM_README_TIMEOUT', 8),

    /*
    |--------------------------------------------------------------------------
    | User Agent
    |--------------------------------------------------------------------------
    |
    | The User-Agent header sent with the registry request.
    |
    */
    'user_agent' => env('NPM_README_USER_AGENT', 'laravel-npm-readme'),

    /*
    |--------------------------------------------------------------------------
    | Markdown Renderer
    |--------------------------------------------------------------------------
    |
    | An optional callable that receives the raw README markdown and returns
    | rendered HTML. When null, an internal League\CommonMark renderer is used
    | that STRIPS raw HTML (GitHub Flavored Markdown + heading permalinks).
    |
    | Provide a callable to keep raw HTML ('allow'); the rendered HTML is then
    | UNTRUSTED and you MUST sanitize it before display, e.g. with
    | jeffersongoncalves/laravel-html-sanitizer.
    |
    */
    'renderer' => null,
];
