# Core Meta Module

Provides JSON-LD structured data infrastructure for SteelRoot CMS.

## Files

- **JsonLdRenderer.php** - Renders and merges JSON-LD schemas
- **CommonSchemas.php** - Common Schema.org templates (Organization, WebSite, BreadcrumbList)
- **MetaResolver.php** - Meta contract resolver (existing)

## Quick Start

```php
use Core\Meta\JsonLdRenderer;
use Core\Meta\CommonSchemas;

// Create schemas
$article = [
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => 'Article Title',
    'url' => 'https://example.com/article',
];

$org = CommonSchemas::organization([
    'name' => 'Company',
    'url' => 'https://example.com',
    'logo' => 'https://example.com/logo.png',
]);

// Merge and render
$merged = JsonLdRenderer::merge($article, $org);
$jsonLd = JsonLdRenderer::render($merged);

// Pass to renderer
$renderer->render('view', $data, ['jsonld' => $jsonLd]);
```

See `/JSON_LD_IMPLEMENTATION.md` for full documentation.
