<?php
use Modules\Gallery\Search\GallerySearchProvider;

return new GallerySearchProvider($container->get(\Core\Database::class));
