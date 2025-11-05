import { create } from 'zustand';
import api from '@/lib/api';

export interface Server {
  id: number;
  name: string;
  description: string | null;
  game_type: string;
  cpu: number;
  memory: number;
  disk: number;
  status: 'running' | 'stopped' | 'installing';
  created_at: string;
}

interface ServerStore {
  servers: Server[];
  isLoading: boolean;
  fetchServers: () => Promise<void>;
  createServer: (data: Partial<Server>) => Promise<Server>;
  deleteServer: (id: number) => Promise<void>;
  controlServer: (id: number, action: string) => Promise<void>;
}

export const useServerStore = create<ServerStore>((set, get) => ({
  servers: [],
  isLoading: false,

  fetchServers: async () => {
    set({ isLoading: true });
    try {
      const response = await api.get('/servers');
      set({ servers: response.data.servers, isLoading: false });
    } catch (error) {
      set({ isLoading: false });
      throw error;
    }
  },

  createServer: async (data) => {
    const response = await api.post('/servers', data);
    const newServer = response.data.server;
    set({ servers: [...get().servers, newServer] });
    return newServer;
  },

  deleteServer: async (id) => {
    await api.delete(`/servers/${id}`);
    set({ servers: get().servers.filter((s) => s.id !== id) });
  },

  controlServer: async (id, action) => {
    await api.post(`/servers/${id}/control`, { action });
    await get().fetchServers();
  },
}));
