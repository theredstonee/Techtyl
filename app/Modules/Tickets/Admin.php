<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tickets;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;

/**
 * Admin-Reiter "Tickets": alle Tickets, Detailansicht (?tab=tickets&ticket=<id>), Kategorien und
 * Einstellungen. Der Admin-Controller laesst nur Root-Admins hierher.
 */
class Admin
{
    private const PER_PAGE = 25;
    private const MAX_CATEGORIES = 30;
    private const CATEGORY_NAME = ['required', 'string', 'min:2', 'max:60', 'regex:/^[^\p{Cc}\p{Cf}<>]+$/u'];

    /** Filter keys. "active" means every ticket that is not closed. Labels come from translations. */
    private const FILTERS = ['active' => 'active', 'all' => 'all'] + Tickets::TEAM_STATUSES;

    public static function data(): array
    {
        if (!Schema::hasTable(Tickets::TICKETS)) {
            return ['ticketsReady' => false];
        }

        $request = request();
        $status = (string) $request->query('status', 'active');
        if (!is_string($request->query('status', 'active')) || !isset(self::FILTERS[$status])) {
            $status = 'active';
        }
        $category = self::positiveInt($request->query('category'));
        $page = max(1, self::positiveInt($request->query('page')));

        $categories = DB::table(Tickets::CATEGORIES . ' as c')
            ->leftJoin(Tickets::TICKETS . ' as t', 't.category_id', '=', 'c.id')
            ->groupBy('c.id', 'c.name', 'c.position')
            ->orderBy('c.position')->orderBy('c.id')
            ->selectRaw('c.id, c.name, c.position, count(t.id) as tickets')
            ->get();

        $query = DB::table(Tickets::TICKETS . ' as t')
            ->when($status === 'active', fn ($builder) => $builder->where('t.status', '!=', Tickets::CLOSED))
            ->when(isset(Tickets::STATUSES[$status]), fn ($builder) => $builder->where('t.status', $status))
            ->when($category > 0, fn ($builder) => $builder->where('t.category_id', $category));
        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);

        $list = $query
            ->join('users as u', 'u.id', '=', 't.user_id')
            ->leftJoin(Tickets::CATEGORIES . ' as c', 'c.id', '=', 't.category_id')
            ->leftJoin('servers as s', 's.id', '=', 't.server_id')
            ->orderByRaw("case when t.status = 'open' then 0 when t.status = 'closed' then 2 else 1 end")
            ->orderByDesc('t.last_message_at')->orderByDesc('t.id')
            ->offset(($page - 1) * self::PER_PAGE)->limit(self::PER_PAGE)
            ->get([
                't.id', 't.subject', 't.status', 't.team_unread', 't.last_message_at', 't.created_at',
                'u.username', 'c.name as category', 's.name as server',
            ]);
        foreach ($list as $row) {
            $row->at = self::time($row->last_message_at ?? $row->created_at);
        }

        $counts = array_fill_keys(array_keys(Tickets::STATUSES), 0);
        foreach (DB::table(Tickets::TICKETS)->groupBy('status')->selectRaw('status, count(*) as total')->get() as $row) {
            if (isset($counts[$row->status])) {
                $counts[$row->status] = (int) $row->total;
            }
        }

        $filterQuery = array_filter(['status' => $status !== 'active' ? $status : null, 'category' => $category > 0 ? $category : null]);

        return [
            'ticketsReady' => true,
            'ticketSettings' => TicketSettings::get(),
            'ticketSettingLimits' => ['days' => TicketSettings::MAX_AUTOCLOSE_DAYS, 'open' => TicketSettings::MAX_OPEN_LIMIT],
            'ticketCategories' => $categories,
            'ticketFilters' => self::filterLabels(),
            'ticketStatuses' => Tickets::teamStatusLabels(),
            'ticketStatus' => $status,
            'ticketCategory' => $category,
            'ticketList' => $list,
            'ticketTotal' => $total,
            'ticketCounts' => $counts,
            'ticketPage' => $page,
            'ticketPages' => $pages,
            // Fuer Links, die die Filter behalten ("&status=...&category=...").
            'ticketFilterQuery' => $filterQuery === [] ? '' : '&' . http_build_query($filterQuery),
            'ticketDetail' => self::detail(self::positiveInt($request->query('ticket'))),
            'ticketDetailRequested' => $request->query('ticket') !== null,
        ];
    }

    public static function handle(Request $request, string $action): string
    {
        return match ($action) {
            'reply' => self::reply($request),
            'status' => self::status($request),
            'settings' => self::settings($request),
            'category-add' => self::categoryAdd($request),
            'category-rename' => self::categoryRename($request),
            'category-delete' => self::categoryDelete($request),
            'category-move' => self::categoryMove($request),
            default => throw new NotFoundHttpException(),
        };
    }

    private static function reply(Request $request): string
    {
        $input = $request->validate([
            'ticket' => ['required', 'integer', 'min:1'],
            'body' => ['required', 'string', 'max:' . (Tickets::BODY_MAX * 2)],
            'then' => ['required', 'string', Rule::in([Tickets::ANSWERED, Tickets::WAITING, Tickets::CLOSED])],
        ], [
            'ticket.required' => I18n::t('tickets.v_ticket_invalid'),
            'ticket.integer' => I18n::t('tickets.v_ticket_invalid'),
            'ticket.min' => I18n::t('tickets.v_ticket_invalid'),
            'body.required' => I18n::t('tickets.v_reply_required'),
            'body.string' => I18n::t('tickets.v_reply_required'),
            'body.max' => I18n::t('tickets.v_reply_long', ['max' => Tickets::BODY_MAX]),
            'then.required' => I18n::t('tickets.v_then'),
            'then.string' => I18n::t('tickets.v_then'),
            'then.in' => I18n::t('tickets.v_then'),
        ], Tickets::attrs());
        $body = Tickets::cleanBody($input['body']);
        if ($body === '' || mb_strlen($body) > Tickets::BODY_MAX) {
            throw ValidationException::withMessages(['body' => [I18n::t('tickets.v_reply_length', ['max' => Tickets::BODY_MAX])]]);
        }

        Tickets::teamReply($request->user(), (int) $input['ticket'], $body, $input['then']);

        self::backToTicket((int) $input['ticket'], I18n::t($input['then'] === Tickets::CLOSED ? 'tickets.saved_reply_closed' : 'tickets.saved_reply'));
    }

    private static function status(Request $request): string
    {
        $input = $request->validate([
            'ticket' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'string', Rule::in(array_keys(Tickets::STATUSES))],
        ], [
            'ticket.required' => I18n::t('tickets.v_ticket_invalid'),
            'ticket.integer' => I18n::t('tickets.v_ticket_invalid'),
            'ticket.min' => I18n::t('tickets.v_ticket_invalid'),
            'status.required' => I18n::t('tickets.v_status_required'),
            'status.string' => I18n::t('tickets.v_status_invalid'),
            'status.in' => I18n::t('tickets.v_status_invalid'),
        ], Tickets::attrs());
        Tickets::setStatus((int) $input['ticket'], $input['status'], 'team', $request->user()->id);

        self::backToTicket((int) $input['ticket'], I18n::t('tickets.saved_status', [
            'status' => Tickets::teamStatusLabels()[$input['status']],
        ]));
    }

    private static function settings(Request $request): string
    {
        TicketSettings::save($request->validate([
            'notify' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'boolean'],
            'autoclose_days' => ['required', 'integer', 'min:0', 'max:' . TicketSettings::MAX_AUTOCLOSE_DAYS],
            'max_open' => ['required', 'integer', 'min:1', 'max:' . TicketSettings::MAX_OPEN_LIMIT],
        ], [
            'notify.boolean' => I18n::t('tickets.v_bool'),
            'attachments.boolean' => I18n::t('tickets.v_bool'),
            'autoclose_days.required' => I18n::t('tickets.v_days_required'),
            'autoclose_days.integer' => I18n::t('tickets.v_days', ['max' => TicketSettings::MAX_AUTOCLOSE_DAYS]),
            'autoclose_days.min' => I18n::t('tickets.v_days', ['max' => TicketSettings::MAX_AUTOCLOSE_DAYS]),
            'autoclose_days.max' => I18n::t('tickets.v_days', ['max' => TicketSettings::MAX_AUTOCLOSE_DAYS]),
            'max_open.required' => I18n::t('tickets.v_max_open_required'),
            'max_open.integer' => I18n::t('tickets.v_max_open', ['max' => TicketSettings::MAX_OPEN_LIMIT]),
            'max_open.min' => I18n::t('tickets.v_max_open', ['max' => TicketSettings::MAX_OPEN_LIMIT]),
            'max_open.max' => I18n::t('tickets.v_max_open', ['max' => TicketSettings::MAX_OPEN_LIMIT]),
        ], Tickets::attrs()));

        return I18n::t('tickets.saved_settings');
    }

    private static function categoryAdd(Request $request): string
    {
        $name = self::categoryName($request, null);
        if (DB::table(Tickets::CATEGORIES)->count() >= self::MAX_CATEGORIES) {
            throw ValidationException::withMessages(['name' => [I18n::t('tickets.v_category_limit', ['max' => self::MAX_CATEGORIES])]]);
        }
        DB::table(Tickets::CATEGORIES)->insert([
            'name' => $name,
            'position' => ((int) DB::table(Tickets::CATEGORIES)->max('position')) + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return I18n::t('tickets.saved_category_added');
    }

    private static function categoryRename(Request $request): string
    {
        $id = self::categoryId($request);
        DB::table(Tickets::CATEGORIES)->where('id', $id)->update(['name' => self::categoryName($request, $id), 'updated_at' => now()]);

        return I18n::t('tickets.saved_category_renamed');
    }

    private static function categoryDelete(Request $request): string
    {
        $id = self::categoryId($request);
        DB::transaction(function () use ($id) {
            if (DB::table(Tickets::TICKETS)->where('category_id', $id)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['category' => [I18n::t('tickets.v_category_used')]]);
            }
            DB::table(Tickets::CATEGORIES)->where('id', $id)->delete();
        });

        return I18n::t('tickets.saved_category_deleted');
    }

    private static function categoryMove(Request $request): string
    {
        $id = self::categoryId($request);
        $direction = $request->validate(['direction' => ['required', 'string', 'in:up,down']], [
            'direction.required' => I18n::t('tickets.v_direction'),
            'direction.string' => I18n::t('tickets.v_direction'),
            'direction.in' => I18n::t('tickets.v_direction'),
        ], Tickets::attrs())['direction'];

        DB::transaction(function () use ($id, $direction) {
            $ids = DB::table(Tickets::CATEGORIES)->orderBy('position')->orderBy('id')->lockForUpdate()->pluck('id')
                ->map(fn ($value) => (int) $value)->all();
            $index = array_search($id, $ids, true);
            $swap = $direction === 'up' ? $index - 1 : $index + 1;
            if ($index !== false && isset($ids[$swap])) {
                [$ids[$index], $ids[$swap]] = [$ids[$swap], $ids[$index]];
            }
            foreach ($ids as $position => $categoryId) {
                DB::table(Tickets::CATEGORIES)->where('id', $categoryId)->update(['position' => $position]);
            }
        });

        return I18n::t('tickets.saved_order');
    }

    private static function categoryId(Request $request): int
    {
        $id = (int) $request->validate(['category' => ['required', 'integer', 'min:1']], [
            'category.required' => I18n::t('tickets.v_category_invalid'),
            'category.integer' => I18n::t('tickets.v_category_invalid'),
            'category.min' => I18n::t('tickets.v_category_invalid'),
        ], Tickets::attrs())['category'];
        if (!DB::table(Tickets::CATEGORIES)->where('id', $id)->exists()) {
            throw ValidationException::withMessages(['category' => [I18n::t('tickets.v_category_missing')]]);
        }

        return $id;
    }

    private static function categoryName(Request $request, ?int $exceptId): string
    {
        $name = trim($request->validate(['name' => self::CATEGORY_NAME], [
            'name.required' => I18n::t('tickets.v_name_required'),
            'name.string' => I18n::t('tickets.v_name_required'),
            'name.min' => I18n::t('tickets.v_name_short'),
            'name.max' => I18n::t('tickets.v_name_long', ['max' => 60]),
            'name.regex' => I18n::t('tickets.v_name_chars'),
        ], Tickets::attrs())['name']);
        $taken = DB::table(Tickets::CATEGORIES)->where('name', $name)
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))->exists();
        if (mb_strlen($name) < 2 || $taken) {
            throw ValidationException::withMessages(['name' => [I18n::t($taken ? 'tickets.v_name_taken' : 'tickets.v_name_short')]]);
        }

        return $name;
    }

    /** Zurueck in die Detailansicht statt in die Liste (der Admin-Controller kennt nur den Reiter). */
    private static function backToTicket(int $ticketId, string $message): never
    {
        \Prologue\Alerts\Facades\Alert::success(e($message))->flash();

        throw new HttpResponseException(
            redirect('/admin/extensions/{identifier}?tab=tickets&ticket=' . $ticketId)
        );
    }

    /** @return array<string, mixed>|null */
    private static function detail(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $ticket = DB::table(Tickets::TICKETS . ' as t')
            ->join('users as u', 'u.id', '=', 't.user_id')
            ->leftJoin(Tickets::CATEGORIES . ' as c', 'c.id', '=', 't.category_id')
            ->leftJoin('servers as s', 's.id', '=', 't.server_id')
            ->where('t.id', $id)
            ->first([
                't.id', 't.subject', 't.status', 't.created_at', 't.last_message_at', 't.closed_at', 't.closed_by', 't.awaiting_user_since',
                'u.id as user_id', 'u.username', 'u.email',
                'c.name as category', 's.id as server_id', 's.name as server',
            ]);
        if ($ticket === null) {
            return null;
        }
        Tickets::markReadByTeam($id);

        $events = [
            'closed' => I18n::t('tickets.event_closed'),
            'reopened' => I18n::t('tickets.event_reopened'),
            'autoclosed' => I18n::t('tickets.event_autoclosed'),
        ];
        $teamLabels = Tickets::teamStatusLabels();
        $messages = [];
        foreach (Tickets::thread($id) as $message) {
            $author = $message->user_id === null
                ? ($message->type === 'autoclosed' ? I18n::t('tickets.author_system') : I18n::t('tickets.author_deleted'))
                : $message->username . ((bool) $message->is_team ? I18n::t('tickets.author_team_suffix') : '');
            $event = null;
            if ($message->type === 'status') {
                $event = I18n::t('tickets.event_status_set', [
                    'status' => $teamLabels[(string) $message->body] ?? I18n::t('tickets.unknown'),
                ]);
            } elseif ($message->type !== 'message') {
                $event = $events[$message->type] ?? I18n::t('tickets.event_generic');
            }
            $messages[] = [
                'author' => $author,
                'team' => (bool) $message->is_team,
                'event' => $event,
                // Zeilenweise, damit die Ansicht Umbrueche ohne ungefiltertes HTML setzen kann.
                'lines' => $event === null ? explode("\n", (string) $message->body) : [],
                'at' => self::time($message->created_at),
                'attachments' => array_map(fn ($attachment) => [
                    'url' => '/extensions/{identifier}/tickets/attachments/' . (int) $attachment->id,
                    'label' => I18n::t('tickets.attachment_size', [
                        'width' => (int) $attachment->width,
                        'height' => (int) $attachment->height,
                    ]),
                ], $message->attachments),
            ];
        }

        return [
            'ticket' => $ticket,
            'messages' => $messages,
            'created' => self::time($ticket->created_at),
            'updated' => self::time($ticket->last_message_at),
            'closedBy' => [
                'user' => I18n::t('tickets.closed_by_user'),
                'team' => I18n::t('tickets.closed_by_team'),
                'auto' => I18n::t('tickets.closed_by_auto'),
            ][$ticket->closed_by] ?? '',
        ];
    }

    /** @return array<string, string> */
    private static function filterLabels(): array
    {
        return [
            'active' => I18n::t('tickets.filter_active'),
            'all' => I18n::t('tickets.filter_all'),
        ] + Tickets::teamStatusLabels();
    }

    private static function time(mixed $value): string
    {
        return $value === null ? '' : \Carbon\CarbonImmutable::parse($value)->format('d.m.Y H:i');
    }

    private static function positiveInt(mixed $value): int
    {
        return is_string($value) && ctype_digit($value) && strlen($value) <= 9 ? (int) $value : 0;
    }
}
