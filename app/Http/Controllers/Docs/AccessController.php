<?php

namespace App\Http\Controllers\Docs;

use App\Models\DocsAccessRequest;
use App\Services\InboxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Entering access codes, and asking a system's owners for access.
 */
class AccessController extends DocsController
{
    public function show(): View
    {
        return view('docs.access', ['codes' => $this->access()->codes()->load('grants.system', 'grants.audiences')]);
    }

    public function unlock(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'return' => ['nullable', 'string', 'max:500'],
        ]);

        $code = $this->access()->unlock($data['code']);

        if (!$code) {
            return back()->withErrors(['code' => 'That code is not valid, or it has expired.'])->withInput();
        }

        $return = $data['return'] ?? null;
        // Only return to a page on this site.
        $target = $return && str_starts_with($return, '/') && !str_starts_with($return, '//') ? url($return) : $this->context()->url('docs/access');

        return redirect()->to($target)->with('docs_unlocked', $code->name);
    }

    public function forget(): RedirectResponse
    {
        $this->access()->forgetCodes();

        return redirect()->to($this->context()->home())->with('docs_status', 'Access codes removed from this browser.');
    }

    /**
     * A reader asks for access to a restricted manual; its owners get an inbox message.
     */
    public function request(Request $request, string $system, InboxService $inbox): RedirectResponse
    {
        $system = $this->findSystem($system);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'about' => ['nullable', 'string', 'max:255'],
        ]);
        $about = $data['about'] ?? null;
        unset($data['about']);
        if ($about) {
            // Which page they were on, e.g. "Finance guide (for Finance)".
            $data['reason'] = trim("Asked about: {$about}\n\n" . ($data['reason'] ?? ''));
        }

        DocsAccessRequest::create($data + ['system_id' => $system->id, 'ip' => $request->ip()]);

        $inbox->sendMessage(
            organisationId: (int) $system->organisation_id,
            senderId: null,
            recipientIds: $system->approverIds(),
            subject: "Documentation access request: {$system->name}",
            body: "{$data['name']} ({$data['email']}) asked for access to the {$system->name} manual in the documentation library."
                . (!empty($data['reason']) ? "\n\nReason: {$data['reason']}" : '')
                . "\n\nTo give access, create an access code under Knowledge > Access Codes and send it to them. Requests are listed under Knowledge > Access Requests.",
            messageType: 'system',
        );

        return back()->with('docs_request_sent', true);
    }
}
