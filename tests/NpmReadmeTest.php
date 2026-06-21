<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\NpmReadme\NpmReadme;

beforeEach(fn () => Cache::flush());

it('extracts the package name from an npm package url', function () {
    expect(NpmReadme::packageFromUrl('https://www.npmjs.com/package/laravel-echo'))->toBe('laravel-echo');
    expect(NpmReadme::packageFromUrl('https://npmjs.com/package/@tailwindcss/vite'))->toBe('@tailwindcss/vite');
});

it('returns null for non-npm urls', function () {
    expect(NpmReadme::packageFromUrl('https://github.com/owner/repo'))->toBeNull();
    expect(NpmReadme::packageFromUrl(null))->toBeNull();
});

it('fetches the registry document and renders the readme markdown', function () {
    Http::fake([
        'registry.npmjs.org/*' => Http::response(['readme' => "# Title\n\nHello **world**."], 200),
    ]);

    $html = NpmReadme::fetchHtml('https://www.npmjs.com/package/laravel-echo');

    expect($html)
        ->toContain('<h1')
        ->toContain('Title')
        ->toContain('<strong>world</strong>');
});

it('strips raw html with the default renderer', function () {
    Http::fake([
        'registry.npmjs.org/*' => Http::response(['readme' => 'Hello <script>alert(1)</script> world'], 200),
    ]);

    $html = NpmReadme::fetchHtml('https://www.npmjs.com/package/evil');

    expect($html)->not->toContain('<script>');
});

it('uses a custom renderer callable when configured', function () {
    config()->set('npm-readme.renderer', fn (string $md): string => '<custom>'.$md.'</custom>');

    Http::fake([
        'registry.npmjs.org/*' => Http::response(['readme' => 'raw markdown'], 200),
    ]);

    expect(NpmReadme::fetchHtml('https://www.npmjs.com/package/foo'))
        ->toBe('<custom>raw markdown</custom>');
});

it('returns null when the package ships no readme sentinel', function () {
    Http::fake([
        'registry.npmjs.org/*' => Http::response(['readme' => 'ERROR: No README data found!'], 200),
    ]);

    expect(NpmReadme::fetchHtml('https://www.npmjs.com/package/empty'))->toBeNull();
});

it('returns null on a failed registry response', function () {
    Http::fake([
        'registry.npmjs.org/*' => Http::response(null, 500),
    ]);

    expect(NpmReadme::fetchHtml('https://www.npmjs.com/package/missing'))->toBeNull();
});

it('caches the rendered html so a second call does not hit the registry', function () {
    Http::fake([
        'registry.npmjs.org/*' => Http::response(['readme' => '# Cached'], 200),
    ]);

    $first = NpmReadme::fetchHtml('https://www.npmjs.com/package/cacheable');
    $second = NpmReadme::fetchHtml('https://www.npmjs.com/package/cacheable');

    expect($first)->toBe($second);
    Http::assertSentCount(1);
});
