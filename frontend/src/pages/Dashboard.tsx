import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuthStore } from '@/store/authStore';
import { useServerStore, Server } from '@/store/serverStore';
import { Server as ServerIcon, Plus, LogOut, Cpu, HardDrive, Play, Square, Trash2, Activity } from 'lucide-react';
import toast from 'react-hot-toast';

export default function Dashboard() {
  const navigate = useNavigate();
  const { user, logout } = useAuthStore();
  const { servers, fetchServers, deleteServer, controlServer, isLoading } = useServerStore();

  useEffect(() => {
    fetchServers();
  }, []);

  const handleLogout = () => {
    logout();
    toast.success('Erfolgreich abgemeldet');
  };

  const handleControl = async (serverId: number, action: string) => {
    try {
      await controlServer(serverId, action);
      toast.success('Aktion ausgeführt');
    } catch (error) {
      toast.error('Aktion fehlgeschlagen');
    }
  };

  const handleDelete = async (serverId: number) => {
    if (!confirm('Server wirklich löschen?')) return;

    try {
      await deleteServer(serverId);
      toast.success('Server gelöscht');
    } catch (error) {
      toast.error('Löschen fehlgeschlagen');
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'running': return 'bg-green-100 text-green-800';
      case 'stopped': return 'bg-gray-100 text-gray-800';
      case 'installing': return 'bg-yellow-100 text-yellow-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const getStatusText = (status: string) => {
    switch (status) {
      case 'running': return 'Läuft';
      case 'stopped': return 'Gestoppt';
      case 'installing': return 'Installiert...';
      default: return status;
    }
  };

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <header className="bg-white border-b border-gray-200">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-3">
              <div className="w-10 h-10 bg-primary-600 rounded-xl flex items-center justify-center">
                <ServerIcon className="w-6 h-6 text-white" />
              </div>
              <div>
                <h1 className="text-xl font-bold text-gray-900">Techtyl</h1>
                <p className="text-xs text-gray-500">by Pterodactyl</p>
              </div>
            </div>

            <div className="flex items-center space-x-4">
              <div className="text-right">
                <p className="text-sm font-medium text-gray-900">{user?.name}</p>
                <p className="text-xs text-gray-500">
                  {servers.length} / {user?.server_limit} Server
                </p>
              </div>
              <button onClick={handleLogout} className="btn btn-secondary">
                <LogOut className="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </header>

      {/* Main Content */}
      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="flex items-center justify-between mb-8">
          <h2 className="text-2xl font-bold text-gray-900">Meine Server</h2>
          {user && servers.length < user.server_limit && (
            <Link to="/servers/create" className="btn btn-primary flex items-center space-x-2">
              <Plus className="w-5 h-5" />
              <span>Neuer Server</span>
            </Link>
          )}
        </div>

        {isLoading ? (
          <div className="text-center py-12">
            <Activity className="w-8 h-8 animate-spin text-primary-600 mx-auto mb-2" />
            <p className="text-gray-600">Lade Server...</p>
          </div>
        ) : servers.length === 0 ? (
          <div className="text-center py-12 card">
            <ServerIcon className="w-16 h-16 text-gray-400 mx-auto mb-4" />
            <h3 className="text-lg font-medium text-gray-900 mb-2">Keine Server</h3>
            <p className="text-gray-600 mb-6">Erstelle deinen ersten Server mit KI-Unterstützung</p>
            <Link to="/servers/create" className="btn btn-primary inline-flex items-center space-x-2">
              <Plus className="w-5 h-5" />
              <span>Server erstellen</span>
            </Link>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {servers.map((server) => (
              <ServerCard
                key={server.id}
                server={server}
                onControl={handleControl}
                onDelete={handleDelete}
                getStatusColor={getStatusColor}
                getStatusText={getStatusText}
              />
            ))}
          </div>
        )}
      </main>
    </div>
  );
}

function ServerCard({
  server,
  onControl,
  onDelete,
  getStatusColor,
  getStatusText
}: {
  server: Server;
  onControl: (id: number, action: string) => void;
  onDelete: (id: number) => void;
  getStatusColor: (status: string) => string;
  getStatusText: (status: string) => string;
}) {
  const cpuPercent = Math.round((Math.random() * 80));
  const memPercent = Math.round((Math.random() * 90));

  return (
    <div className="card hover:shadow-md transition-shadow">
      <div className="flex items-start justify-between mb-4">
        <div>
          <h3 className="font-semibold text-lg text-gray-900">{server.name}</h3>
          <p className="text-sm text-gray-500">{server.game_type}</p>
        </div>
        <span className={`px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(server.status)}`}>
          {getStatusText(server.status)}
        </span>
      </div>

      {server.description && (
        <p className="text-sm text-gray-600 mb-4">{server.description}</p>
      )}

      {/* Resource Usage */}
      <div className="space-y-3 mb-4">
        <div>
          <div className="flex justify-between text-xs text-gray-600 mb-1">
            <span className="flex items-center">
              <Cpu className="w-3 h-3 mr-1" />
              CPU
            </span>
            <span>{cpuPercent}%</span>
          </div>
          <div className="w-full bg-gray-200 rounded-full h-2">
            <div
              className="bg-primary-600 h-2 rounded-full transition-all"
              style={{ width: `${cpuPercent}%` }}
            />
          </div>
        </div>

        <div>
          <div className="flex justify-between text-xs text-gray-600 mb-1">
            <span className="flex items-center">
              <HardDrive className="w-3 h-3 mr-1" />
              RAM
            </span>
            <span>{memPercent}%</span>
          </div>
          <div className="w-full bg-gray-200 rounded-full h-2">
            <div
              className="bg-primary-600 h-2 rounded-full transition-all"
              style={{ width: `${memPercent}%` }}
            />
          </div>
        </div>
      </div>

      {/* Resource Specs */}
      <div className="grid grid-cols-3 gap-2 mb-4 text-xs text-gray-600">
        <div className="text-center p-2 bg-gray-50 rounded">
          <div className="font-medium text-gray-900">{server.cpu}</div>
          <div>Cores</div>
        </div>
        <div className="text-center p-2 bg-gray-50 rounded">
          <div className="font-medium text-gray-900">{server.memory}MB</div>
          <div>RAM</div>
        </div>
        <div className="text-center p-2 bg-gray-50 rounded">
          <div className="font-medium text-gray-900">{server.disk}MB</div>
          <div>Disk</div>
        </div>
      </div>

      {/* Controls */}
      <div className="flex space-x-2">
        {server.status === 'stopped' ? (
          <button
            onClick={() => onControl(server.id, 'start')}
            className="flex-1 btn btn-primary text-sm py-2"
          >
            <Play className="w-4 h-4 inline mr-1" />
            Start
          </button>
        ) : server.status === 'running' ? (
          <button
            onClick={() => onControl(server.id, 'stop')}
            className="flex-1 btn btn-secondary text-sm py-2"
          >
            <Square className="w-4 h-4 inline mr-1" />
            Stop
          </button>
        ) : null}

        <button
          onClick={() => onDelete(server.id)}
          className="btn btn-danger text-sm py-2 px-3"
        >
          <Trash2 className="w-4 h-4" />
        </button>
      </div>
    </div>
  );
}
