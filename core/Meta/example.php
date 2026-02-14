<?php
/**
 * JSON-LD Usage Examples for SteelRoot
 *
 * This file demonstrates how to use JsonLdRenderer and CommonSchemas.
 * DO NOT execute this file directly - it's for reference only.
 */

require_once __DIR__ . '/JsonLdRenderer.php';
require_once __DIR__ . '/CommonSchemas.php';

use Core\Meta\JsonLdRenderer;
use Core\Meta\CommonSchemas;

// Example 1: Single Organization Schema
$orgSchema = CommonSchemas::organization([
    'name' => 'SteelRoot',
    'url' => 'https://example.com',
    'logo' => 'https://example.com/logo.png'
]);
$html = JsonLdRenderer::render($orgSchema);
// Output: <script type="application/ld+json">...</script>

// Example 2: Article with Organization
$articleSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => 'Getting Started with SteelRoot',
    'url' => 'https://example.com/articles/getting-started',
    'datePublished' => '2025-02-15T10:00:00+00:00',
    'dateModified' => '2025-02-15T12:00:00+00:00',
    'description' => 'Learn how to build fast websites with SteelRoot CMS',
    'image' => 'https://example.com/uploads/article-cover.jpg'
];

$mergedSchema = JsonLdRenderer::merge($articleSchema, $orgSchema);
$html = JsonLdRenderer::render($mergedSchema);
// Output: Merged schema with @graph containing both Article and Organization

// Example 3: BreadcrumbList
$breadcrumbs = CommonSchemas::breadcrumbList([
    ['name' => 'Home', 'url' => 'https://example.com'],
    ['name' => 'Articles', 'url' => 'https://example.com/articles'],
    ['name' => 'Getting Started'] // Last item without URL
]);
$html = JsonLdRenderer::render($breadcrumbs);

// Example 4: Multiple schemas
$websiteSchema = CommonSchemas::webSite([
    'name' => 'SteelRoot CMS',
    'url' => 'https://example.com'
]);

$allSchemas = JsonLdRenderer::merge($articleSchema, $orgSchema, $websiteSchema);
$html = JsonLdRenderer::render($allSchemas);
// Output: @graph with 3 schemas

// Example 5: Empty schema (safe handling)
$emptySchema = JsonLdRenderer::render([]);
// Output: '' (empty string)

echo "✅ All examples are valid!\n";
echo "See JSON_LD_IMPLEMENTATION.md for full documentation.\n";
