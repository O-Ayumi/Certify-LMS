<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\InvitationStatus;
use App\Models\Invitation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectAcceptedInvitation
{
    public function handle(Request $request, Closure $next): Response
    {
        $invitation = $request->route('invitation');

        if (! $request->hasValidSignature()) {
            return $next($request);
        }

        if (
            ! ($invitation instanceof Invitation)
            || $invitation->status !== InvitationStatus::Accepted
        ) {
            return $next($request);
        }

        return response()->view('auth.invitation-invalid', status: 410);
    }
}
