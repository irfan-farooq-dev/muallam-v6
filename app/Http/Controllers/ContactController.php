<?php

namespace App\Http\Controllers;

use App\Enums\ContactSubject;
use App\Http\Requests\ContactRequest;
use App\Models\ContactMessage;
use App\Services\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function create(): View
    {
        return view('pages.contact', [
            'subjects' => ContactSubject::cases(),
        ]);
    }

    public function store(ContactRequest $request, Notifier $notifier): RedirectResponse
    {
        $thanks = 'Thank you, your message has been sent. We will reply to '.$request->validated('email').' as soon as we can.';

        // A filled-in hidden field means a bot: act as if it worked, but keep nothing.
        if ($request->filled('website')) {
            return to_route('contact')->with('success', $thanks);
        }

        $message = new ContactMessage($request->safe()->only(['name', 'email', 'subject', 'message']));
        $message->user()->associate($request->user());
        $message->ip_address = $request->ip();
        $message->save();

        $notifier->contactMessageReceived($message);

        return to_route('contact')->with('success', $thanks);
    }
}
