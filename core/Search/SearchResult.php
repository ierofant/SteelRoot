<?php
namespace Core\Search;

class SearchResult
{
    public string $title;
    public string $snippet;
    public string $url;
    public ?string $image;
    public ?string $meta;

    public function __construct(string $title, string $snippet, string $url, ?string $image = null, ?string $meta = null)
    {
        $this->title = $title;
        $this->snippet = $snippet;
        $this->url = $url;
        $this->image = $image;
        $this->meta = $meta;
    }
}
