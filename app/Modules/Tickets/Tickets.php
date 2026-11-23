<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tickets;

use Pterodactyl\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tickets\Notifications\TicketNotification;

/**
 * Fachlogik der Tickets: eroeffnen, antworten, Status wechseln. Wer etwas darf, entscheiden die
 * Aufrufer (Client-API: nur eigene Tickets; Admin-Bereich: Root-Admins) - diese Klasse haelt die
 * Regeln fuer Status, Zaehler und Benachrichtigungen an einer Stelle.
 */
class Tickets
{
    public const TICKETS = 'techtyl_tickets_tickets';
    public const MESSAGES = 'techtyl_tickets_messages';
    public const CATEGORIES = 'techtyl_tickets_categories';
    public const ATTACHMENTS = 'techtyl_tickets_attachments';

    public const OPEN = 'open';
    public const ANSWERED = 'answered';
    public const WAITING = 'waiting';
    public const CLOSED = 'closed';

    /** Status => Bezeichnung aus Sicht des Nutzers. */
    public const STATUSES = [
        self::OPEN => 'Offen',
        self::ANSWERED => 'Antwort vom Team',
        self::WAITING => 'Wartet auf dich',
        self::CLOSED => 'Geschlossen',
    ];

    /** Status => Bezeichnung aus Sicht des Teams. */
    public const TEAM_STATUSES = [
        self::OPEN => 'Offen, Team am Zug',
        self::ANSWERED => 'Beantwortet',
        self::WAITING => 'Wartet auf Nutzer',
        self::CLOSED => 'Geschlossen',
    ];

    public const SUBJECT_MIN = 3;
    public const SUBJECT_MAX = 120;
    public const BODY_MAX = 5000;
    public const MAX_NOTIFIED_ADMINS = 20;

    /** Zeilenenden vereinheitlichen, Steuerzeichen (ausser Zeilenumbruch und Tabulator) entfernen. */
    public static function cleanBody(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = (string) preg_replace('/[^\P{Cc}\n\t]+/u', '', $text);
        $text = (string) preg_replace('/\n{4,}/', "\n\n\n", $text);

        return trim($text);
    }

    public static function cleanSubject(string $text): string
    {
        $text = (string) preg_replace('/[\p{Cc}\p{Zl}\p{Zp}]+/u', ' ', $text);

        return trim((string) preg_replace('/\s{2,}/u', ' ', $text));
    }

    public static function openCount(int $userId): int
    {
        return DB::table(self::TICKETS)->where('user_id', $userId)->where('status', '!=', self::CLOSED)->count();
    }

    /**
     * @param array{subject: string, category_id: int, server_id: int|null, body: string} $data
     * @param array<int, array{file: string, width: int, height: int, size: int}> $attachments
     *
     * @throws ValidationException wenn die Grenze offener Tickets erreicht ist
     */
    public static function open(User $user, array $data, array $attachments): int
    {
        $maxOpen = TicketSettings::get()['max_open'];

        $id = DB::transaction(function () use ($user, $data, $attachments, $maxOpen) {
            // Sperrt die offenen Tickets des Nutzers, damit gleichzeitige Anfragen die Grenze nicht umgehen.
            $open = DB::table(self::TICKETS)->where('user_id', $user->id)->where('status', '!=', self::CLOSED)
                ->lockForUpdate()->pluck('id')->count();
            if ($open >= $maxOpen) {
                throw ValidationException::withMessages(['subject' => [
                    'Du hast bereits ' . $open . ' offene Tickets (höchstens ' . $maxOpen . '). Schließe ein erledigtes Ticket oder antworte in einem bestehenden.',
                ]]);
            }

            $now = now();
            $ticketId = (int) DB::table(self::TICKETS)->insertGetId([
                'user_id' => $user->id,
                'category_id' => $data['category_id'],
                'server_id' => $data['server_id'],
                'subject' => $data['subject'],
                'status' => self::OPEN,
                'user_unread' => 0,
                'team_unread' => 1,
                'last_message_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            self::addMessage($ticketId, $user->id, false, 'message', $data['body'], $attachments);

            return $ticketId;
        });

        self::notifyTeam($id, TicketNotification::NEW_TICKET);

        return $id;
    }

    /**
     * Antwort des Nutzers; das Ticket liegt danach wieder beim Team.
     *
     * @param array<int, array{file: string, width: int, height: int, size: int}> $attachments
     */
    public static function userReply(User $user, int $ticketId, string $body, array $attachments): int
    {
        [$messageId, $wasOpen] = DB::transaction(function () use ($user, $ticketId, $body, $attachments) {
            $ticket = DB::table(self::TICKETS)->where('id', $ticketId)->where('user_id', $user->id)->lockForUpdate()->first();
            if ($ticket === null) {
                throw new NotFoundHttpException('Das Ticket wurde nicht gefunden.');
            }
            if ($ticket->status === self::CLOSED) {
                throw ValidationException::withMessages(['body' => ['Dieses Ticket ist geschlossen. Öffne es wieder, um zu antworten.']]);
            }

            $messageId = self::addMessage($ticketId, $user->id, false, 'message', $body, $attachments);
            DB::table(self::TICKETS)->where('id', $ticketId)->update([
                'status' => self::OPEN,
                'team_unread' => (int) $ticket->team_unread + 1,
                'user_unread' => 0,
                'awaiting_user_since' => null,
                'last_message_at' => now(),
                'updated_at' => now(),
            ]);

            return [$messageId, $ticket->status === self::OPEN];
        });

        // Nur melden, wenn das Ticket neu beim Team liegt - nicht bei jeder weiteren Nachricht.
        if (!$wasOpen) {
            self::notifyTeam($ticketId, TicketNotification::USER_REPLIED);
        }

        return $messageId;
    }

    /** Antwort des Teams; $then ist der Status danach (answered, waiting oder closed). */
    public static function teamReply(User $admin, int $ticketId, string $body, string $then): int
    {
        if (!in_array($then, [self::ANSWERED, self::WAITING, self::CLOSED], true)) {
            $then = self::ANSWERED;
        }

        [$messageId, $hadUnread] = DB::transaction(function () use ($admin, $ticketId, $body, $then) {
            $ticket = DB::table(self::TICKETS)->where('id', $ticketId)->lockForUpdate()->first();
            if ($ticket === null) {
                throw new NotFoundHttpException('Das Ticket wurde nicht gefunden.');
            }

            $now = now();
            $messageId = self::addMessage($ticketId, $admin->id, true, 'message', $body, []);
            $unread = (int) $ticket->user_unread + 1;
            if ($then === self::CLOSED) {
                self::addMessage($ticketId, $admin->id, true, 'closed', null, []);
            }
            DB::table(self::TICKETS)->where('id', $ticketId)->update([
                'status' => $then,
                'user_unread' => $unread,
                'team_unread' => 0,
                'awaiting_user_since' => $then === self::CLOSED ? null : $now,
                'closed_at' => $then === self::CLOSED ? $now : null,
                'closed_by' => $then === self::CLOSED ? 'team' : null,
                'last_message_at' => $now,
                'updated_at' => $now,
            ]);

            return [$messageId, (int) $ticket->user_unread > 0];
        });

        // Eine Mail je "Stapel" ungelesener Antworten, nicht eine je Nachricht.
        if (!$hadUnread) {
            self::notifyUser($ticketId, TicketNotification::TEAM_REPLIED);
        }

        return $messageId;
    }

    /**
     * Setzt den Status und haelt den Wechsel als Ereignis im Verlauf fest.
     *
     * @param string $by "user", "team" oder "auto"
     * @param int|null $ownerId bei Aufrufen aus der Client-API: nur Tickets dieses Nutzers
     */
    public static function setStatus(int $ticketId, string $status, string $by, ?int $actorId, ?int $ownerId = null): void
    {
        if (!isset(self::STATUSES[$status])) {
            throw ValidationException::withMessages(['status' => ['Unbekannter Status.']]);
        }

        DB::transaction(function () use ($ticketId, $status, $by, $actorId, $ownerId) {
            $ticket = DB::table(self::TICKETS)->where('id', $ticketId)
                ->when($ownerId !== null, fn ($query) => $query->where('user_id', $ownerId))
                ->lockForUpdate()->first();
            if ($ticket === null) {
                throw new NotFoundHttpException('Das Ticket wurde nicht gefunden.');
            }
            if ($ticket->status === $status) {
                return;
            }

            if ($by === 'user' && $status !== self::CLOSED) {
                // Wieder oeffnen zaehlt gegen die Grenze offener Tickets.
                $maxOpen = TicketSettings::get()['max_open'];
                $open = DB::table(self::TICKETS)->where('user_id', $ticket->user_id)->where('status', '!=', self::CLOSED)
                    ->lockForUpdate()->pluck('id')->count();
                if ($open >= $maxOpen) {
                    throw ValidationException::withMessages(['status' => [
                        'Du hast bereits ' . $open . ' offene Tickets (höchstens ' . $maxOpen . '). Schließe zuerst ein anderes.',
                    ]]);
                }
            }

            $now = now();
            $type = match (true) {
                $status === self::CLOSED && $by === 'auto' => 'autoclosed',
                $status === self::CLOSED => 'closed',
                $ticket->status === self::CLOSED => 'reopened',
                default => 'status',
            };
            self::addMessage($ticketId, $actorId, $by === 'team', $type, $type === 'status' ? $status : null, []);

            $values = [
                'status' => $status,
                'closed_at' => $status === self::CLOSED ? $now : null,
                'closed_by' => $status === self::CLOSED ? $by : null,
                'awaiting_user_since' => in_array($status, [self::ANSWERED, self::WAITING], true) ? $now : null,
                'updated_at' => $now,
            ];
            if ($by === 'user') {
                // Der Nutzer hat etwas getan, das das Team sehen soll (ausser beim Schliessen).
                $values['team_unread'] = $status === self::CLOSED ? 0 : (int) $ticket->team_unread + 1;
            } elseif ($by === 'team') {
                $values['team_unread'] = 0;
            }
            DB::table(self::TICKETS)->where('id', $ticketId)->update($values);
        });
    }

    public static function markReadByUser(int $ticketId, int $userId): void
    {
        DB::table(self::TICKETS)->where('id', $ticketId)->where('user_id', $userId)->where('user_unread', '>', 0)
            ->update(['user_unread' => 0]);
    }

    public static function markReadByTeam(int $ticketId): void
    {
        DB::table(self::TICKETS)->where('id', $ticketId)->where('team_unread', '>', 0)->update(['team_unread' => 0]);
    }

    /**
     * Nachrichten und Ereignisse eines Tickets samt Anhaengen, aelteste zuerst.
     *
     * @return array<int, object> Zeilen mit zusaetzlichem Feld "attachments" (Liste von Zeilen)
     */
    public static function thread(int $ticketId): array
    {
        $messages = DB::table(self::MESSAGES . ' as m')
            ->leftJoin('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.ticket_id', $ticketId)
            ->orderBy('m.id')
            ->get(['m.id', 'm.user_id', 'm.is_team', 'm.type', 'm.body', 'm.created_at', 'u.name_first', 'u.username']);
        $attachments = DB::table(self::ATTACHMENTS)->where('ticket_id', $ticketId)->orderBy('id')
            ->get(['id', 'message_id', 'width', 'height', 'size'])->groupBy('message_id');

        return $messages->map(function ($message) use ($attachments) {
            $message->attachments = ($attachments[$message->id] ?? collect())->all();

            return $message;
        })->all();
    }

    /**
     * @param array<int, array{file: string, width: int, height: int, size: int}> $attachments
     */
    private static function addMessage(int $ticketId, ?int $userId, bool $isTeam, string $type, ?string $body, array $attachments): int
    {
        $now = now();
        $messageId = (int) DB::table(self::MESSAGES)->insertGetId([
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'is_team' => $isTeam,
            'type' => $type,
            'body' => $body,
            'created_at' => $now,
        ]);
        foreach ($attachments as $attachment) {
            DB::table(self::ATTACHMENTS)->insert([
                'ticket_id' => $ticketId,
                'message_id' => $messageId,
                'file' => $attachment['file'],
                'width' => $attachment['width'],
                'height' => $attachment['height'],
                'size' => $attachment['size'],
                'created_at' => $now,
            ]);
        }

        return $messageId;
    }

    /** Benachrichtigt den Besitzer des Tickets; ein Fehler beim Einreihen darf die Aktion nie scheitern lassen. */
    public static function notifyUser(int $ticketId, string $kind): void
    {
        if (!TicketSettings::get()['notify']) {
            return;
        }
        try {
            $userId = DB::table(self::TICKETS)->where('id', $ticketId)->value('user_id');
            $user = $userId !== null ? User::query()->find($userId) : null;
            if ($user !== null) {
                $user->notify(new TicketNotification($ticketId, $kind));
            }
        } catch (\Throwable $e) {
            Log::warning('techtyl tickets: Benachrichtigung konnte nicht eingereiht werden', ['ticket' => $ticketId, 'error' => $e->getMessage()]);
        }
    }

    public static function notifyTeam(int $ticketId, string $kind): void
    {
        if (!TicketSettings::get()['notify']) {
            return;
        }
        try {
            $ownerId = DB::table(self::TICKETS)->where('id', $ticketId)->value('user_id');
            $admins = User::query()->where('root_admin', true)->where('id', '!=', (int) $ownerId)
                ->orderBy('id')->limit(self::MAX_NOTIFIED_ADMINS)->get();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new TicketNotification($ticketId, $kind));
            }
        } catch (\Throwable $e) {
            Log::warning('techtyl tickets: Benachrichtigung konnte nicht eingereiht werden', ['ticket' => $ticketId, 'error' => $e->getMessage()]);
        }
    }
}
