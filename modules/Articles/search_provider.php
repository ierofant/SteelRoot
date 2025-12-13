<?php
use Modules\Articles\Search\ArticlesSearchProvider;

return new ArticlesSearchProvider($container->get(\Core\Database::class));
