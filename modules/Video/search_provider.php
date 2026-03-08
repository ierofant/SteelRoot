<?php
use Modules\Video\Search\VideoSearchProvider;

return new VideoSearchProvider($container->get(\Core\Database::class));
