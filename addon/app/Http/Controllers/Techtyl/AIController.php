<?php

namespace Pterodactyl\Http\Controllers\Techtyl;

use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Pterodactyl\Services\AzureOpenAIService;

/**
 * Techtyl AI Controller
 * Pterodactyl Addon für AI-gestützte Server-Verwaltung
 */
class AIController extends Controller
{
    protected AzureOpenAIService $aiService;

    public function __construct(AzureOpenAIService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Get AI suggestions for server configuration
     */
    public function getSuggestions(Request $request)
    {
        $request->validate([
            'egg_id' => 'required|integer',
            'players' => 'nullable|integer|min:1',
        ]);

        // Hole Egg-Info aus Pterodactyl
        $egg = \Pterodactyl\Models\Egg::findOrFail($request->egg_id);
        $gameType = $egg->name;

        $requirements = [];
        if ($request->has('players')) {
            $requirements['players'] = $request->players;
        }

        $result = $this->aiService->getServerConfigSuggestions(
            $gameType,
            $requirements
        );

        return response()->json($result);
    }

    /**
     * Get AI help for general questions
     */
    public function getHelp(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:500',
            'egg_id' => 'nullable|integer',
        ]);

        $context = [];
        if ($request->has('egg_id')) {
            $egg = \Pterodactyl\Models\Egg::find($request->egg_id);
            if ($egg) {
                $context['game_type'] = $egg->name;
            }
        }
        $context['question'] = $request->question;

        $result = $this->aiService->helpWithServerCreation($context);

        return response()->json($result);
    }

    /**
     * Get server name suggestions
     */
    public function getNameSuggestions(Request $request)
    {
        $request->validate([
            'egg_id' => 'required|integer',
            'count' => 'nullable|integer|min:1|max:10',
        ]);

        $egg = \Pterodactyl\Models\Egg::findOrFail($request->egg_id);
        $gameType = $egg->name;

        $result = $this->aiService->suggestServerNames(
            $gameType,
            $request->count ?? 5
        );

        return response()->json($result);
    }

    /**
     * Get troubleshooting help for a server
     */
    public function troubleshoot(Request $request)
    {
        $request->validate([
            'server_id' => 'required|integer',
            'issue' => 'required|string|max:500',
        ]);

        // Prüfe ob User Zugriff auf Server hat
        $server = $request->user()->servers()
            ->findOrFail($request->server_id);

        $serverInfo = [
            'name' => $server->name,
            'egg' => $server->egg->name ?? 'Unknown',
            'memory' => $server->memory,
            'cpu' => $server->cpu,
            'disk' => $server->disk,
        ];

        $result = $this->aiService->helpTroubleshoot(
            $request->issue,
            $serverInfo
        );

        return response()->json($result);
    }
}
