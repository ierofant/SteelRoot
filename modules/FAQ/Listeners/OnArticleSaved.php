<?php
namespace Modules\FAQ\Listeners;

use Core\Logger;

class OnArticleSaved
{
    public function __construct()
    {
    }

    public function handle(array $payload = []): void
    {
        Logger::log('FAQ listener received article.saved: ' . json_encode($payload));
    }
}
