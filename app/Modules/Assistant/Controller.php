<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant;

use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Pterodactyl\Http\Controllers\Controller as BaseController;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant\Providers\Result;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant\Providers\Factory;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant\Providers\ProviderException;

/**
 * Client-API des KI-Assistenten unter /api/client/extensions/techtyl/assistant.
 * Jede Abfrage ist an den angemeldeten Nutzer gebunden; fremde Gespraeche und Server ergeben 404.
 * Der Assistent hat keine Werkzeuge: Diese Klasse schickt Text an den Anbieter und speichert Text.
 */
class Controller extends BaseController
{
    private const CONVERSATIONS = 'techtyl_assistant_conversations';
    private const MESSAGES = 'techtyl_assistant_messages';

    /** Alles, was die Seite beim Oeffnen braucht. */
    public function state(Request $request): JsonResponse
    {
        $user = $request->user();
        Retention::sometimes();

        return new JsonResponse([
            'object' => 'techtyl_assistant_state',
            'attributes' => [
                'configured' => Config::configured(),
                'provider' => Config::configured() ? Config::label(Config::provider()) : null,
                'model' => Config::configured() ? Config::model(Config::provider()) : null,
                'demo' => Config::provider() === 'demo',
                'is_admin' => (bool) $user->root_admin,
                'admin_url' => $user->root_admin ? '/admin/extensions/{identifier}?tab=assistant' : null,
                'context_enabled' => (bool) Config::all()['context_enabled'],
                'context_lines' => Config::int('context_lines'),
                'limits' => [
                    'daily' => Config::int('daily_limit'),
                    'used_today' => Quota::used($user->id),
                    'per_minute' => Config::int('per_minute'),
                    'max_input_chars' => Config::int('max_input_chars'),
                    'retention_days' => Config::int('retention_days'),
                ],
                'servers' => ServerContext::selectable($user),
                'conversations' => $this->conversationList($user),
            ],
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        return new JsonResponse(['object' => 'list', 'data' => $this->conversationList($request->user())]);
    }

    public function conversation(Request $request, int $conversation): JsonResponse
    {
        $row = $this->ownConversation($request->user(), $conversation);
        $messages = DB::table(self::MESSAGES)->where('conversation_id', $row->id)->orderBy('id')->limit(500)->get();

        return new JsonResponse([
            'object' => 'techtyl_assistant_conversation',
            'attributes' => $this->conversationAttributes($row) + [
                'messages' => $messages->map(fn ($message) => $this->messageAttributes($message))->all(),
            ],
        ]);
    }

    public function deleteConversation(Request $request, int $conversation): JsonResponse
    {
        $row = $this->ownConversation($request->user(), $conversation);
        DB::table(self::CONVERSATIONS)->where('id', $row->id)->where('user_id', $request->user()->id)->delete();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    /** Loescht den gesamten Verlauf des Nutzers. */
    public function deleteAll(Request $request): JsonResponse
    {
        DB::table(self::CONVERSATIONS)->where('user_id', $request->user()->id)->delete();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * Stellt eine Frage. Mit "stream" kommt die Antwort als Ereignisstrom (meta, delta, done, error),
     * sonst als ein JSON-Dokument. Alle Pruefungen laufen, bevor etwas an den Anbieter geht.
     */
    public function chat(Request $request): JsonResponse|StreamedResponse
    {
        $user = $request->user();
        $maxInput = Config::int('max_input_chars');
        $input = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:' . $maxInput],
            'conversation' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'server' => ['sometimes', 'nullable', 'string', 'regex:/^([a-f0-9]{8}|[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})$/'],
            'share_context' => ['sometimes', 'boolean'],
            'stream' => ['sometimes', 'boolean'],
        ], [
            'message.max' => I18n::t('assistant.message_too_long', ['max' => $maxInput]),
            'message.required' => I18n::t('assistant.message_required'),
        ]);
        $question = trim(mb_scrub((string) $input['message']));
        if ($question === '') {
            throw \Illuminate\Validation\ValidationException::withMessages(['message' => [I18n::t('assistant.message_required')]]);
        }

        if (!Config::configured()) {
            throw new HttpException(503, I18n::t('assistant.not_configured'));
        }
        $provider = Config::provider();

        // Server und Gespraech pruefen: nur eigene Gespraeche, nur Server mit Konsolen-Recht.
        $server = null;
        if (!empty($input['server'])) {
            $server = ServerContext::find($user, (string) $input['server']);
            if ($server === null) {
                throw new NotFoundHttpException(I18n::t('assistant.server_not_found'));
            }
        }
        $share = ($input['share_context'] ?? false) === true && $server !== null && Config::all()['context_enabled'] === true;
        $conversation = !empty($input['conversation']) ? $this->ownConversation($user, (int) $input['conversation']) : null;
        if ($conversation === null
            && DB::table(self::CONVERSATIONS)->where('user_id', $user->id)->count() >= Config::MAX_CONVERSATIONS) {
            throw new HttpException(409, I18n::t('assistant.max_conversations', ['max' => Config::MAX_CONVERSATIONS]));
        }

        // Limits: pro Minute, eine Antwort gleichzeitig, pro Tag.
        $minuteKey = 'techtyl-assistant:minute:' . $user->id;
        if (RateLimiter::tooManyAttempts($minuteKey, Config::int('per_minute'))) {
            $seconds = max(1, RateLimiter::availableIn($minuteKey));
            throw new TooManyRequestsHttpException($seconds, I18n::t('assistant.rate_minute', ['seconds' => $seconds]));
        }
        $lock = Cache::lock('techtyl-assistant:run:' . $user->id, Config::int('timeout') * 3 + 60);
        if (!$lock->get()) {
            throw new TooManyRequestsHttpException(5, I18n::t('assistant.busy'));
        }

        try {
            RateLimiter::hit($minuteKey, 60);
            $daily = Config::int('daily_limit');
            if (!Quota::reserve($user->id, $daily)) {
                throw new TooManyRequestsHttpException(null, I18n::t('assistant.daily_limit', ['limit' => $daily]));
            }

            // Gespraech anlegen bzw. Auswahl dieses Gespraechs festhalten, dann die Frage speichern.
            $now = now();
            if ($conversation === null) {
                $conversationId = DB::table(self::CONVERSATIONS)->insertGetId([
                    'user_id' => $user->id,
                    'title' => self::title($question),
                    'server_id' => $server?->id,
                    'share_context' => $share,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $conversationId = (int) $conversation->id;
                DB::table(self::CONVERSATIONS)->where('id', $conversationId)->where('user_id', $user->id)
                    ->update(['server_id' => $server?->id, 'share_context' => $share, 'updated_at' => $now]);
            }
            $history = $this->history($conversationId);
            $questionId = DB::table(self::MESSAGES)->insertGetId([
                'conversation_id' => $conversationId,
                'role' => 'user',
                'content' => $question,
                'status' => 'ok',
                'created_at' => $now,
            ]);
        } catch (\Throwable $e) {
            $lock->release();
            throw $e;
        }

        $run = function (callable $emit) use ($user, $provider, $server, $share, $conversationId, $questionId, $question, $history, $lock): void {
            try {
                $this->run($emit, $user, $provider, $server, $share, $conversationId, $questionId, $question, $history);
            } finally {
                $lock->release();
            }
        };

        if (($input['stream'] ?? false) !== true) {
            $events = [];
            $run(function (string $event, array $data) use (&$events): bool {
                if ($event !== 'delta' && $event !== 'ping') {
                    $events[$event] = $data;
                }

                return true;
            });

            return new JsonResponse(['object' => 'techtyl_assistant_reply', 'attributes' => $events], isset($events['error']) ? 502 : 200);
        }

        return new StreamedResponse(function () use ($run): void {
            // Der Lauf soll auch nach einem Abbruch im Browser sauber enden (Teilantwort speichern).
            ignore_user_abort(true);
            @set_time_limit(0);
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            $run(function (string $event, array $data): bool {
                echo $event === 'ping' ? ": ping\n\n" : 'event: ' . $event . "\ndata: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n\n";
                flush();

                return connection_aborted() === 0;
            });
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, no-transform',
            // nginx soll die Antwort nicht puffern, sonst kommt sie am Stueck statt Wort fuer Wort.
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Holt (falls zugestimmt) die Server-Daten, ruft den Anbieter auf und speichert die Antwort.
     *
     * @param callable(string, array<string, mixed>): bool $emit false = Nutzer hat abgebrochen
     * @param array<int, array{role: string, content: string}> $history
     */
    private function run(callable $emit, User $user, string $provider, ?Server $server, bool $share, int $conversationId, int $questionId, string $question, array $history): void
    {
        $text = '';
        $alive = true;
        try {
            $content = $question;
            $contextLines = null;
            if ($share && $server !== null) {
                $context = ServerContext::build($server, Config::int('context_lines'), Config::int('context_chars'));
                $content .= "\n\n" . ServerContext::wrap($context['text']);
                $contextLines = $context['lines'];
                DB::table(self::MESSAGES)->where('id', $questionId)->update(['context_lines' => $contextLines]);
            }
            $conversation = DB::table(self::CONVERSATIONS)->where('id', $conversationId)->first();
            $alive = $emit('meta', [
                'conversation' => $this->conversationAttributes($conversation),
                'question' => ['id' => $questionId, 'context_lines' => $contextLines],
            ]);

            $messages = $history;
            $messages[] = ['role' => 'user', 'content' => $content];
            $result = new Result();
            if ($alive) {
                $result = Factory::make($provider)->chat(
                    Prompt::system($server, I18n::locale()),
                    self::alternate($messages),
                    Config::int('max_tokens'),
                    function (string $piece) use (&$text, &$alive, $emit): bool {
                        $text .= $piece;
                        $alive = $emit('delta', ['text' => $piece]);

                        return $alive;
                    },
                    function () use (&$alive, $emit): bool {
                        $alive = $emit('ping', []);

                        return $alive;
                    },
                );
            }

            $status = match (true) {
                !$alive || $result->stop === Result::ABORTED => 'stopped',
                $result->stop === Result::LENGTH => 'length',
                $result->stop === Result::REFUSAL => 'refusal',
                default => 'ok',
            };
            Quota::addTokens($user->id, $result->tokensIn, $result->tokensOut);

            if (trim($text) === '') {
                if ($status === 'stopped') {
                    // Vor dem ersten Wort gestoppt: nichts zu speichern, die Anfrage zaehlt trotzdem.
                    $emit('done', ['message' => null, 'usage' => $this->usage($user)]);

                    return;
                }
                throw new ProviderException(
                    $status === 'refusal' ? ProviderException::BLOCKED : ProviderException::BAD_REQUEST,
                    'empty answer, stop: ' . $result->stop,
                );
            }

            $message = $this->storeAnswer($conversationId, $text, $status, $provider, $result);
            $emit('done', ['message' => $message, 'usage' => $this->usage($user)]);
        } catch (ProviderException $e) {
            Log::warning('techtyl assistant: Anbieter-Fehler', [
                'provider' => $provider, 'kind' => $e->kind, 'status' => $e->status, 'detail' => $e->getMessage(), 'user' => $user->id,
            ]);
            $message = null;
            $removed = false;
            if (trim($text) !== '') {
                $message = $this->storeAnswer($conversationId, $text, 'error', $provider, new Result());
            } else {
                // Der Anbieter hat nichts geliefert: Die Anfrage zaehlt nicht gegen das Tageslimit
                // und die unbeantwortete Frage bleibt nicht im Verlauf stehen.
                Quota::refund($user->id);
                $removed = $this->discardQuestion($conversationId, $questionId);
            }
            $detail = $e->kind === ProviderException::BAD_REQUEST && str_starts_with($e->getMessage(), 'empty answer')
                ? I18n::t('assistant.empty_answer')
                : $e->userMessage();
            $emit('error', ['message' => $detail, 'kind' => $e->kind, 'partial' => $message, 'conversation_removed' => $removed, 'usage' => $this->usage($user)]);
        } catch (\Throwable $e) {
            Log::error('techtyl assistant: Fehler beim Beantworten', ['error' => $e->getMessage(), 'at' => $e->getFile() . ':' . $e->getLine(), 'user' => $user->id]);
            $removed = false;
            if (trim($text) === '') {
                Quota::refund($user->id);
                $removed = $this->discardQuestion($conversationId, $questionId);
            }
            $emit('error', ['message' => I18n::t('assistant.internal_error'), 'kind' => 'internal', 'partial' => null, 'conversation_removed' => $removed, 'usage' => $this->usage($user)]);
        }
    }

    /**
     * Entfernt eine Frage, auf die keine Antwort kam, und ein dadurch leeres Gespraech.
     *
     * @return bool true, wenn auch das Gespraech entfernt wurde
     */
    private function discardQuestion(int $conversationId, int $questionId): bool
    {
        try {
            DB::table(self::MESSAGES)->where('id', $questionId)->where('conversation_id', $conversationId)->delete();
            if (!DB::table(self::MESSAGES)->where('conversation_id', $conversationId)->exists()) {
                DB::table(self::CONVERSATIONS)->where('id', $conversationId)->delete();

                return true;
            }
        } catch (\Throwable $e) {
            Log::warning('techtyl assistant: unbeantwortete Frage nicht entfernt', ['error' => $e->getMessage()]);
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function storeAnswer(int $conversationId, string $text, string $status, string $provider, Result $result): array
    {
        $now = now();
        $id = DB::table(self::MESSAGES)->insertGetId([
            'conversation_id' => $conversationId,
            'role' => 'assistant',
            'content' => mb_scrub($text),
            'status' => $status,
            'provider' => $provider,
            'model' => Config::model($provider),
            'tokens_in' => $result->tokensIn,
            'tokens_out' => $result->tokensOut,
            'created_at' => $now,
        ]);
        DB::table(self::CONVERSATIONS)->where('id', $conversationId)->update(['updated_at' => $now]);

        return $this->messageAttributes(DB::table(self::MESSAGES)->where('id', $id)->first());
    }

    /**
     * Fruehere Nachrichten des Gespraechs fuer den Anbieter, begrenzt nach Anzahl und Laenge.
     * Frueher mitgeschickte Server-Daten sind nicht gespeichert und werden nicht erneut gesendet.
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function history(int $conversationId): array
    {
        $rows = DB::table(self::MESSAGES)->where('conversation_id', $conversationId)
            ->whereIn('status', ['ok', 'stopped', 'length'])
            ->orderByDesc('id')->limit(Config::HISTORY_MESSAGES)->get(['role', 'content']);
        $budget = Config::HISTORY_CHARS;
        $messages = [];
        foreach ($rows as $row) {
            $budget -= mb_strlen((string) $row->content);
            if ($budget < 0) {
                break;
            }
            array_unshift($messages, ['role' => $row->role === 'assistant' ? 'assistant' : 'user', 'content' => (string) $row->content]);
        }

        return $messages;
    }

    /**
     * Anbieter erwarten abwechselnd "user" und "assistant", beginnend mit "user".
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @return array<int, array{role: string, content: string}>
     */
    private static function alternate(array $messages): array
    {
        $out = [];
        foreach ($messages as $message) {
            if ($out === [] && $message['role'] !== 'user') {
                continue;
            }
            $last = count($out) - 1;
            if ($last >= 0 && $out[$last]['role'] === $message['role']) {
                $out[$last]['content'] .= "\n\n" . $message['content'];
            } else {
                $out[] = $message;
            }
        }

        return $out;
    }

    private function ownConversation(User $user, int $id): object
    {
        $row = DB::table(self::CONVERSATIONS)->where('id', $id)->where('user_id', $user->id)->first();
        if ($row === null) {
            throw new NotFoundHttpException(I18n::t('assistant.conversation_not_found'));
        }

        return $row;
    }

    /** @return array<int, array<string, mixed>> */
    private function conversationList(User $user): array
    {
        return DB::table(self::CONVERSATIONS . ' as c')->leftJoin('servers', 'servers.id', '=', 'c.server_id')
            ->where('c.user_id', $user->id)
            ->orderByDesc('c.updated_at')->orderByDesc('c.id')->limit(Config::MAX_CONVERSATIONS)
            ->get(['c.id', 'c.title', 'c.share_context', 'c.updated_at', 'c.server_id', 'servers.uuidShort as server_short'])
            ->map(fn ($row) => $this->conversationAttributes($row))->all();
    }

    /** @return array<string, mixed> */
    private function conversationAttributes(object $row): array
    {
        $server = property_exists($row, 'server_short')
            ? $row->server_short
            : ($row->server_id !== null ? DB::table('servers')->where('id', $row->server_id)->value('uuidShort') : null);

        return [
            'id' => (int) $row->id,
            'title' => (string) $row->title,
            'server' => $server,
            'share_context' => (bool) $row->share_context,
            'updated_at' => \Illuminate\Support\Carbon::parse($row->updated_at)->toAtomString(),
        ];
    }

    /** @return array<string, mixed> */
    private function messageAttributes(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'role' => $row->role === 'assistant' ? 'assistant' : 'user',
            'content' => (string) $row->content,
            'status' => (string) $row->status,
            'context_lines' => $row->context_lines !== null ? (int) $row->context_lines : null,
            'created_at' => $row->created_at !== null ? \Illuminate\Support\Carbon::parse($row->created_at)->toAtomString() : null,
        ];
    }

    /** @return array{daily: int, used_today: int} */
    private function usage(User $user): array
    {
        return ['daily' => Config::int('daily_limit'), 'used_today' => Quota::used($user->id)];
    }

    private static function title(string $question): string
    {
        $line = trim((string) preg_replace('/\s+/u', ' ', $question));

        return mb_strlen($line) > 60 ? mb_substr($line, 0, 59) . '…' : $line;
    }
}
