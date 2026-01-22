<?php
declare(strict_types=1);

namespace Modules\Api\Support;

use Core\Logger;
use Core\Request;
use Core\Response;

trait JsonNegotiationTrait
{
    protected function respond(Request $req, array $data, int $status = 200): Response
    {
        $accept = $req->headers['accept'] ?? '';
        $isJson = str_contains($accept, 'application/json') || str_starts_with($req->path, '/api/');
        if (str_starts_with($req->path, '/api/')) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            if (function_exists('header_remove')) {
                header_remove('Set-Cookie');
            }
        }
        if ($isJson) {
            return $this->jsonResponse($data, $status);
        }

        if (isset($data['_html']) && is_string($data['_html'])) {
            return new Response($data['_html'], $status);
        }

        if (isset($data['_view']) && is_string($data['_view'])) {
            $payload = $data['_data'] ?? $data;
            $meta = $data['_meta'] ?? null;
            if (property_exists($this, 'container') && $this->container) {
                try {
                    $renderer = $this->container->get('renderer');
                    $html = $meta !== null
                        ? $renderer->render($data['_view'], $payload, $meta)
                        : $renderer->render($data['_view'], $payload);
                    return new Response($html, $status);
                } catch (\Throwable $e) {
                    Logger::log('Render failed: ' . $e->getMessage());
                }
            }
        }

        return new Response('', $status);
    }

    private function jsonResponse(array $data, int $status): Response
    {
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            return new Response($json, $status, ['Content-Type' => 'application/json; charset=utf-8']);
        } catch (\Throwable $e) {
            Logger::log('JSON encode failed: ' . $e->getMessage());
            return new Response('{"error":"internal_error"}', 500, ['Content-Type' => 'application/json; charset=utf-8']);
        }
    }
}
