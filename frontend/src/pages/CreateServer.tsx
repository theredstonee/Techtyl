import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useServerStore } from '@/store/serverStore';
import { useAIStore } from '@/store/aiStore';
import toast from 'react-hot-toast';
import { ArrowLeft, Bot, Sparkles, Cpu, HardDrive, Database, Lightbulb } from 'lucide-react';

export default function CreateServer() {
  const navigate = useNavigate();
  const { createServer } = useServerStore();
  const { getSuggestions, getHelp, getNameSuggestions } = useAIStore();

  const [formData, setFormData] = useState({
    name: '',
    description: '',
    game_type: '',
    cpu: 2,
    memory: 2048,
    disk: 10240,
  });

  const [aiHelp, setAiHelp] = useState<string>('');
  const [aiLoading, setAiLoading] = useState(false);
  const [showAI, setShowAI] = useState(true);
  const [question, setQuestion] = useState('');

  const gameTypes = [
    'Minecraft',
    'Rust',
    'ARK',
    'Counter-Strike',
    'Valheim',
    'Terraria',
    'TeamSpeak',
    'Discord Bot',
    'Sonstige'
  ];

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: name === 'cpu' || name === 'memory' || name === 'disk' ? parseInt(value) : value
    }));
  };

  const handleGetAISuggestions = async () => {
    if (!formData.game_type) {
      toast.error('Bitte wähle zuerst einen Server-Typ');
      return;
    }

    setAiLoading(true);
    try {
      const suggestions = await getSuggestions(formData.game_type);
      setFormData(prev => ({
        ...prev,
        cpu: suggestions.cpu || prev.cpu,
        memory: suggestions.memory || prev.memory,
        disk: suggestions.disk || prev.disk,
      }));
      setAiHelp(suggestions.explanation || 'Empfehlungen wurden angewendet!');
      toast.success('AI-Empfehlungen geladen!');
    } catch (error) {
      toast.error('Konnte keine Empfehlungen laden');
    } finally {
      setAiLoading(false);
    }
  };

  const handleAskAI = async () => {
    if (!question.trim()) {
      toast.error('Bitte stelle eine Frage');
      return;
    }

    setAiLoading(true);
    try {
      const response = await getHelp(question, {
        game_type: formData.game_type,
      });
      setAiHelp(response);
      setQuestion('');
    } catch (error) {
      toast.error('AI-Anfrage fehlgeschlagen');
    } finally {
      setAiLoading(false);
    }
  };

  const handleGetNameSuggestions = async () => {
    if (!formData.game_type) {
      toast.error('Bitte wähle zuerst einen Server-Typ');
      return;
    }

    setAiLoading(true);
    try {
      const names = await getNameSuggestions(formData.game_type, 5);
      if (names && names.length > 0) {
        setFormData(prev => ({ ...prev, name: names[0] }));
        setAiHelp(`Vorschläge: ${names.join(', ')}`);
        toast.success('Namen vorgeschlagen!');
      }
    } catch (error) {
      toast.error('Konnte keine Namen generieren');
    } finally {
      setAiLoading(false);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!formData.name || !formData.game_type) {
      toast.error('Bitte fülle alle Pflichtfelder aus');
      return;
    }

    try {
      await createServer(formData);
      toast.success('Server wird erstellt!');
      navigate('/');
    } catch (error: any) {
      const errors = error.response?.data?.errors;
      if (errors) {
        Object.values(errors).flat().forEach((err: any) => toast.error(err));
      } else {
        toast.error('Server-Erstellung fehlgeschlagen');
      }
    }
  };

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <header className="bg-white border-b border-gray-200 mb-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
          <button
            onClick={() => navigate('/')}
            className="flex items-center text-gray-600 hover:text-gray-900 transition-colors"
          >
            <ArrowLeft className="w-5 h-5 mr-2" />
            Zurück zum Dashboard
          </button>
        </div>
      </header>

      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Main Form */}
          <div className="lg:col-span-2">
            <div className="card">
              <h1 className="text-2xl font-bold mb-6 flex items-center">
                <Sparkles className="w-6 h-6 mr-2 text-primary-600" />
                Neuen Server erstellen
              </h1>

              <form onSubmit={handleSubmit} className="space-y-6">
                {/* Server Type */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Server-Typ *
                  </label>
                  <select
                    name="game_type"
                    value={formData.game_type}
                    onChange={handleChange}
                    className="input"
                    required
                  >
                    <option value="">Bitte wählen...</option>
                    {gameTypes.map(type => (
                      <option key={type} value={type}>{type}</option>
                    ))}
                  </select>
                </div>

                {/* Server Name */}
                <div>
                  <div className="flex justify-between items-center mb-2">
                    <label className="block text-sm font-medium text-gray-700">
                      Server-Name *
                    </label>
                    <button
                      type="button"
                      onClick={handleGetNameSuggestions}
                      disabled={aiLoading || !formData.game_type}
                      className="text-xs text-primary-600 hover:text-primary-700 flex items-center"
                    >
                      <Bot className="w-3 h-3 mr-1" />
                      AI-Vorschläge
                    </button>
                  </div>
                  <input
                    type="text"
                    name="name"
                    value={formData.name}
                    onChange={handleChange}
                    className="input"
                    placeholder="Mein Server"
                    required
                  />
                </div>

                {/* Description */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Beschreibung
                  </label>
                  <textarea
                    name="description"
                    value={formData.description}
                    onChange={handleChange}
                    className="input"
                    rows={3}
                    placeholder="Optionale Beschreibung..."
                  />
                </div>

                {/* Resources Header */}
                <div className="flex justify-between items-center pt-4">
                  <h3 className="text-lg font-semibold text-gray-900">Ressourcen</h3>
                  <button
                    type="button"
                    onClick={handleGetAISuggestions}
                    disabled={aiLoading || !formData.game_type}
                    className="btn btn-secondary text-sm flex items-center space-x-2"
                  >
                    <Bot className="w-4 h-4" />
                    <span>{aiLoading ? 'Lädt...' : 'AI-Empfehlungen'}</span>
                  </button>
                </div>

                {/* CPU */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    <Cpu className="w-4 h-4 inline mr-1" />
                    CPU-Kerne: {formData.cpu}
                  </label>
                  <input
                    type="range"
                    name="cpu"
                    min="1"
                    max="8"
                    value={formData.cpu}
                    onChange={handleChange}
                    className="w-full"
                  />
                  <div className="flex justify-between text-xs text-gray-500">
                    <span>1</span>
                    <span>8</span>
                  </div>
                </div>

                {/* Memory */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    <HardDrive className="w-4 h-4 inline mr-1" />
                    RAM: {formData.memory} MB ({(formData.memory / 1024).toFixed(1)} GB)
                  </label>
                  <input
                    type="range"
                    name="memory"
                    min="512"
                    max="16384"
                    step="512"
                    value={formData.memory}
                    onChange={handleChange}
                    className="w-full"
                  />
                  <div className="flex justify-between text-xs text-gray-500">
                    <span>512 MB</span>
                    <span>16 GB</span>
                  </div>
                </div>

                {/* Disk */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    <Database className="w-4 h-4 inline mr-1" />
                    Speicher: {formData.disk} MB ({(formData.disk / 1024).toFixed(1)} GB)
                  </label>
                  <input
                    type="range"
                    name="disk"
                    min="1024"
                    max="102400"
                    step="1024"
                    value={formData.disk}
                    onChange={handleChange}
                    className="w-full"
                  />
                  <div className="flex justify-between text-xs text-gray-500">
                    <span>1 GB</span>
                    <span>100 GB</span>
                  </div>
                </div>

                {/* Submit */}
                <div className="flex space-x-3 pt-6">
                  <button
                    type="button"
                    onClick={() => navigate('/')}
                    className="btn btn-secondary flex-1"
                  >
                    Abbrechen
                  </button>
                  <button
                    type="submit"
                    className="btn btn-primary flex-1"
                  >
                    Server erstellen
                  </button>
                </div>
              </form>
            </div>
          </div>

          {/* AI Assistant Sidebar */}
          <div className="lg:col-span-1">
            <div className="card sticky top-4">
              <div className="flex items-center justify-between mb-4">
                <h3 className="font-semibold text-lg flex items-center">
                  <Bot className="w-5 h-5 mr-2 text-primary-600" />
                  AI-Assistent
                </h3>
                <button
                  onClick={() => setShowAI(!showAI)}
                  className="text-sm text-gray-500 hover:text-gray-700"
                >
                  {showAI ? 'Ausblenden' : 'Anzeigen'}
                </button>
              </div>

              {showAI && (
                <>
                  <div className="mb-4">
                    <div className="bg-primary-50 border border-primary-200 rounded-lg p-4 mb-4">
                      <div className="flex items-start space-x-2">
                        <Lightbulb className="w-5 h-5 text-primary-600 flex-shrink-0 mt-0.5" />
                        <div className="text-sm text-primary-900">
                          {aiHelp || 'Hallo! Ich helfe dir bei der Server-Konfiguration. Stelle mir Fragen oder lass mich Empfehlungen geben!'}
                        </div>
                      </div>
                    </div>
                  </div>

                  <div className="space-y-3">
                    <div>
                      <input
                        type="text"
                        value={question}
                        onChange={(e) => setQuestion(e.target.value)}
                        onKeyPress={(e) => e.key === 'Enter' && handleAskAI()}
                        placeholder="Frage den AI-Assistenten..."
                        className="input text-sm"
                        disabled={aiLoading}
                      />
                    </div>

                    <button
                      onClick={handleAskAI}
                      disabled={aiLoading || !question.trim()}
                      className="btn btn-primary w-full text-sm"
                    >
                      {aiLoading ? 'Lädt...' : 'Frage stellen'}
                    </button>
                  </div>

                  <div className="mt-6 pt-6 border-t border-gray-200">
                    <h4 className="text-sm font-medium text-gray-700 mb-3">Schnellaktionen</h4>
                    <div className="space-y-2">
                      <button
                        onClick={handleGetAISuggestions}
                        disabled={aiLoading || !formData.game_type}
                        className="w-full text-left text-sm text-primary-600 hover:text-primary-700 p-2 hover:bg-primary-50 rounded transition-colors"
                      >
                        Ressourcen-Empfehlungen
                      </button>
                      <button
                        onClick={handleGetNameSuggestions}
                        disabled={aiLoading || !formData.game_type}
                        className="w-full text-left text-sm text-primary-600 hover:text-primary-700 p-2 hover:bg-primary-50 rounded transition-colors"
                      >
                        Namen vorschlagen
                      </button>
                    </div>
                  </div>
                </>
              )}
            </div>
          </div>
        </div>
      </main>
    </div>
  );
}
