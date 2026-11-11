<?php

namespace App\Http\Controllers;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Keeps the admin panel (session cookie) in step with the portal (API token).
 *
 * The portal and /admin authenticate separately, so without this a browser could be signed in
 * as one person in the portal and another person in /admin.
 */
class AdminHandoffController extends Controller
{
    private const TTL_SECONDS = 60;

    /**
     * API: a one-time link that signs the current portal user into /admin.
     */
    public function link(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->canAccessPanel(Filament::getPanel('admin')), 403);

        $nonce = Str::random(40);
        Cache::put("admin-handoff:{$nonce}", $user->id, self::TTL_SECONDS);

        return response()->json([
            'url' => URL::temporarySignedRoute('admin.handoff', now()->addSeconds(self::TTL_SECONDS), ['nonce' => $nonce]),
        ]);
    }

    /**
     * Web (signed): sign into /admin as the portal user, replacing whoever was signed in before.
     */
    public function enter(Request $request): RedirectResponse
    {
        // Single use: a copied or replayed link stops working once it has been opened.
        $userId = Cache::pull('admin-handoff:' . $request->query('nonce'));
        abort_unless($userId, 403, 'This link has expired. Use Command Center again.');

        $user = User::find($userId);
        abort_unless($user && $user->canAccessPanel(Filament::getPanel('admin')), 403);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->to(Filament::getPanel('admin')->getUrl());
    }

    /**
     * API: a link the portal opens after logging out, to end the /admin session in this browser too.
     */
    public function logoutLink(Request $request): JsonResponse
    {
        return response()->json([
            'url' => URL::temporarySignedRoute('admin.session-end', now()->addSeconds(self::TTL_SECONDS)),
        ]);
    }

    /**
     * Web (signed): end the /admin session.
     */
    public function end(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
