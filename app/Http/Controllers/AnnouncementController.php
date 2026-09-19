<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Requests\Announcement\StoreRequest;
use App\Models\AdminAnnouncement;
use App\Models\Certification;
use App\Models\User;
use App\UseCases\Announcement\StoreAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', AdminAnnouncement::class);

        return view('announcement.management.index', [
            'announcements' => AdminAnnouncement::query()
                ->with(['targetCertification', 'targetUser', 'createdBy'])
                ->latest('dispatched_at')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', AdminAnnouncement::class);

        return view('announcement.management.create', [
            'certifications' => Certification::query()->orderBy('name')->get(),
            'students' => User::query()
                ->where('role', UserRole::Student->value)
                ->where('status', UserStatus::InProgress->value)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $action($request->user(), $request->validated());

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', 'お知らせを配信しました。');
    }

    public function show(AdminAnnouncement $announcement): View
    {
        $this->authorize('view', $announcement);

        $announcement->load(['targetCertification', 'targetUser', 'createdBy']);

        return view('announcement.management.show', compact('announcement'));
    }
}
