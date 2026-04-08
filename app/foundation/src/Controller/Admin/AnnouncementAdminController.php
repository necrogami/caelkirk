<?php

declare(strict_types=1);

namespace App\Foundation\Controller\Admin;

use App\Foundation\Entity\Announcement;
use App\Foundation\Repository\AnnouncementRepository;
use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\PubSub\Message;
use Marko\PubSub\PublisherInterface;
use Marko\Routing\Attributes\Delete;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Attributes\Put;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

class AnnouncementAdminController
{
    public function __construct(
        private readonly ViewInterface $view,
        private readonly AnnouncementRepository $announcementRepository,
        private readonly PublisherInterface $publisher,
    ) {}

    #[Get('/admin/announcements')]
    #[Middleware(AdminAuthMiddleware::class)]
    #[RequiresPermission(permission: 'system.announcements')]
    public function index(): Response
    {
        $announcements = $this->announcementRepository->findAllOrdered();

        return $this->view->render('foundation::admin/announcements/index', [
            'announcements' => $announcements,
        ]);
    }

    #[Get('/admin/announcements/create')]
    #[Middleware(AdminAuthMiddleware::class)]
    #[RequiresPermission(permission: 'system.announcements')]
    public function create(): Response
    {
        return $this->view->render('foundation::admin/announcements/form', [
            'announcement' => null,
        ]);
    }

    #[Post('/admin/announcements')]
    #[Middleware(AdminAuthMiddleware::class)]
    #[RequiresPermission(permission: 'system.announcements')]
    public function store(Request $request): Response
    {
        $announcement = new Announcement();
        $this->fillFromRequest($announcement, $request);
        $this->announcementRepository->save($announcement);

        if ($announcement->active) {
            $this->publishAnnouncement($announcement);
        }

        return Response::redirect('/admin/announcements');
    }

    #[Get('/admin/announcements/{id}/edit')]
    #[Middleware(AdminAuthMiddleware::class)]
    #[RequiresPermission(permission: 'system.announcements')]
    public function edit(int $id): Response
    {
        $announcement = $this->announcementRepository->find($id);

        if ($announcement === null) {
            return Response::redirect('/admin/announcements');
        }

        return $this->view->render('foundation::admin/announcements/form', [
            'announcement' => $announcement,
        ]);
    }

    #[Put('/admin/announcements/{id}')]
    #[Middleware(AdminAuthMiddleware::class)]
    #[RequiresPermission(permission: 'system.announcements')]
    public function update(Request $request, int $id): Response
    {
        $announcement = $this->announcementRepository->find($id);

        if ($announcement === null) {
            return Response::redirect('/admin/announcements');
        }

        /** @var Announcement $announcement */
        $this->fillFromRequest($announcement, $request);
        $this->announcementRepository->save($announcement);

        return Response::redirect('/admin/announcements');
    }

    #[Delete('/admin/announcements/{id}')]
    #[Middleware(AdminAuthMiddleware::class)]
    #[RequiresPermission(permission: 'system.announcements')]
    public function destroy(int $id): Response
    {
        $announcement = $this->announcementRepository->find($id);

        if ($announcement !== null) {
            $this->announcementRepository->delete($announcement);
        }

        return Response::redirect('/admin/announcements');
    }

    private function fillFromRequest(Announcement $announcement, Request $request): void
    {
        $announcement->title = $request->post('title', '');
        $announcement->body = $request->post('body', '');
        $announcement->type = $request->post('type', 'info');
        $announcement->active = $request->post('active') === '1';

        $startsAt = $request->post('starts_at');
        $announcement->startsAt = $startsAt ? new \DateTimeImmutable($startsAt) : null;

        $endsAt = $request->post('ends_at');
        $announcement->endsAt = $endsAt ? new \DateTimeImmutable($endsAt) : null;
    }

    private function publishAnnouncement(Announcement $announcement): void
    {
        $this->publisher->publish(
            channel: 'global',
            message: new Message(
                channel: 'global',
                payload: json_encode([
                    'type' => 'announcement',
                    'title' => $announcement->title,
                    'body' => $announcement->body,
                    'announcementType' => $announcement->type,
                ]),
            ),
        );
    }
}
