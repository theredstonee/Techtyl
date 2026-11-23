<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tickets;

use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Settings;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\ImageProcessor;

/**
 * Client-API der Tickets unter /api/client/extensions/techtyl/tickets.
 * Jede Abfrage ist an den angemeldeten Nutzer gebunden: fremde Tickets ergeben 404, auch fuer
 * Root-Admins (das Team arbeitet ausschliesslich im Admin-Bereich).
 */
class TicketController extends Controller
{
    private const PER_PAGE = 50;

    /** Kategorien, eigene Server und Grenzen fuer das Formular. */
    public function meta(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = TicketSettings::get();

        return new JsonResponse([
            'object' => 'techtyl_tickets_meta',
            'attributes' => [
                'categories' => DB::table(Tickets::CATEGORIES)->orderBy('position')->orderBy('id')->get(['id', 'name'])
                    ->map(fn ($row) => ['id' => (int) $row->id, 'name' => $row->name])->all(),
                'servers' => $this->serverQuery($user)->orderBy('name')->limit(500)->get(['uuid', 'uuidShort', 'name'])
                    ->map(fn ($row) => ['uuid' => $row->uuid, 'identifier' => $row->uuidShort, 'name' => $row->name])->all(),
                'statuses' => Tickets::userStatusLabels(),
                'limits' => [
                    'subject_min' => Tickets::SUBJECT_MIN,
                    'subject_max' => Tickets::SUBJECT_MAX,
                    'body_max' => Tickets::BODY_MAX,
                    'max_open' => $settings['max_open'],
                    'open_now' => Tickets::openCount($user->id),
                    'attachments' => $settings['attachments'],
                    'max_files' => Attachments::MAX_PER_MESSAGE,
                    'max_upload_bytes' => Settings::limits()['max_upload_kb'] * 1024,
                    'mime_types' => array_keys(array_filter(ImageProcessor::readable())),
                ],
            ],
        ]);
    }

    /** Eigene Tickets; zuerst die, bei denen der Nutzer am Zug ist, dann nach letzter Aktivitaet. */
    public function index(Request $request): JsonResponse
    {
        $input = $request->validate([
            'status' => ['sometimes', 'nullable', 'string', 'in:' . implode(',', array_keys(Tickets::STATUSES))],
            'q' => ['sometimes', 'nullable', 'string', 'max:' . Tickets::SUBJECT_MAX],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ], [
            'status.string' => I18n::t('tickets.v_status_invalid'),
            'status.in' => I18n::t('tickets.v_status_invalid'),
            'q.string' => I18n::t('tickets.v_search_invalid'),
            'q.max' => I18n::t('tickets.v_search_long', ['max' => Tickets::SUBJECT_MAX]),
            'page.integer' => I18n::t('tickets.v_page'),
            'page.min' => I18n::t('tickets.v_page'),
            'page.max' => I18n::t('tickets.v_page'),
        ], Tickets::attrs());
        $user = $request->user();
        $status = $input['status'] ?? null;
        $search = trim((string) ($input['q'] ?? ''));
        $page = (int) ($input['page'] ?? 1);

        $base = fn () => DB::table(Tickets::TICKETS . ' as t')->where('t.user_id', $user->id)
            ->when($search !== '', fn ($query) => $query->where('t.subject', 'like', '%' . addcslashes($search, '%_\\') . '%'));

        $counts = array_fill_keys(array_keys(Tickets::STATUSES), 0);
        foreach ($base()->groupBy('t.status')->selectRaw('t.status as status, count(*) as total')->get() as $row) {
            if (isset($counts[$row->status])) {
                $counts[$row->status] = (int) $row->total;
            }
        }

        $query = $base()->when($status !== null, fn ($builder) => $builder->where('t.status', $status));
        $total = (clone $query)->count();
        $rows = $this->withRelations($query)
            ->orderByRaw("case when t.status = 'closed' then 2 when t.user_unread > 0 or t.status in ('answered', 'waiting') then 0 else 1 end")
            ->orderByDesc('t.last_message_at')->orderByDesc('t.id')
            ->offset(($page - 1) * self::PER_PAGE)->limit(self::PER_PAGE)
            ->get();

        return new JsonResponse([
            'object' => 'list',
            'data' => $rows->map(fn ($row) => $this->ticketObject($row))->all(),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => self::PER_PAGE,
                'counts' => $counts + ['all' => array_sum($counts)],
                'unread' => (int) DB::table(Tickets::TICKETS)->where('user_id', $user->id)->sum('user_unread'),
                'open_now' => Tickets::openCount($user->id),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $input = $request->validate([
            'subject' => ['required', 'string', 'max:' . (Tickets::SUBJECT_MAX * 2)],
            'category' => ['required', 'integer', 'min:1'],
            'server' => ['sometimes', 'nullable', 'string', 'uuid'],
        ] + $this->messageRules(), $this->fieldTexts() + $this->messageTexts(), Tickets::attrs());

        $subject = Tickets::cleanSubject($input['subject']);
        $length = mb_strlen($subject);
        if ($length < Tickets::SUBJECT_MIN || $length > Tickets::SUBJECT_MAX) {
            throw ValidationException::withMessages(['subject' => [
                I18n::t('tickets.v_subject_length', ['min' => Tickets::SUBJECT_MIN, 'max' => Tickets::SUBJECT_MAX]),
            ]]);
        }
        $body = $this->cleanBody($input['body']);

        if (!DB::table(Tickets::CATEGORIES)->where('id', (int) $input['category'])->exists()) {
            throw ValidationException::withMessages(['category' => [I18n::t('tickets.v_category_gone')]]);
        }
        $serverId = null;
        if (!empty($input['server'])) {
            $serverId = $this->serverQuery($user)->where('uuid', $input['server'])->value('id');
            if ($serverId === null) {
                throw ValidationException::withMessages(['server' => [I18n::t('tickets.v_server_missing')]]);
            }
        }
        // Vor der Bildverarbeitung pruefen, damit ein abgelehntes Ticket keine Rechenzeit kostet.
        if (Tickets::openCount($user->id) >= TicketSettings::get()['max_open']) {
            throw ValidationException::withMessages(['subject' => [
                I18n::t('tickets.v_open_reached', ['max' => TicketSettings::get()['max_open']]),
            ]]);
        }

        $stored = Attachments::store($this->files($request));
        try {
            $id = Tickets::open($user, [
                'subject' => $subject,
                'category_id' => (int) $input['category'],
                'server_id' => $serverId !== null ? (int) $serverId : null,
                'body' => $body,
            ], $stored);
        } catch (\Throwable $e) {
            Attachments::discard($stored);
            throw $e;
        }

        return new JsonResponse($this->detail($user, $id), JsonResponse::HTTP_CREATED);
    }

    /** Ticket mit Verlauf; markiert die Antworten des Teams als gelesen. */
    public function show(Request $request, string $ticket): JsonResponse
    {
        $user = $request->user();
        $id = $this->ownTicketId($user, $ticket);
        Tickets::markReadByUser($id, $user->id);

        return new JsonResponse($this->detail($user, $id));
    }

    public function reply(Request $request, string $ticket): JsonResponse
    {
        $user = $request->user();
        $id = $this->ownTicketId($user, $ticket);
        $input = $request->validate($this->messageRules(), $this->messageTexts(), Tickets::attrs());
        $body = $this->cleanBody($input['body']);

        if (DB::table(Tickets::TICKETS)->where('id', $id)->value('status') === Tickets::CLOSED) {
            throw ValidationException::withMessages(['body' => [I18n::t('tickets.v_closed_reply')]]);
        }

        $stored = Attachments::store($this->files($request));
        try {
            Tickets::userReply($user, $id, $body, $stored);
        } catch (\Throwable $e) {
            Attachments::discard($stored);
            throw $e;
        }

        return new JsonResponse($this->detail($user, $id), JsonResponse::HTTP_CREATED);
    }

    public function close(Request $request, string $ticket): JsonResponse
    {
        $user = $request->user();
        $id = $this->ownTicketId($user, $ticket);
        Tickets::setStatus($id, Tickets::CLOSED, 'user', $user->id, $user->id);

        return new JsonResponse($this->detail($user, $id));
    }

    public function reopen(Request $request, string $ticket): JsonResponse
    {
        $user = $request->user();
        $id = $this->ownTicketId($user, $ticket);
        if (DB::table(Tickets::TICKETS)->where('id', $id)->value('status') === Tickets::CLOSED) {
            Tickets::setStatus($id, Tickets::OPEN, 'user', $user->id, $user->id);
        }

        return new JsonResponse($this->detail($user, $id));
    }

    /**
     * Liefert einen Anhang aus. Erreichbar ueber die Client-API (Sitzung oder API-Schluessel) und
     * ueber die Web-Route (Sitzung, fuer Bild-Elemente im Dashboard und im Admin-Bereich).
     * Zugriff haben nur der Besitzer des Tickets und Root-Admins; alles andere ergibt 404.
     */
    public function attachment(Request $request, string $attachment): BinaryFileResponse
    {
        $user = $request->user();
        $id = ctype_digit($attachment) && strlen($attachment) <= 10 ? (int) $attachment : 0;
        if (!$user instanceof User || $id < 1) {
            throw new NotFoundHttpException();
        }

        $row = DB::table(Tickets::ATTACHMENTS . ' as a')
            ->join(Tickets::TICKETS . ' as t', 't.id', '=', 'a.ticket_id')
            ->where('a.id', $id)
            ->first(['a.file', 't.user_id']);
        if ($row === null || ((int) $row->user_id !== $user->id && !$user->root_admin)) {
            throw new NotFoundHttpException();
        }
        $found = Attachments::locate($row->file);
        if ($found === null) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($found['path'], 200, [
            'Content-Type' => $found['mime'],
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
        // Nur im Browser des Berechtigten zwischenspeichern, nie in geteilten Caches.
        $response->setCache(['private' => true, 'max_age' => 3600]);
        $response->headers->set('Vary', 'Cookie, Authorization');

        return $response;
    }

    /** @return array<string, array<int, string>> */
    private function messageRules(): array
    {
        $rules = [
            // Die Länge wird nach dem Bereinigen noch einmal genau geprueft.
            'body' => ['required', 'string', 'max:' . (Tickets::BODY_MAX * 2)],
            'images' => ['sometimes', 'array', 'max:' . Attachments::MAX_PER_MESSAGE],
        ];
        if (TicketSettings::get()['attachments']) {
            $rules['images.*'] = ['file', 'max:' . Settings::limits()['max_upload_kb'], 'mimetypes:' . implode(',', ImageProcessor::MIME_TYPES)];
        } else {
            $rules['images'] = ['prohibited'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    private function fieldTexts(): array
    {
        return [
            'subject.required' => I18n::t('tickets.v_subject_required'),
            'subject.string' => I18n::t('tickets.v_subject_required'),
            'subject.max' => I18n::t('tickets.v_subject_long', ['max' => Tickets::SUBJECT_MAX]),
            'category.required' => I18n::t('tickets.v_category_choose'),
            'category.integer' => I18n::t('tickets.v_category_choose'),
            'category.min' => I18n::t('tickets.v_category_choose'),
            'server.string' => I18n::t('tickets.v_server_invalid'),
            'server.uuid' => I18n::t('tickets.v_server_invalid'),
        ];
    }

    /** @return array<string, string> */
    private function messageTexts(): array
    {
        return [
            'body.required' => I18n::t('tickets.v_body_required'),
            'body.string' => I18n::t('tickets.v_body_required'),
            'body.max' => I18n::t('tickets.v_body_long', ['max' => Tickets::BODY_MAX]),
            'images.array' => I18n::t('tickets.v_images_invalid'),
            'images.max' => I18n::t('tickets.v_images_max', ['max' => Attachments::MAX_PER_MESSAGE]),
            'images.prohibited' => I18n::t('tickets.v_images_off'),
            'images.*.file' => I18n::t('tickets.v_image_upload'),
            'images.*.uploaded' => I18n::t('tickets.v_image_upload_size'),
            'images.*.max' => I18n::t('tickets.v_image_max', ['max' => Settings::limits()['max_upload_kb']]),
            'images.*.mimetypes' => I18n::t('tickets.v_image_type'),
        ];
    }

    private function cleanBody(string $raw): string
    {
        $body = Tickets::cleanBody($raw);
        if ($body === '') {
            throw ValidationException::withMessages(['body' => [I18n::t('tickets.v_body_required')]]);
        }
        if (mb_strlen($body) > Tickets::BODY_MAX) {
            throw ValidationException::withMessages(['body' => [I18n::t('tickets.v_body_long', ['max' => Tickets::BODY_MAX])]]);
        }

        return $body;
    }

    /** @return array<int, UploadedFile> */
    private function files(Request $request): array
    {
        if (!TicketSettings::get()['attachments']) {
            return [];
        }
        $files = $request->file('images', []);

        return array_values(array_filter(is_array($files) ? $files : [], fn ($file) => $file instanceof UploadedFile && $file->isValid()));
    }

    private function ownTicketId(User $user, string $ticket): int
    {
        $id = ctype_digit($ticket) && strlen($ticket) <= 10 ? (int) $ticket : 0;
        if ($id < 1 || !DB::table(Tickets::TICKETS)->where('id', $id)->where('user_id', $user->id)->exists()) {
            throw new NotFoundHttpException(I18n::t('tickets.v_ticket_missing'));
        }

        return $id;
    }

    /** Server, die der Nutzer als Besitzer oder Subuser sieht. */
    private function serverQuery(User $user)
    {
        return DB::table('servers')->where(fn ($query) => $query
            ->where('owner_id', $user->id)
            ->orWhereIn('id', fn ($sub) => $sub->select('server_id')->from('subusers')->where('user_id', $user->id)));
    }

    private function withRelations($query)
    {
        return $query
            ->leftJoin(Tickets::CATEGORIES . ' as c', 'c.id', '=', 't.category_id')
            ->leftJoin('servers as s', 's.id', '=', 't.server_id')
            ->select([
                't.id', 't.subject', 't.status', 't.user_unread', 't.last_message_at', 't.created_at', 't.closed_at', 't.closed_by',
                'c.id as category_id', 'c.name as category_name',
                's.uuid as server_uuid', 's.uuidShort as server_identifier', 's.name as server_name',
            ]);
    }

    private function ticketObject(object $row): array
    {
        return [
            'object' => 'techtyl_ticket',
            'attributes' => [
                'id' => (int) $row->id,
                'subject' => $row->subject,
                'status' => $row->status,
                'category' => $row->category_id !== null ? ['id' => (int) $row->category_id, 'name' => $row->category_name] : null,
                'server' => $row->server_uuid !== null
                    ? ['uuid' => $row->server_uuid, 'identifier' => $row->server_identifier, 'name' => $row->server_name]
                    : null,
                'unread' => (int) $row->user_unread,
                'created_at' => $this->iso($row->created_at),
                'updated_at' => $this->iso($row->last_message_at ?? $row->created_at),
                'closed_at' => $this->iso($row->closed_at),
                'closed_by' => $row->closed_by,
            ],
        ];
    }

    private function detail(User $user, int $id): array
    {
        $row = $this->withRelations(DB::table(Tickets::TICKETS . ' as t')->where('t.id', $id)->where('t.user_id', $user->id))->first();
        if ($row === null) {
            throw new NotFoundHttpException(I18n::t('tickets.v_ticket_missing'));
        }
        $ticket = $this->ticketObject($row);

        $ticket['attributes']['messages'] = array_map(function ($message) {
            $team = (bool) $message->is_team;

            return [
                'id' => (int) $message->id,
                'type' => $message->type,
                'author' => $message->user_id === null ? 'system' : ($team ? 'team' : 'you'),
                // Vom Team wird nur der Vorname gezeigt, nie Benutzername oder E-Mail.
                'author_name' => $team ? trim((string) $message->name_first) : '',
                'body' => (string) $message->body,
                'created_at' => $this->iso($message->created_at),
                'attachments' => array_map(fn ($attachment) => [
                    'id' => (int) $attachment->id,
                    'url' => '/extensions/{identifier}/tickets/attachments/' . (int) $attachment->id,
                    'width' => (int) $attachment->width,
                    'height' => (int) $attachment->height,
                ], $message->attachments),
            ];
        }, Tickets::thread($id));

        return $ticket;
    }

    private function iso(mixed $value): ?string
    {
        return $value === null ? null : \Carbon\CarbonImmutable::parse($value)->toIso8601String();
    }
}
