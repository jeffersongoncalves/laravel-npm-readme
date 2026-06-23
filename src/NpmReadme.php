<?php

declare(strict_types=1);

namespace JeffersonGoncalves\NpmReadme;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use Throwable;

/**
 * Render the README of an npm package. Unlike a GitHub README (a live
 * conditional fetch + disk cache), the npm registry ships the README markdown
 * inline in the package document, so this just fetches that document, renders
 * the markdown and caches the resulting HTML.
 *
 * NOTE: the rendered HTML is UNTRUSTED. The default renderer STRIPS raw HTML so
 * an embedded <script> in a third-party README cannot become stored XSS. If you
 * need raw HTML kept ('allow'), provide your own `npm-readme.renderer` callable
 * AND sanitize its output yourself (e.g. with
 * jeffersongoncalves/laravel-html-sanitizer) before display.
 */
class NpmReadme
{
    /** npm's sentinel when a package ships no README. */
    private const NO_README = 'ERROR: No README data found!';

    /**
     * Return the rendered README HTML for an npmjs.com package URL, or null
     * when the URL isn't an npm package, the registry has no document, or the
     * package ships no README.
     */
    public static function fetchHtml(?string $npmUrl): ?string
    {
        $package = self::packageFromUrl($npmUrl);

        if ($package === null) {
            return null;
        }

        $cacheKey = 'npm_readme:'.$package;
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            // '' is the negative-cache sentinel for "resolved, but no README".
            // Cache::remember() can't do this: it treats a null payload as a
            // miss and re-fetches every call, so a package without a README
            // would hammer the registry on each request.
            return $cached === '' ? null : $cached;
        }

        $result = self::renderPackageReadme($package);

        // `false` flags a transient failure (network error / 5xx) — leave it
        // uncached so the next call retries. A string (HTML) or `null` ("no
        // README"/4xx) is a stable outcome: cache it, storing null as '' so the
        // negative result is honoured without another registry round-trip.
        if ($result === false) {
            return null;
        }

        Cache::put($cacheKey, $result ?? '', now()->addMinutes(self::cacheMinutes()));

        return $result;
    }

    /**
     * Extract the package identifier (`name` or `@scope/name`) from an
     * npmjs.com/package URL. Returns null for anything else.
     */
    public static function packageFromUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $url = trim($url);

        // Resolve the real host and whitelist it, so look-alikes like
        // `evilnpmjs.com` or `npmjs.com.evil.com` can't slip through a loose
        // substring match. parse_url() only fills the host when a scheme is
        // present, so assume https:// for scheme-less input.
        $host = parse_url($url, PHP_URL_HOST) ?? parse_url('https://'.$url, PHP_URL_HOST);

        if (! is_string($host) || ! in_array(strtolower($host), ['npmjs.com', 'www.npmjs.com'], true)) {
            return null;
        }

        if (! preg_match('~/package/(@[^/?#]+/[^/?#]+|[^/?#]+)~', $url, $m)) {
            return null;
        }

        return rtrim($m[1], '/');
    }

    private static function renderPackageReadme(string $package): string|false|null
    {
        try {
            $response = Http::timeout(self::timeout())
                ->withHeaders([
                    'User-Agent' => self::userAgent(),
                    'Accept' => 'application/json',
                ])
                ->get(self::registryUrl().'/'.self::encodePackage($package));
        } catch (Throwable $e) {
            Log::warning('NpmReadme registry fetch failed', [
                'package' => $package,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if (! $response->successful()) {
            // 4xx (not found / bad name) is a stable result worth negative-
            // caching; a 5xx is a transient registry hiccup, so retry later.
            return $response->clientError() ? null : false;
        }

        $readme = $response->json('readme');

        if (! is_string($readme)) {
            return null;
        }

        $readme = trim($readme);

        if ($readme === '' || $readme === self::NO_README) {
            return null;
        }

        return self::render($readme);
    }

    /**
     * Encode a package id for the registry path. Only the scope separator is
     * escaped: rawurlencode() would also turn the leading `@` into `%40` and
     * yield a 404, so scoped packages must be requested as `@scope%2Fname`.
     */
    private static function encodePackage(string $package): string
    {
        return str_replace('/', '%2F', $package);
    }

    private static function render(string $markdown): string
    {
        $renderer = config('npm-readme.renderer');

        if (is_callable($renderer)) {
            return (string) $renderer($markdown);
        }

        return self::defaultRenderer($markdown);
    }

    private static function defaultRenderer(string $markdown): string
    {
        $environment = new Environment([
            // Strip raw HTML from the third-party README rather than passing it
            // through ('allow'). Without this a malicious README could embed a
            // <script> tag that becomes stored XSS unless the consumer sanitizes.
            // Users who DO sanitize can opt back into 'allow' via a custom
            // `renderer` callable in the config.
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'heading_permalink' => [
                'symbol' => '#',
                'html_class' => 'md-anchor',
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new HeadingPermalinkExtension);

        return (new MarkdownConverter($environment))->convert($markdown)->getContent();
    }

    private static function cacheMinutes(): int
    {
        return (int) config('npm-readme.cache_minutes', 60);
    }

    private static function timeout(): int
    {
        return (int) config('npm-readme.timeout', 8);
    }

    private static function userAgent(): string
    {
        return (string) config('npm-readme.user_agent', 'laravel-npm-readme');
    }

    private static function registryUrl(): string
    {
        return rtrim((string) config('npm-readme.registry_url', 'https://registry.npmjs.org'), '/');
    }
}
