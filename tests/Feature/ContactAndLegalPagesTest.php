<?php

namespace Tests\Feature;

use App\Enums\ContactSubject;
use App\Enums\NotificationKind;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContactAndLegalPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function input(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Sara Ahmed',
            'email' => 'sara@example.com',
            'subject' => ContactSubject::Suggestion->value,
            'message' => 'Please add a dark mode to the site.',
        ], $overrides);
    }

    public function test_contact_page_offers_the_old_sites_subjects(): void
    {
        $this->get(route('contact'))
            ->assertOk()
            ->assertSeeInOrder(['General feedback', 'Suggestion', 'Complaint', 'Marketing', 'Technical issue'])
            ->assertSee(config('muallam.contact_email'))
            ->assertSee('name="website"', false);
    }

    public function test_signed_in_member_gets_name_and_email_filled_in(): void
    {
        $member = User::factory()->create(['name' => 'Bilal Khan', 'email' => 'bilal@example.com']);

        $this->actingAs($member)->get(route('contact'))
            ->assertSee('value="Bilal Khan"', false)
            ->assertSee('value="bilal@example.com"', false);
    }

    public function test_message_is_saved_and_admins_are_notified(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post(route('contact.store'), $this->input(['email' => '  SARA@Example.com ']))
            ->assertRedirect(route('contact'))
            ->assertSessionHas('success', 'Thank you, your message has been sent. We will reply to sara@example.com as soon as we can.');

        $message = ContactMessage::sole();
        $this->assertSame('Sara Ahmed', $message->name);
        $this->assertSame('sara@example.com', $message->email);
        $this->assertSame(ContactSubject::Suggestion, $message->subject);
        $this->assertNull($message->user_id);
        $this->assertNull($message->read_at);
        $this->assertNotNull($message->ip_address);

        $notification = $admin->notifications()->sole()->data;
        $this->assertSame(NotificationKind::ContactMessage->value, $notification['kind']);
        $this->assertSame('New message from Sara Ahmed: Suggestion', $notification['message']);
        $this->assertSame('/admin/messages/'.$message->id, $notification['url']);
    }

    public function test_opening_a_message_in_the_inbox_clears_its_bell_notification(): void
    {
        $admin = User::factory()->admin()->create();
        $this->post(route('contact.store'), $this->input());
        $this->assertSame(1, $admin->unreadNotifications()->count());

        $this->actingAs($admin)->get(route('admin.messages.show', ContactMessage::sole()))->assertOk();

        $this->assertSame(0, $admin->unreadNotifications()->count());
    }

    public function test_signed_in_sender_is_linked_to_the_message(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->post(route('contact.store'), $this->input());

        $this->assertTrue(ContactMessage::sole()->user->is($member));
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidInput(): array
    {
        return [
            'missing name' => [['name' => ''], 'name'],
            'bad email' => [['email' => 'not-an-email'], 'email'],
            'no subject' => [['subject' => ''], 'subject'],
            'unknown subject' => [['subject' => 'lottery'], 'subject'],
            'message too short' => [['message' => 'Hi'], 'message'],
            'message too long' => [['message' => str_repeat('a', 5001)], 'message'],
        ];
    }

    /**
     * @param  array<string, string>  $overrides
     */
    #[DataProvider('invalidInput')]
    public function test_invalid_message_is_rejected(array $overrides, string $field): void
    {
        $this->from(route('contact'))->post(route('contact.store'), $this->input($overrides))
            ->assertRedirect(route('contact'))
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_bots_that_fill_the_hidden_field_are_thanked_but_nothing_is_kept(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post(route('contact.store'), $this->input(['website' => 'http://spam.example']))
            ->assertRedirect(route('contact'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('contact_messages', 0);
        $this->assertSame(0, $admin->notifications()->count());
    }

    public function test_sending_is_limited_to_five_messages_an_hour(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('contact.store'), $this->input());
        }

        $this->post(route('contact.store'), $this->input())->assertTooManyRequests();
        $this->assertDatabaseCount('contact_messages', 5);
    }

    public function test_admin_inbox_lists_filters_opens_and_deletes_messages(): void
    {
        $admin = User::factory()->admin()->create();
        $unread = ContactMessage::factory()->create(['name' => 'Unread Person', 'subject' => ContactSubject::Complaint, 'message' => 'The search is slow.']);
        ContactMessage::factory()->read()->create(['name' => 'Read Person', 'subject' => ContactSubject::Marketing]);

        $this->actingAs($admin)->get(route('admin.messages.index'))
            ->assertOk()
            ->assertSee('Unread Person')
            ->assertSee('Read Person')
            ->assertSee('1 unread.');
        $this->actingAs($admin)->get(route('admin.messages.index', ['status' => 'unread']))->assertSee('Unread Person')->assertDontSee('Read Person');
        $this->actingAs($admin)->get(route('admin.messages.index', ['subject' => 'marketing']))->assertSee('Read Person')->assertDontSee('Unread Person');
        $this->actingAs($admin)->get(route('admin.messages.index', ['search' => 'slow']))->assertSee('Unread Person')->assertDontSee('Read Person');

        $this->actingAs($admin)->get(route('admin.messages.show', $unread))
            ->assertOk()
            ->assertSee('The search is slow.')
            ->assertSee('mailto:'.$unread->email.'?subject=', false);
        $this->assertNotNull($unread->fresh()->read_at);

        $this->actingAs($admin)->post(route('admin.messages.unread', $unread))->assertRedirect(route('admin.messages.index'));
        $this->assertNull($unread->fresh()->read_at);

        $this->actingAs($admin)->delete(route('admin.messages.destroy', $unread))->assertRedirect(route('admin.messages.index'));
        $this->assertModelMissing($unread);
    }

    public function test_members_cannot_read_the_inbox(): void
    {
        $message = ContactMessage::factory()->create();
        $member = User::factory()->create();

        $this->actingAs($member)->get(route('admin.messages.index'))->assertForbidden();
        $this->actingAs($member)->get(route('admin.messages.show', $message))->assertForbidden();
        $this->actingAs($member)->delete(route('admin.messages.destroy', $message))->assertForbidden();
        $this->assertModelExists($message);
    }

    public function test_privacy_and_terms_pages_and_footer_links(): void
    {
        $this->get(route('privacy'))->assertOk()->assertSee('Privacy policy')->assertSee('Cookies and third parties')->assertSee(config('muallam.contact_email'));
        $this->get(route('terms'))->assertOk()->assertSee('Terms &amp; conditions', false)->assertSee('Content rules');

        $this->get(route('home'))
            ->assertSee(route('contact'))
            ->assertSee(route('privacy'))
            ->assertSee(route('terms'));
        $this->get(route('register'))->assertSee('By creating an account you agree to our');
    }

    public function test_new_pages_are_in_the_sitemap(): void
    {
        $this->get(route('sitemap'))
            ->assertOk()
            ->assertSee('<loc>'.route('contact').'</loc>', false)
            ->assertSee('<loc>'.route('privacy').'</loc>', false)
            ->assertSee('<loc>'.route('terms').'</loc>', false);
    }
}
