<?php
declare(strict_types=1);

namespace Modules\Comments\Controllers;

use Core\Container;
use Core\Request;
use Core\Response;
use Modules\Comments\Services\CommentService;

class AdminCommentsController
{
    private CommentService $comments;

    public function __construct(Container $container)
    {
        $this->comments = $container->get(CommentService::class);
    }

    public function index(Request $request): Response
    {
        return $this->comments->renderAdminIndex($request);
    }

    public function settings(Request $request): Response
    {
        return $this->comments->renderSettings($request);
    }

    public function saveSettings(Request $request): Response
    {
        return $this->comments->saveSettings($request);
    }

    public function approve(Request $request): Response
    {
        return $this->comments->moderateAction($request, 'approved');
    }

    public function reject(Request $request): Response
    {
        return $this->comments->moderateAction($request, 'rejected');
    }

    public function spam(Request $request): Response
    {
        return $this->comments->moderateAction($request, 'spam');
    }

    public function delete(Request $request): Response
    {
        return $this->comments->moderateAction($request, 'deleted');
    }

    public function purge(Request $request): Response
    {
        return $this->comments->purgeAction($request);
    }

    public function bulk(Request $request): Response
    {
        return $this->comments->bulkAction($request);
    }
}
