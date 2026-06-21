# Laravel npm Readme

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jeffersongoncalves/laravel-npm-readme.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-npm-readme)
[![Total Downloads](https://img.shields.io/packagist/dt/jeffersongoncalves/laravel-npm-readme.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-npm-readme)

Fetch an npm package's README straight from the registry document, render the markdown and cache the resulting HTML. The npm registry ships the README markdown inline in the package document, so there is no extra request beyond the registry call.

This is the npm sibling of [`jeffersongoncalves/laravel-github-readme`](https://github.com/jeffersongoncalves/laravel-github-readme).

## Installation

```bash
composer require jeffersongoncalves/laravel-npm-readme
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag="npm-readme-config"
```

## Usage

```php
use JeffersonGoncalves\NpmReadme\NpmReadme;

$html = NpmReadme::fetchHtml('https://www.npmjs.com/package/laravel-echo');
// or a scoped package:
$html = NpmReadme::fetchHtml('https://www.npmjs.com/package/@tailwindcss/vite');
```

`fetchHtml()` returns the rendered HTML, or `null` when the URL isn't an npm package, the registry has no document, or the package ships no README. Results are cached on the default cache store (`npm_readme:{package}`) for `config('npm-readme.cache_minutes')`.

`NpmReadme::packageFromUrl($url)` is also public if you only need the package identifier.

## Security

The rendered HTML is **untrusted** (third-party package READMEs). The default renderer therefore **strips raw HTML** (`html_input` = `strip`), so an embedded `<script>` cannot become stored XSS.

If you need raw HTML kept, provide your own `renderer` callable in `config/npm-readme.php` — the output is then unsafe and you **must** sanitize it before display, e.g. with [`jeffersongoncalves/laravel-html-sanitizer`](https://github.com/jeffersongoncalves/laravel-html-sanitizer):

```php
// config/npm-readme.php
'renderer' => [\App\Support\Markdown::class, 'render'],
```

## Configuration

| Key | Default | Description |
| --- | --- | --- |
| `cache_minutes` | `60` | Minutes the rendered HTML is cached per package. |
| `registry_url` | `https://registry.npmjs.org` | npm registry base URL. |
| `timeout` | `8` | Registry request timeout in seconds. |
| `user_agent` | `laravel-npm-readme` | `User-Agent` header for the registry request. |
| `renderer` | `null` | Optional `callable(string $markdown): string`. When null, an internal CommonMark renderer (GFM + heading permalinks, raw HTML stripped) is used. |

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
