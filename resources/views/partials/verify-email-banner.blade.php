{{-- Reminder for signed-in users whose email is not verified yet (hidden on the "check your inbox" page itself). --}}
@auth
    @if (! auth()->user()->hasVerifiedEmail() && ! request()->routeIs('verification.notice'))
        <div class="verify-email-banner">
            <div class="container d-flex flex-wrap align-items-center justify-content-between gap-2 py-2">
                <span>
                    <i class="bi bi-envelope-exclamation me-1"></i>
                    Please verify your email address. We sent a link to <strong>{{ auth()->user()->email }}</strong>.
                </span>
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-accent">Resend email</button>
                </form>
            </div>
        </div>
    @endif
@endauth
