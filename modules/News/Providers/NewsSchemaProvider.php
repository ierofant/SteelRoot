<?php
namespace Modules\News\Providers;

use Modules\Articles\Providers\ArticleSchemaProvider;

/**
 * Generates Schema.org NewsArticle structured data.
 * Identical to ArticleSchemaProvider but with type = NewsArticle.
 */
class NewsSchemaProvider extends ArticleSchemaProvider
{
    protected string $schemaType = 'NewsArticle';
}
