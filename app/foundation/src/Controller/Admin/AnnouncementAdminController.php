<?php

declare(strict_types=1);

namespace App\Foundation\Controller\Admin;

use App\Foundation\Entity\Announcement;
use App\Foundation\Repository\AnnouncementRepository;
use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\PubSub\Message;
use Marko\PubSub\PublisherInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\Security\Middleware\CsrfMiddleware;
use Marko\Security\Middleware\SecurityHeadersMiddleware;
use Marko\View\ViewInterface;

#[Middleware([SecurityHeadersMiddleware::class, CsrfMiddleware::class])]
class AnnouncementAdminController
{
    public function __construct(
        private readonly ViewInterface $view,
        private readonly AnnouncementRepository $announcementRepository,
        private readonly PublisherInterface $publisher,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    #[Get('/admin/announcements')]
    #[Middleware(AdminAuthMiddleware::class)]
    #[RequiresPermission(permission: 'system.announcements')]
    public function index(): Response
    {
        $announcements = $this->announcementRepository->findAllOrdered();

        return $this->view->render('foundation::admin/announcements/index', [
            'announcements' => $announcements,
            'csrfToken' => $this->csrfTokenManager->get(),
        ]);
    }

    #[Get('/admin/announcements/create')]
    #[Middleware(AdminAuthMiddleware::class)]
    #[RequiresPermission(permission: 'system.announcements')]
    public function create(): Response
    {
        return $this->view->render('foundation::admin/announcements/form', [
            'announcement' => null,
            'csrfToken' => $this->csrfTokenManager->get(),
        ]);
    }

    #[Post('/admin/announcements')]
    #[Middleware(AdminAuthMiddleware::class)]
    #[RequiresPermission(permission: 'system.announcements')]
    public function store(Request $request): Response
    {
        $announcement = new Announcement();
        $this->fillFromRequest($announcement, $request);
        $announcement->createdAt = new \DateTimeImmutable();
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
            'csrfToken' => $this->csrfTokenManager->get(),
        ]);
    }

    #[Post('/admin/announcements/{id}')]
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

    #[Post('/admin/announcements/{id}/delete')]
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
        $announcement->type = in_array($request->post('type'), ['info', 'warning', 'maintenance'], true)
            ? $request->post('type')
            : 'info';
        $announcement->active = $request->post('active') === '1';

        $startsAt = $request->post('starts_at');
        $announcement->startsAt = $this->parseDate($startsAt);

        $endsAt = $request->post('ends_at');
        $announcement->endsAt = $this->parseDate($endsAt);
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\DateMalformedStringException) {
            return null;
        }
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
