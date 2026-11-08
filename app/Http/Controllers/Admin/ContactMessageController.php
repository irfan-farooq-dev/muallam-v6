<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContactSubject;
use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Messages sent from the contact page. Admins reply from their own email (the page offers a reply link).
 */
class ContactMessageController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = in_array($request->query('status'), ['unread', 'read'], true) ? (string) $request->query('status') : '';
        $subject = ContactSubject::tryFrom((string) $request->query('subject'));

        $messages = ContactMessage::query()
            ->when($status === 'unread', fn (Builder $query) => $query->whereNull('read_at'))
            ->when($status === 'read', fn (Builder $query) => $query->whereNotNull('read_at'))
            ->when($subject, fn (Builder $query) => $query->where('subject', $subject))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn (Builder $match) => $match->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('message', 'like', $like));
            })
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.messages.index', [
            'messages' => $messages,
            'subjects' => ContactSubject::cases(),
            'filters' => ['search' => $search, 'status' => $status, 'subject' => $subject?->value ?? ''],
            'unreadCount' => ContactMessage::query()->whereNull('read_at')->count(),
        ]);
    }

    /**
     * Opening a message marks it as read, along with this admin's bell notification about it.
     */
    public function show(Request $request, ContactMessage $message): View
    {
        if ($message->read_at === null) {
            $message->forceFill(['read_at' => now()])->save();
        }

        $request->user()->unreadNotifications()
            ->where('data->contact_message_id', $message->getKey())
            ->update(['read_at' => now()]);

        return view('admin.messages.show', [
            'message' => $message->load('user:id,name,slug'),
        ]);
    }

    public function markUnread(ContactMessage $message): RedirectResponse
    {
        $message->forceFill(['read_at' => null])->save();

        return to_route('admin.messages.index')->with('success', 'Message marked as unread.');
    }

    public function destroy(ContactMessage $message): RedirectResponse
    {
        $message->delete();

        return to_route('admin.messages.index')->with('success', "Message from {$message->name} deleted.");
    }
}
