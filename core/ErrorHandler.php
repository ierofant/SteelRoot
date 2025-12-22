<?php
namespace Core;

use Throwable;

class ErrorHandler
{
    private Renderer $renderer;

    public function __construct(Renderer $renderer)
    {
        $this->renderer = $renderer;
    }

    public function register(): void
    {
        set_exception_handler([$this, 'handleException']);
        set_error_handler(function ($severity, $message, $file, $line) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
    }

    public function handleException(Throwable $e): void
    {
        http_response_code(500);
        try {
            echo $this->renderer->render(
                'errors/500',
                ['_layout' => true, 'error' => $e],
                ['title' => 'Server error', 'description' => 'Internal server error']
            );
        } catch (Throwable $t) {
            echo 'Server error';
        }
    }
}
