<?php

namespace App\Livewire\Portal;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketAuditLog;
use App\Models\TicketMessage;
use App\Services\TelegramNotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class TicketChat extends Component
{
    use WithFileUploads;

    public Ticket $ticket;

    public string $messageBody = '';

    public string $messageTab = 'requester_visible'; // 'requester_visible' or 'internal_staff_only'

    public string $chatFilter = 'all'; // 'all', 'conversation', 'notes'

    /** @var array<TemporaryUploadedFile> */
    public array $attachments = [];

    public ?string $toastMessage = null;

    protected array $rules = [
        'messageBody' => 'required_without:attachments|nullable|string|max:5000',
        'messageTab' => 'required|in:requester_visible,internal_staff_only',
        'attachments.*' => 'nullable|file|max:25600|mimes:jpeg,jpg,png,webp,gif,bmp,heic,heif,pdf,doc,docx,xls,xlsx,txt,zip',
    ];

    public function mount(Ticket $ticket): void
    {
        $this->ticket = $ticket;
    }

    public function setChatFilter(string $filter): void
    {
        if (in_array($filter, ['all', 'conversation', 'notes'], true)) {
            $this->chatFilter = $filter;
            $this->dispatch('scroll-chat-to-bottom');
        }
    }

    public function setMessageTab(string $tab): void
    {
        if (in_array($tab, ['requester_visible', 'internal_staff_only'], true)) {
            $this->messageTab = $tab;
        }
    }

    public function removeAttachment(int $index): void
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    public function clearToast(): void
    {
        $this->toastMessage = null;
    }

    public function sendMessage(TelegramNotificationService $telegram): void
    {
        $this->validate();

        $user = Auth::user();
        if (! $user) {
            return;
        }

        $bodyText = trim($this->messageBody);
        $hasFiles = count($this->attachments) > 0;

        if (empty($bodyText) && ! $hasFiles) {
            return;
        }

        DB::transaction(function () use ($user, $bodyText, $hasFiles) {
            $msg = TicketMessage::create([
                'ticket_id' => $this->ticket->id,
                'sender_id' => $user->id,
                'visibility' => $this->messageTab,
                'message_type' => $hasFiles && empty($bodyText) ? 'attachment' : 'text',
                'body' => $bodyText ?: null,
            ]);

            if ($hasFiles) {
                $yearMonth = date('Y/m');
                foreach ($this->attachments as $file) {
                    $storedName = (string) Str::uuid().'.'.$file->getClientOriginalExtension();
                    $storagePath = $file->storeAs("attachments/{$yearMonth}", $storedName, 'attachments');

                    TicketAttachment::create([
                        'ticket_id' => $this->ticket->id,
                        'message_id' => $msg->id,
                        'uploaded_by_id' => $user->id,
                        'original_filename' => $file->getClientOriginalName(),
                        'storage_path' => $storagePath,
                        'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                        'file_size_bytes' => $file->getSize(),
                    ]);
                }
            }

            if ($this->messageTab === 'internal_staff_only') {
                TicketAuditLog::create([
                    'ticket_id' => $this->ticket->id,
                    'actor_id' => $user->id,
                    'action' => 'INTERNAL_NOTE_ADDED',
                    'old_value' => null,
                    'new_value' => null,
                    'metadata' => ['message_id' => $msg->id],
                    'created_at' => now(),
                ]);
            }
        });

        // Trigger Telegram alert to requester if public and requester is NOT active in TMA chat
        $isRequesterActive = Cache::has("ticket_presence_{$this->ticket->id}_{$this->ticket->requester_id}");
        if (! $isRequesterActive && $this->messageTab === 'requester_visible' && $this->ticket->requester?->telegram_id) {
            $alertBody = ! empty($bodyText) ? $bodyText : '📎 Sent an attachment';
            $telegram->sendStaffReplyAlert($this->ticket->requester, $this->ticket, $alertBody, $user->name);
        }

        $this->toastMessage = $this->messageTab === 'internal_staff_only' ? 'Internal note added.' : 'Reply sent to employee.';
        $this->messageBody = '';
        $this->attachments = [];

        if ($this->chatFilter === 'notes' && $this->messageTab === 'requester_visible') {
            $this->chatFilter = 'all';
        }

        $this->dispatch('message-sent');
        $this->dispatch('scroll-chat-to-bottom');
    }

    public function getFileBadgeClass(string $filename, bool $isMe = false): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => 'bg-rose-500 text-white',
            'doc', 'docx' => 'bg-blue-600 text-white',
            'xls', 'xlsx', 'csv' => 'bg-emerald-600 text-white',
            'zip', 'rar', '7z', 'tar', 'gz' => 'bg-amber-600 text-white',
            default => $isMe ? 'bg-blue-800 text-white' : 'bg-slate-700 text-white',
        };
    }

    public function getFileExtension(string $filename): string
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        return $ext ? strtoupper(substr($ext, 0, 4)) : 'FILE';
    }

    public function render(): View
    {
        $query = $this->ticket->messages()
            ->with(['sender.roles', 'attachments'])
            ->orderBy('created_at', 'asc');

        if ($this->chatFilter === 'conversation') {
            $query->where('visibility', '!=', 'system_event');
        } elseif ($this->chatFilter === 'notes') {
            $query->where('visibility', 'internal_staff_only');
        }

        $messages = $query->get();

        $internalNotesCount = $this->ticket->messages()
            ->where('visibility', 'internal_staff_only')
            ->count();

        $isRequesterActive = Cache::has("ticket_presence_{$this->ticket->id}_{$this->ticket->requester_id}");

        $this->dispatch('requester-presence-updated', active: $isRequesterActive);

        return view('livewire.portal.ticket-chat', [
            'messages' => $messages,
            'internalNotesCount' => $internalNotesCount,
            'isRequesterActive' => $isRequesterActive,
        ]);
    }
}
