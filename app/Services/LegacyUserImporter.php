<?php

namespace App\Services;

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Copies the accounts of the old muallam.com into this site. Members keep their passwords
 * (the old site used bcrypt too). Accounts whose email already exists here are left alone,
 * so running it again is safe.
 */
class LegacyUserImporter
{
    /**
     * Old role numbers: 10 super admin, 20 admin, 30 user.
     */
    private const OLD_ADMIN_ROLES = [10, 20];

    private const PHONE_PATTERN = '/^\+?[0-9][0-9\s\-()]{6,19}$/';

    private const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function __construct(private UserSlugGenerator $slugs) {}

    /**
     * @return list<array{old_id: int, name: string, email: string, result: 'imported'|'skipped', note: string}>
     */
    public function import(ConnectionInterface $legacy, bool $dryRun = false): array
    {
        $report = [];

        foreach ($legacy->table('users')->whereNull('deleted_at')->orderBy('id')->get() as $old) {
            $email = Str::lower(trim((string) $old->email));
            $name = Str::limit(Str::squish(strip_tags((string) $old->name)), 100, '');
            $line = ['old_id' => (int) $old->id, 'name' => $name, 'email' => $email];

            if ($reason = $this->reasonToSkip($old, $email)) {
                $report[] = [...$line, 'result' => 'skipped', 'note' => $reason];

                continue;
            }

            $slug = $this->slugFor((string) $old->slug, $name);
            $notes = array_filter([
                $slug !== $old->slug ? "new address {$slug} (old one was {$old->slug})" : null,
                in_array((int) $old->role, self::OLD_ADMIN_ROLES, true) ? 'admin' : null,
                (int) $old->status === 0 ? 'deactivated' : null,
            ]);

            if (! $dryRun) {
                $this->insert($old, $email, $name, $slug);
            }

            $report[] = [...$line, 'result' => 'imported', 'note' => implode('; ', $notes)];
        }

        return $report;
    }

    private function reasonToSkip(object $old, string $email): ?string
    {
        if (preg_match('/<[^>]+>|https?:\/\/|www\./i', (string) $old->name)) {
            return 'spam account (link in the name)';
        }

        if ($this->looksMachineGenerated((string) $old->name)) {
            return 'bot sign-up (random-letter name)';
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'invalid email address';
        }

        if (! str_starts_with((string) $old->password, '$2y$')) {
            return 'password is not a bcrypt hash';
        }

        if (User::query()->where('email', $email)->exists()) {
            return 'email already registered here';
        }

        return null;
    }

    /**
     * Bots signed up with names like "wkOgcgiPKtmjPHYcgvs": one long word of letters with capitals scattered inside.
     */
    private function looksMachineGenerated(string $name): bool
    {
        $name = trim($name);

        return preg_match('/^[A-Za-z]{12,}$/', $name) === 1
            && preg_match_all('/[A-Z]/', substr($name, 1)) >= 3;
    }

    /**
     * Keeps the old profile address when it is still valid here, otherwise makes a new one from the name.
     */
    private function slugFor(string $oldSlug, string $name): string
    {
        $usable = strlen($oldSlug) >= UserSlugGenerator::MIN_LENGTH
            && strlen($oldSlug) <= UserSlugGenerator::MAX_LENGTH
            && preg_match(self::SLUG_PATTERN, $oldSlug)
            && ! $this->slugs->isReserved($oldSlug)
            && ! User::query()->where('slug', $oldSlug)->exists()
            && ! DB::table('user_slug_history')->where('slug', $oldSlug)->exists();

        return $usable ? $oldSlug : $this->slugs->generate($name);
    }

    /**
     * Inserted without the model so the existing bcrypt hash is stored as it is
     * (Laravel re-hashes it with the current cost on the member's next sign-in).
     */
    private function insert(object $old, string $email, string $name, string $slug): void
    {
        $phone = Str::squish((string) $old->phone);
        $createdAt = $old->created_at ?? now();

        User::query()->toBase()->insert([
            'name' => $name,
            'slug' => $slug,
            'email' => $email,
            'email_verified_at' => $old->email_verified_at,
            'phone' => preg_match(self::PHONE_PATTERN, $phone) ? $phone : null,
            'gender' => Gender::tryFrom(Str::lower((string) $old->gender))?->value,
            'password' => $old->password,
            'role' => in_array((int) $old->role, self::OLD_ADMIN_ROLES, true) ? UserRole::Admin->value : UserRole::Author->value,
            'is_active' => (int) $old->status !== 0,
            'created_at' => $createdAt,
            'updated_at' => $old->updated_at ?? $createdAt,
        ]);
    }
}
