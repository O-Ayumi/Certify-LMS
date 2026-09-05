<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Http\Requests\QaThread\IndexRequest;
use App\Http\Requests\QaThread\StoreRequest;
use App\Http\Requests\QaThread\UpdateRequest;
use App\Models\Certification;
use App\Models\QaThread;
use App\UseCases\QaThread\DestroyAction;
use App\UseCases\QaThread\IndexAction;
use App\UseCases\QaThread\ResolveAction;
use App\UseCases\QaThread\ShowAction;
use App\UseCases\QaThread\StoreAction;
use App\UseCases\QaThread\UnresolveAction;
use App\UseCases\QaThread\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** 質問掲示板のスレッド一覧・CRUD・解決状態変更を受け付ける Controller。 */
class QaThreadController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $admin = $request->routeIs('admin.*');
        $certifications = Certification::query()
            ->when(! $admin, fn ($query) => $query->published()
                ->when($request->user()->role === UserRole::Coach, fn ($certifications) => $certifications->assignedTo($request->user())))
            ->orderBy('name')->get();

        return view('qa-thread.index', [
            'threads' => $action($request->user(), $request->validated()),
            'filters' => $request->validated(),
            'certifications' => $certifications,
            'publishedStatus' => CertificationStatus::Published,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', QaThread::class);

        return view('qa-thread.create', ['certifications' => Certification::published()->orderBy('name')->get()]);
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $thread = $action($request->user(), $request->validated());

        return redirect()->route('qa-board.show', $thread)->with('success', '質問を投稿しました。');
    }

    public function show(Request $request, QaThread $thread, ShowAction $action): View
    {
        $this->authorizePublicAccess($request, $thread);
        $this->authorize('view', $thread);

        return view('qa-thread.show', ['thread' => $action($thread)]);
    }

    public function edit(Request $request, QaThread $thread): View
    {
        $this->authorizePublicAccess($request, $thread);
        $this->authorize('update', $thread);

        return view('qa-thread.edit', ['thread' => $thread->load('certification')]);
    }

    public function update(UpdateRequest $request, QaThread $thread, UpdateAction $action): RedirectResponse
    {
        $this->authorizePublicAccess($request, $thread);
        $action($thread, $request->validated());

        return redirect()->route('qa-board.show', $thread)->with('success', '質問を更新しました。');
    }

    public function destroy(Request $request, QaThread $thread, DestroyAction $action): RedirectResponse
    {
        $this->authorizePublicAccess($request, $thread);
        $this->authorize('delete', $thread);
        $action($thread, $request->user());

        return redirect()->route($request->routeIs('admin.*') ? 'admin.qa-board.index' : 'qa-board.index')->with('success', '質問を削除しました。');
    }

    public function resolve(Request $request, QaThread $thread, ResolveAction $action): RedirectResponse
    {
        $this->authorizePublicAccess($request, $thread);
        $this->authorize('resolve', $thread);
        $action($thread);

        return redirect()->route('qa-board.show', $thread)->with('success', '質問を解決済みにしました。');
    }

    public function unresolve(Request $request, QaThread $thread, UnresolveAction $action): RedirectResponse
    {
        $this->authorizePublicAccess($request, $thread);
        $this->authorize('unresolve', $thread);
        $action($thread);

        return redirect()->route('qa-board.show', $thread)->with('success', '質問を未解決に戻しました。');
    }

    private function authorizePublicAccess(Request $request, QaThread $thread): void
    {
        if ($request->routeIs('admin.*')) {
            return;
        }
        $thread->loadMissing('certification');
        abort_if($thread->certification->status !== CertificationStatus::Published, 404);
    }
}
