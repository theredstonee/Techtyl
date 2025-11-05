<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\ClaudeAIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServerController extends Controller
{
    protected ClaudeAIService $aiService;

    public function __construct(ClaudeAIService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Get all servers for the authenticated user
     */
    public function index(Request $request)
    {
        $servers = $request->user()->servers()
            ->withCount('allocations')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'servers' => $servers
        ]);
    }

    /**
     * Create a new server
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // Check server limit
        if ($user->servers()->count() >= $user->server_limit) {
            return response()->json([
                'success' => false,
                'message' => 'Server-Limit erreicht'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\s\-_]+$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'game_type' => ['required', 'string', 'max:100'],
            'cpu' => ['required', 'integer', 'min:1', 'max:8'],
            'memory' => ['required', 'integer', 'min:512', 'max:16384'],
            'disk' => ['required', 'integer', 'min:1024', 'max:102400'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $server = $user->servers()->create([
            'name' => $request->name,
            'description' => $request->description,
            'game_type' => $request->game_type,
            'cpu' => $request->cpu,
            'memory' => $request->memory,
            'disk' => $request->disk,
            'status' => 'installing',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Server wird erstellt',
            'server' => $server
        ], 201);
    }

    /**
     * Get AI suggestions for server configuration
     */
    public function getAISuggestions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'game_type' => ['required', 'string'],
            'players' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $requirements = [];
        if ($request->has('players')) {
            $requirements['players'] = $request->players;
        }

        $result = $this->aiService->getServerConfigSuggestions(
            $request->game_type,
            $requirements
        );

        return response()->json($result);
    }

    /**
     * Get AI help for server creation
     */
    public function getAIHelp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question' => ['required', 'string', 'max:500'],
            'context' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $context = $request->context ?? [];
        $context['question'] = $request->question;

        $result = $this->aiService->helpWithServerCreation($context);

        return response()->json($result);
    }

    /**
     * Get server name suggestions from AI
     */
    public function getNameSuggestions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'game_type' => ['required', 'string'],
            'count' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->aiService->suggestServerNames(
            $request->game_type,
            $request->count ?? 5
        );

        return response()->json($result);
    }

    /**
     * Get troubleshooting help from AI
     */
    public function getTroubleshootHelp(Request $request, Server $server)
    {
        // Check ownership
        if ($server->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Zugriff verweigert'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'issue' => ['required', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $serverInfo = [
            'type' => $server->game_type,
            'cpu' => $server->cpu,
            'memory' => $server->memory,
            'status' => $server->status,
        ];

        $result = $this->aiService->helpTroubleshoot(
            $request->issue,
            $serverInfo
        );

        return response()->json($result);
    }

    /**
     * Show a specific server
     */
    public function show(Request $request, Server $server)
    {
        // Check ownership
        if ($server->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Zugriff verweigert'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'server' => $server->load(['allocations', 'user'])
        ]);
    }

    /**
     * Update server
     */
    public function update(Request $request, Server $server)
    {
        // Check ownership
        if ($server->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Zugriff verweigert'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\s\-_]+$/'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $server->update($request->only(['name', 'description']));

        return response()->json([
            'success' => true,
            'message' => 'Server aktualisiert',
            'server' => $server
        ]);
    }

    /**
     * Delete server
     */
    public function destroy(Request $request, Server $server)
    {
        // Check ownership
        if ($server->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Zugriff verweigert'
            ], 403);
        }

        $server->delete();

        return response()->json([
            'success' => true,
            'message' => 'Server gelöscht'
        ]);
    }

    /**
     * Control server (start, stop, restart)
     */
    public function control(Request $request, Server $server)
    {
        // Check ownership
        if ($server->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Zugriff verweigert'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'action' => ['required', 'in:start,stop,restart,kill'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Update server status based on action
        $statusMap = [
            'start' => 'running',
            'stop' => 'stopped',
            'restart' => 'running',
            'kill' => 'stopped',
        ];

        $server->update([
            'status' => $statusMap[$request->action]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Aktion ausgeführt',
            'server' => $server
        ]);
    }

    /**
     * Get server resource usage
     */
    public function resources(Request $request, Server $server)
    {
        // Check ownership
        if ($server->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Zugriff verweigert'
            ], 403);
        }

        // Simulate resource usage (in a real implementation, this would come from the actual server)
        $usage = [
            'cpu' => [
                'current' => rand(10, 80),
                'limit' => $server->cpu * 100,
                'percentage' => rand(10, 80)
            ],
            'memory' => [
                'current' => rand(200, $server->memory),
                'limit' => $server->memory,
                'percentage' => rand(20, 90)
            ],
            'disk' => [
                'current' => rand(500, $server->disk),
                'limit' => $server->disk,
                'percentage' => rand(10, 70)
            ],
            'network' => [
                'rx' => rand(100, 1000),
                'tx' => rand(100, 1000),
            ]
        ];

        return response()->json([
            'success' => true,
            'usage' => $usage
        ]);
    }
}
