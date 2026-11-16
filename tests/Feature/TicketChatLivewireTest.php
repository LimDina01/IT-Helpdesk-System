<?php

namespace Tests\Feature;

use App\Livewire\Portal\TicketChat;
use App\Models\Branch;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TicketChatLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected User $staffUser;

    protected User $requesterUser;

    protected Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->staffUser = User::where('email', 'helpdesk@cbvh.com.kh')->firstOrFail();
        $this->requesterUser = User::where('email', 'sokha.chan@cbvh.com.kh')->firstOrFail();
        $branch = Branch::firstOrFail();
        $category = TicketCategory::firstOrFail();

        $this->ticket = Ticket::create([
            'ticket_code' => 'TICK-TEST-100',
            'ticket_type' => 'incident',
            'subject' => 'Printer not responding',
            'description' => 'Printer on 2nd floor is offline.',
            'category_id' => $category->id,
            'branch_id' => $branch->id,
            'requester_id' => $this->requesterUser->id,
            'status' => 'new',
            'priority' => 'medium',
        ]);
    }

    public function test_ticket_chat_renders_successfully(): void
    {
        Livewire::actingAs($this->staffUser)
            ->test(TicketChat::class, ['ticket' => $this->ticket])
            ->assertStatus(200)
            ->assertSee('Ticket Discussion & Notes')
            ->assertSee('Reply to Employee')
            ->assertSee('Internal Note');
    }

    public function test_staff_can_send_public_reply(): void
    {
        Livewire::actingAs($this->staffUser)
            ->test(TicketChat::class, ['ticket' => $this->ticket])
            ->set('messageTab', 'requester_visible')
            ->set('messageBody', 'We are investigating the printer issue now.')
            ->call('sendMessage')
            ->assertDispatched('message-sent')
            ->assertDispatched('scroll-chat-to-bottom')
            ->assertSet('messageBody', '');

        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $this->ticket->id,
            'sender_id' => $this->staffUser->id,
            'visibility' => 'requester_visible',
            'body' => 'We are investigating the printer issue now.',
        ]);
    }

    public function test_staff_can_post_confidential_internal_note(): void
    {
        Livewire::actingAs($this->staffUser)
            ->test(TicketChat::class, ['ticket' => $this->ticket])
            ->set('messageTab', 'internal_staff_only')
            ->set('messageBody', 'Checked toner levels; cyan cartridge is missing.')
            ->call('sendMessage')
            ->assertDispatched('message-sent')
            ->assertSet('toastMessage', 'Internal note added.');

        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $this->ticket->id,
            'sender_id' => $this->staffUser->id,
            'visibility' => 'internal_staff_only',
            'body' => 'Checked toner levels; cyan cartridge is missing.',
        ]);

        $this->assertDatabaseHas('ticket_audit_logs', [
            'ticket_id' => $this->ticket->id,
            'actor_id' => $this->staffUser->id,
            'action' => 'INTERNAL_NOTE_ADDED',
        ]);
    }

    public function test_filter_can_switch_views(): void
    {
        Livewire::actingAs($this->staffUser)
            ->test(TicketChat::class, ['ticket' => $this->ticket])
            ->call('setChatFilter', 'notes')
            ->assertSet('chatFilter', 'notes')
            ->assertDispatched('scroll-chat-to-bottom')
            ->call('setChatFilter', 'conversation')
            ->assertSet('chatFilter', 'conversation')
            ->call('setChatFilter', 'all')
            ->assertSet('chatFilter', 'all');
    }

    public function test_validation_prevents_empty_message_without_files(): void
    {
        Livewire::actingAs($this->staffUser)
            ->test(TicketChat::class, ['ticket' => $this->ticket])
            ->set('messageBody', '')
            ->call('sendMessage')
            ->assertHasErrors(['messageBody']);
    }

    public function test_staff_can_send_message_with_attachment(): void
    {
        Storage::fake('attachments');

        $file = UploadedFile::fake()->create('network_diagnostic.pdf', 250, 'application/pdf');

        Livewire::actingAs($this->staffUser)
            ->test(TicketChat::class, ['ticket' => $this->ticket])
            ->set('messageTab', 'requester_visible')
            ->set('messageBody', 'Please see the diagnostic report.')
            ->set('attachments', [$file])
            ->call('sendMessage')
            ->assertDispatched('message-sent');

        $this->assertDatabaseHas('ticket_attachments', [
            'ticket_id' => $this->ticket->id,
            'original_filename' => 'network_diagnostic.pdf',
        ]);
    }
}
