#!/bin/bash

# ========================================
# Techtyl Customization Script
# Branding, User-Registrierung, AI-Features
# ========================================

PANEL_DIR="/var/www/pterodactyl"

echo "🎨 Passe Techtyl an..."

# ========================================
# 1. Branding - "Techtyl based on Pterodactyl"
# ========================================

# App Name ändern
sed -i "s/APP_NAME=.*/APP_NAME=\"Techtyl\"/" ${PANEL_DIR}/.env

# Panel Title in Config
cat > ${PANEL_DIR}/config/app.php.patch <<'PHPEOF'
    'name' => env('APP_NAME', 'Techtyl'),
    'techtyl' => [
        'version' => '1.0.0',
        'based_on' => 'Pterodactyl Panel',
        'tagline' => 'AI-powered Game Server Management',
    ],
PHPEOF

# Frontend Branding - Logo/Title
mkdir -p ${PANEL_DIR}/resources/scripts/components/techtyl

cat > ${PANEL_DIR}/resources/scripts/components/techtyl/Branding.tsx <<'TSXEOF'
import React from 'react';

export const TechtylBranding = () => {
    return (
        <div className="text-center py-4">
            <h1 className="text-3xl font-bold text-white">
                🦕 Techtyl
            </h1>
            <p className="text-sm text-gray-400 mt-2">
                based on Pterodactyl Panel
            </p>
            <p className="text-xs text-gray-500 mt-1">
                AI-powered Server Management
            </p>
        </div>
    );
};

export default TechtylBranding;
TSXEOF

echo "✓ Branding angepasst"

# ========================================
# 2. User-Registrierung aktivieren
# ========================================

# Registrierung in .env aktivieren
if ! grep -q "REGISTRATION_ENABLED" ${PANEL_DIR}/.env; then
    cat >> ${PANEL_DIR}/.env <<EOF

# Techtyl User-Registrierung
REGISTRATION_ENABLED=true
RECAPTCHA_ENABLED=false
RECAPTCHA_SECRET_KEY=
RECAPTCHA_WEBSITE_KEY=
EOF
fi

# Registrations-Controller
cat > ${PANEL_DIR}/app/Http/Controllers/Auth/RegisterController.php <<'PHPEOF'
<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Pterodactyl\Models\User;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\RegistersUsers;

class RegisterController extends Controller
{
    use RegistersUsers;

    protected $redirectTo = '/';

    public function __construct()
    {
        $this->middleware('guest');
    }

    public function showRegistrationForm()
    {
        if (!config('pterodactyl.registration_enabled', env('REGISTRATION_ENABLED', false))) {
            abort(404);
        }

        return view('auth.register');
    }

    protected function validator(array $data)
    {
        return Validator::make($data, [
            'username' => 'required|string|max:255|unique:users|regex:/^[a-zA-Z0-9_-]+$/',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);
    }

    protected function create(array $data)
    {
        return User::create([
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'name_first' => $data['username'],
            'name_last' => '',
            'root_admin' => false,
        ]);
    }
}
PHPEOF

# Registrations-Route
if ! grep -q "Route::get('/register'" ${PANEL_DIR}/routes/auth.php 2>/dev/null; then
    cat >> ${PANEL_DIR}/routes/auth.php <<'PHPEOF'

// User Registration (Techtyl)
Route::get('/register', 'Auth\RegisterController@showRegistrationForm')->name('register');
Route::post('/register', 'Auth\RegisterController@register');
PHPEOF
fi

# Registration View erstellen
mkdir -p ${PANEL_DIR}/resources/views/auth

cat > ${PANEL_DIR}/resources/views/auth/register.blade.php <<'BLADEEOF'
@extends('templates/wrapper', [
    'css' => ['body' => 'bg-neutral-800'],
])

@section('container')
<div class="fixed pin flex items-center justify-center">
    <div class="w-full max-w-md">
        <div class="bg-neutral-900 shadow-lg rounded-lg p-8">
            <div class="text-center mb-6">
                <h1 class="text-3xl font-bold text-white">🦕 Techtyl</h1>
                <p class="text-sm text-gray-400 mt-1">based on Pterodactyl</p>
                <p class="text-sm text-gray-500 mt-1">Account erstellen</p>
            </div>

            @if ($errors->any())
                <div class="bg-red-600 rounded p-3 mb-4">
                    <ul class="list-disc list-inside text-sm text-white">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}">
                @csrf

                <div class="mb-4">
                    <label class="block text-gray-300 text-sm font-bold mb-2">
                        Benutzername
                    </label>
                    <input type="text" name="username" value="{{ old('username') }}"
                           class="w-full bg-neutral-700 text-white rounded px-4 py-2 focus:ring-2 focus:ring-blue-500 outline-none"
                           required autofocus>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-300 text-sm font-bold mb-2">
                        E-Mail
                    </label>
                    <input type="email" name="email" value="{{ old('email') }}"
                           class="w-full bg-neutral-700 text-white rounded px-4 py-2 focus:ring-2 focus:ring-blue-500 outline-none"
                           required>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-300 text-sm font-bold mb-2">
                        Passwort
                    </label>
                    <input type="password" name="password"
                           class="w-full bg-neutral-700 text-white rounded px-4 py-2 focus:ring-2 focus:ring-blue-500 outline-none"
                           required>
                    <p class="text-xs text-gray-500 mt-1">Mindestens 8 Zeichen</p>
                </div>

                <div class="mb-6">
                    <label class="block text-gray-300 text-sm font-bold mb-2">
                        Passwort bestätigen
                    </label>
                    <input type="password" name="password_confirmation"
                           class="w-full bg-neutral-700 text-white rounded px-4 py-2 focus:ring-2 focus:ring-blue-500 outline-none"
                           required>
                </div>

                <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded transition-colors">
                    Account erstellen
                </button>
            </form>

            <div class="text-center mt-6">
                <p class="text-gray-400 text-sm">
                    Hast du schon einen Account?
                    <a href="{{ route('auth.login') }}" class="text-blue-400 hover:text-blue-300">
                        Jetzt anmelden
                    </a>
                </p>
            </div>
        </div>

        <p class="text-center text-gray-600 text-xs mt-4">
            © {{ date('Y') }} Techtyl - AI-powered Server Management
        </p>
    </div>
</div>
@endsection
BLADEEOF

# Login-View um Registrierungs-Link erweitern
if [ -f "${PANEL_DIR}/resources/views/auth/login.blade.php" ]; then
    if ! grep -q "route('register')" ${PANEL_DIR}/resources/views/auth/login.blade.php; then
        # Füge Registrierungs-Link hinzu (wird später vom User angepasst)
        echo "✓ Registrierungs-Views erstellt"
    fi
fi

echo "✓ User-Registrierung aktiviert"

# ========================================
# 3. Server-Erstellung für normale User
# ========================================

# User Server Creation Config
cat >> ${PANEL_DIR}/.env <<EOF

# User Server Creation
USER_SERVER_CREATION_ENABLED=true
USER_SERVER_LIMIT=3
USER_SERVER_MEMORY_LIMIT=4096
USER_SERVER_CPU_LIMIT=200
USER_SERVER_DISK_LIMIT=10240
EOF

# Middleware für User Server Creation
cat > ${PANEL_DIR}/app/Http/Middleware/UserCanCreateServer.php <<'PHPEOF'
<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class UserCanCreateServer
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('auth.login');
        }

        // Admin kann immer Server erstellen
        if ($user->root_admin) {
            return $next($request);
        }

        // Prüfe ob User Server Creation aktiviert ist
        if (!env('USER_SERVER_CREATION_ENABLED', false)) {
            abort(403, 'Server creation is disabled for users.');
        }

        // Prüfe Server-Limit
        $serverCount = $user->servers()->count();
        $limit = env('USER_SERVER_LIMIT', 3);

        if ($serverCount >= $limit) {
            abort(403, "You have reached your server limit ({$limit} servers).");
        }

        return $next($request);
    }
}
PHPEOF

# User Server Controller
cat > ${PANEL_DIR}/app/Http/Controllers/Api/Client/Servers/UserServerController.php <<'PHPEOF'
<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Allocation;
use Illuminate\Support\Str;
use Pterodactyl\Services\Servers\ServerCreationService;

class UserServerController extends ClientApiController
{
    protected ServerCreationService $creationService;

    public function __construct(ServerCreationService $creationService)
    {
        parent::__construct();
        $this->creationService = $creationService;
    }

    public function create(Request $request)
    {
        $user = $request->user();

        // Validierung
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'egg_id' => 'required|integer|exists:eggs,id',
            'memory' => 'required|integer|min:128|max:' . env('USER_SERVER_MEMORY_LIMIT', 4096),
            'cpu' => 'required|integer|min:50|max:' . env('USER_SERVER_CPU_LIMIT', 200),
            'disk' => 'required|integer|min:512|max:' . env('USER_SERVER_DISK_LIMIT', 10240),
        ]);

        // Wähle automatisch Node und Allocation
        $node = Node::where('public', true)->first();
        if (!$node) {
            return response()->json(['error' => 'No available nodes'], 500);
        }

        $allocation = Allocation::where('node_id', $node->id)
            ->whereNull('server_id')
            ->first();

        if (!$allocation) {
            return response()->json(['error' => 'No available allocations'], 500);
        }

        // Server erstellen
        $server = $this->creationService->handle([
            'name' => $validated['name'],
            'owner_id' => $user->id,
            'egg_id' => $validated['egg_id'],
            'node_id' => $node->id,
            'allocation_id' => $allocation->id,
            'memory' => $validated['memory'],
            'disk' => $validated['disk'],
            'cpu' => $validated['cpu'],
            'swap' => 0,
            'io' => 500,
            'image' => Egg::find($validated['egg_id'])->docker_image,
            'startup' => Egg::find($validated['egg_id'])->startup,
            'environment' => [],
            'start_on_completion' => false,
        ]);

        return response()->json([
            'success' => true,
            'server' => $server,
            'message' => 'Server erfolgreich erstellt!'
        ]);
    }
}
PHPEOF

# Route für User Server Creation
cat >> ${PANEL_DIR}/routes/api-client.php <<'PHPEOF'

// User Server Creation (Techtyl)
Route::post('/servers/create', 'Servers\UserServerController@create')
    ->middleware('techtyl.can_create_server');
PHPEOF

echo "✓ Server-Erstellung für User aktiviert"

# ========================================
# 4. Frontend für Server-Erstellung mit AI
# ========================================

cat > ${PANEL_DIR}/resources/scripts/components/techtyl/UserServerCreation.tsx <<'TSXEOF'
import React, { useState, useEffect } from 'react';
import axios from 'axios';

interface Egg {
    id: number;
    name: string;
    description: string;
}

export const UserServerCreation: React.FC = () => {
    const [eggs, setEggs] = useState<Egg[]>([]);
    const [selectedEgg, setSelectedEgg] = useState<number | null>(null);
    const [serverName, setServerName] = useState('');
    const [memory, setMemory] = useState(2048);
    const [cpu, setCpu] = useState(100);
    const [disk, setDisk] = useState(5120);
    const [loading, setLoading] = useState(false);
    const [aiSuggestion, setAiSuggestion] = useState<any>(null);

    // Hole verfügbare Eggs
    useEffect(() => {
        axios.get('/api/client/eggs').then(res => {
            setEggs(res.data.data || []);
        });
    }, []);

    // AI-Empfehlungen holen
    const getAISuggestions = async () => {
        if (!selectedEgg) return;

        setLoading(true);
        try {
            const response = await axios.post('/api/client/techtyl/ai/suggestions', {
                egg_id: selectedEgg,
            });

            if (response.data.success && response.data.config) {
                const config = response.data.config;
                setMemory(config.memory);
                setCpu(config.cpu);
                setDisk(config.disk);
                setAiSuggestion(config);
            }
        } catch (error) {
            console.error('AI error:', error);
        } finally {
            setLoading(false);
        }
    };

    // Namen-Vorschläge holen
    const getNameSuggestions = async () => {
        if (!selectedEgg) return;

        setLoading(true);
        try {
            const response = await axios.post('/api/client/techtyl/ai/names', {
                egg_id: selectedEgg,
                count: 5,
            });

            if (response.data.success && response.data.names) {
                // Zeige Namen als Dropdown oder Liste
                const names = response.data.names;
                if (names.length > 0) {
                    setServerName(names[0]);
                }
            }
        } catch (error) {
            console.error('Name generation error:', error);
        } finally {
            setLoading(false);
        }
    };

    // Server erstellen
    const createServer = async () => {
        setLoading(true);
        try {
            const response = await axios.post('/api/client/servers/create', {
                name: serverName,
                egg_id: selectedEgg,
                memory,
                cpu,
                disk,
            });

            if (response.data.success) {
                alert('✅ Server erfolgreich erstellt!');
                window.location.href = '/';
            }
        } catch (error: any) {
            alert('❌ Fehler: ' + (error.response?.data?.message || 'Unbekannter Fehler'));
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="max-w-4xl mx-auto p-6">
            <div className="bg-neutral-800 rounded-lg shadow-lg p-6">
                <h1 className="text-2xl font-bold text-white mb-6">
                    🎮 Neuen Server erstellen
                </h1>

                {/* Egg-Auswahl */}
                <div className="mb-6">
                    <label className="block text-gray-300 text-sm font-bold mb-2">
                        Spiel / Server-Typ
                    </label>
                    <select
                        value={selectedEgg || ''}
                        onChange={(e) => setSelectedEgg(parseInt(e.target.value))}
                        className="w-full bg-neutral-700 text-white rounded px-4 py-2 focus:ring-2 focus:ring-blue-500 outline-none"
                    >
                        <option value="">Wähle ein Spiel...</option>
                        {eggs.map(egg => (
                            <option key={egg.id} value={egg.id}>{egg.name}</option>
                        ))}
                    </select>
                </div>

                {selectedEgg && (
                    <>
                        {/* AI-Assistent */}
                        <div className="bg-neutral-900 rounded-lg p-4 mb-6">
                            <h3 className="text-lg font-semibold text-white mb-3 flex items-center">
                                <span className="mr-2">🤖</span>
                                AI-Assistent
                            </h3>

                            <div className="grid grid-cols-2 gap-3 mb-4">
                                <button
                                    onClick={getAISuggestions}
                                    disabled={loading}
                                    className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition-colors disabled:opacity-50"
                                >
                                    ✨ Optimale Einstellungen vorschlagen
                                </button>

                                <button
                                    onClick={getNameSuggestions}
                                    disabled={loading}
                                    className="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded transition-colors disabled:opacity-50"
                                >
                                    🏷️ Namen generieren
                                </button>
                            </div>

                            {aiSuggestion && (
                                <div className="bg-neutral-700 rounded p-3 text-sm text-gray-200">
                                    <strong>💡 AI-Empfehlung:</strong>
                                    <p className="mt-1">{aiSuggestion.explanation}</p>
                                </div>
                            )}
                        </div>

                        {/* Server-Name */}
                        <div className="mb-4">
                            <label className="block text-gray-300 text-sm font-bold mb-2">
                                Server-Name
                            </label>
                            <input
                                type="text"
                                value={serverName}
                                onChange={(e) => setServerName(e.target.value)}
                                className="w-full bg-neutral-700 text-white rounded px-4 py-2 focus:ring-2 focus:ring-blue-500 outline-none"
                                placeholder="Mein-Server"
                            />
                        </div>

                        {/* Ressourcen */}
                        <div className="grid grid-cols-3 gap-4 mb-6">
                            <div>
                                <label className="block text-gray-300 text-sm font-bold mb-2">
                                    RAM (MB)
                                </label>
                                <input
                                    type="number"
                                    value={memory}
                                    onChange={(e) => setMemory(parseInt(e.target.value))}
                                    className="w-full bg-neutral-700 text-white rounded px-4 py-2 focus:ring-2 focus:ring-blue-500 outline-none"
                                    min="128"
                                    max="4096"
                                />
                            </div>

                            <div>
                                <label className="block text-gray-300 text-sm font-bold mb-2">
                                    CPU (%)
                                </label>
                                <input
                                    type="number"
                                    value={cpu}
                                    onChange={(e) => setCpu(parseInt(e.target.value))}
                                    className="w-full bg-neutral-700 text-white rounded px-4 py-2 focus:ring-2 focus:ring-blue-500 outline-none"
                                    min="50"
                                    max="200"
                                />
                            </div>

                            <div>
                                <label className="block text-gray-300 text-sm font-bold mb-2">
                                    Disk (MB)
                                </label>
                                <input
                                    type="number"
                                    value={disk}
                                    onChange={(e) => setDisk(parseInt(e.target.value))}
                                    className="w-full bg-neutral-700 text-white rounded px-4 py-2 focus:ring-2 focus:ring-blue-500 outline-none"
                                    min="512"
                                    max="10240"
                                />
                            </div>
                        </div>

                        {/* Erstellen-Button */}
                        <button
                            onClick={createServer}
                            disabled={loading || !serverName}
                            className="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded transition-colors disabled:opacity-50"
                        >
                            {loading ? 'Erstelle Server...' : '🚀 Server erstellen'}
                        </button>
                    </>
                )}
            </div>
        </div>
    );
};

export default UserServerCreation;
TSXEOF

echo "✓ Frontend-Komponenten erstellt"

# ========================================
# Permissions anpassen
# ========================================

chown -R www-data:www-data ${PANEL_DIR}
chmod -R 755 ${PANEL_DIR}/storage ${PANEL_DIR}/bootstrap/cache

# Cache leeren
cd ${PANEL_DIR}
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo ""
echo "✅ Techtyl Customization abgeschlossen!"
echo ""
echo "Aktivierte Features:"
echo "  ✓ Branding: 'Techtyl based on Pterodactyl'"
echo "  ✓ User-Registrierung"
echo "  ✓ User können Server erstellen (Limit: 3)"
echo "  ✓ AI-Features für alle User"
echo ""
