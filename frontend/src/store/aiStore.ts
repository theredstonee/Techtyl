import { create } from 'zustand';
import api from '@/lib/api';

interface AIStore {
  isLoading: boolean;
  getSuggestions: (gameType: string, players?: number) => Promise<any>;
  getHelp: (question: string, context?: any) => Promise<string>;
  getNameSuggestions: (gameType: string, count?: number) => Promise<string[]>;
  troubleshoot: (serverId: number, issue: string) => Promise<string>;
}

export const useAIStore = create<AIStore>(() => ({
  isLoading: false,

  getSuggestions: async (gameType, players) => {
    const response = await api.post('/ai/suggestions', {
      game_type: gameType,
      players,
    });
    return response.data.config;
  },

  getHelp: async (question, context = {}) => {
    const response = await api.post('/ai/help', {
      question,
      context,
    });
    return response.data.message;
  },

  getNameSuggestions: async (gameType, count = 5) => {
    const response = await api.post('/ai/name-suggestions', {
      game_type: gameType,
      count,
    });
    return response.data.names;
  },

  troubleshoot: async (serverId, issue) => {
    const response = await api.post(`/servers/${serverId}/ai/troubleshoot`, {
      issue,
    });
    return response.data.message;
  },
}));
