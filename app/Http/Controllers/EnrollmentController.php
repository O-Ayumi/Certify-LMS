<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Http\Requests\Enrollment\StoreRequest;
use App\Http\Requests\Enrollment\UpdateExamDateRequest;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Services\Learning\LearningProgressService;
use App\UseCases\Enrollment\DestroyAction;
use App\UseCases\Enrollment\IndexAction;
use App\UseCases\Enrollment\ResumeAction;
use App\UseCases\Enrollment\ShowAction;
use App\UseCases\Enrollment\StoreAction;
use App\UseCases\Enrollment\UpdateExamDateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EnrollmentController extends Controller
{
    public function index(Request $request, IndexAction $action): View
    {
        $this->authorize('viewAny', Enrollment::class);
        $user = auth()->user();
        if ($user->role === UserRole::Student) {
            return view('enrollment.index', ['enrollments' => $action($user)]);
        }
        $query = Enrollment::query()->forUser($user)
            ->with(['user', 'certification.category', 'latestStatusLog']);
        if ($user->role === UserRole::Admin && $request->boolean('with_trashed')) {
            $query->withTrashed();
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', EnrollmentStatus::from($status)->value);
        }
        if ($certificationId = $request->string('certification_id')->toString()) {
            $query->where('certification_id', $certificationId);
        }
        if ($keyword = trim($request->string('keyword')->toString())) {
            $query->whereHas('user', fn ($q) => $q
                ->where('name', 'LIKE', '%'.$keyword.'%')
                ->orWhere('email', 'LIKE', '%'.$keyword.'%'));
        }
        $enrollments = $query->orderByDesc('created_at')->paginate(20)->withQueryString();
        $certifications = $user->role === UserRole::Admin
            ? Certification::query()->orderBy('name')->get()
            : $user->assignedCertifications()->orderBy('name')->get();

        return view('enrollment.index', [
            'enrollments' => $enrollments,
            'status' => $request->string('status')->toString(),
            'certification_id' => $request->string('certification_id')->toString(),
            'keyword' => $request->string('keyword')->toString(),
            'withTrashed' => $request->boolean('with_trashed'),
            'certifications' => $certifications,
        ]);
    }

    public function show(Enrollment $enrollment, ShowAction $action, LearningProgressService $progressService): View
    {
        $this->authorize('view', $enrollment);
        $action($enrollment);
        $user = auth()->user();
        $progress = null;
        if (in_array($user->role, [UserRole::Coach, UserRole::Admin], true)) {
            $enrollment->loadMissing(['user']);
            $progress = $progressService->summarize($enrollment);
        }
        if ($user->role === UserRole::Admin) {
            $enrollment->loadMissing([
                'statusLogs' => fn ($q) => $q->with('changedBy')->orderByDesc('changed_at'),
            ]);
        }

        return view('enrollment.show', ['enrollment' => $enrollment, 'progress' => $progress]);
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $enrollment = $action(auth()->user(), $request->validated());

        return redirect()->route('enrollments.show', $enrollment)->with('success', '受講登録が完了しました。');
    }

    public function destroy(Enrollment $enrollment, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $enrollment);
        $action($enrollment);

        return redirect()->route('enrollments.index')->with('success', '受講登録を解除しました。');
    }

    public function resume(Enrollment $enrollment, ResumeAction $action): RedirectResponse
    {
        $this->authorize('resume', $enrollment);
        $action($enrollment, auth()->user());

        return redirect()->route('enrollments.show', $enrollment)->with('success', '学習を再開しました。');
    }

    public function updateExamDate(
        Enrollment $enrollment,
        UpdateExamDateRequest $request,
        UpdateExamDateAction $action,
    ): RedirectResponse {
        $action($enrollment, $request->validated());

        return redirect()->route('enrollments.show', $enrollment)->with('success', '目標受験日を更新しました。');
    }
}
