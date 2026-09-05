<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CertificationStatus;
use App\Http\Requests\QaReply\StoreRequest;
use App\Http\Requests\QaReply\UpdateRequest;
use App\Models\QaReply;
use App\Models\QaThread;
use App\UseCases\QaReply\DestroyAction;
use App\UseCases\QaReply\StoreAction;
use App\UseCases\QaReply\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QaReplyController extends Controller
{
    public function store(StoreRequest $request, QaThread $thread, StoreAction $action): RedirectResponse
    {
        $this->visible($request, $thread);
        $action($request->user(), $thread, $request->validated()['body']);

        return redirect()->route('qa-board.show', $thread);
    }

    public function edit(Request $request, QaThread $thread, QaReply $reply): View
    {
        $this->visible($request, $thread);
        $this->authorize('update', $reply);

        return view('qa-thread.reply-edit', compact('thread', 'reply'));
    }

    public function update(UpdateRequest $request, QaThread $thread, QaReply $reply, UpdateAction $action): RedirectResponse
    {
        $this->visible($request, $thread);
        $action($reply, $request->validated()['body']);

        return redirect()->route('qa-board.show', $thread);
    }

    public function destroy(Request $request, QaThread $thread, QaReply $reply, DestroyAction $action): RedirectResponse
    {
        $this->visible($request, $thread);
        $this->authorize('delete', $reply);
        $action($reply);

        return redirect()->route($request->routeIs('admin.*') ? 'admin.qa-board.show' : 'qa-board.show', $thread);
    }

    private function visible(Request $request, QaThread $thread): void
    {
        if ($request->routeIs('admin.*')) {
            return;
        }
        $thread->loadMissing('certification');
        abort_if($thread->certification->status !== CertificationStatus::Published, 404);
    }
}
