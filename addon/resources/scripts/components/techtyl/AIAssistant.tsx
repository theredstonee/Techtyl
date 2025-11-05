import React, { useState } from 'react';
import axios from 'axios';

interface AIAssistantProps {
    eggId: number;
    onSuggestions?: (suggestions: any) => void;
}

export const AIAssistant: React.FC<AIAssistantProps> = ({ eggId, onSuggestions }) => {
    const [loading, setLoading] = useState(false);
    const [result, setResult] = useState<string>('');
    const [question, setQuestion] = useState('');

    const handleGetSuggestions = async () => {
        setLoading(true);
        try {
            const response = await axios.post('/api/techtyl/ai/suggestions', {
                egg_id: eggId,
            });

            if (response.data.success && response.data.config) {
                setResult(`Empfohlene Konfiguration:\nCPU: ${response.data.config.cpu} Kerne\nRAM: ${response.data.config.memory} MB\nDisk: ${response.data.config.disk} MB\n\n${response.data.config.explanation}`);

                if (onSuggestions) {
                    onSuggestions(response.data.config);
                }
            } else {
                setResult('Konnte keine Empfehlungen generieren.');
            }
        } catch (error) {
            setResult('Fehler beim Abrufen der Empfehlungen.');
            console.error(error);
        } finally {
            setLoading(false);
        }
    };

    const handleAskQuestion = async () => {
        if (!question.trim()) return;

        setLoading(true);
        try {
            const response = await axios.post('/api/techtyl/ai/help', {
                egg_id: eggId,
                question: question,
            });

            if (response.data.success) {
                setResult(response.data.message);
            } else {
                setResult('Konnte keine Antwort generieren.');
            }
        } catch (error) {
            setResult('Fehler beim Abrufen der Hilfe.');
            console.error(error);
        } finally {
            setLoading(false);
        }
    };

    const handleGetNames = async () => {
        setLoading(true);
        try {
            const response = await axios.post('/api/techtyl/ai/names', {
                egg_id: eggId,
                count: 5,
            });

            if (response.data.success && response.data.names) {
                setResult(`Vorgeschlagene Namen:\n${response.data.names.join('\n')}`);
            } else {
                setResult('Konnte keine Namen generieren.');
            }
        } catch (error) {
            setResult('Fehler beim Generieren der Namen.');
            console.error(error);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="bg-neutral-800 rounded-lg p-4 mb-4">
            <div className="flex items-center mb-3">
                <svg className="w-5 h-5 mr-2 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <h3 className="text-lg font-semibold text-white">AI-Assistent</h3>
            </div>

            <div className="space-y-3 mb-4">
                <button
                    onClick={handleGetSuggestions}
                    disabled={loading}
                    className="w-full bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded transition-colors disabled:opacity-50"
                >
                    {loading ? 'Lädt...' : '✨ Konfiguration vorschlagen'}
                </button>

                <button
                    onClick={handleGetNames}
                    disabled={loading}
                    className="w-full bg-neutral-700 hover:bg-neutral-600 text-white px-4 py-2 rounded transition-colors disabled:opacity-50"
                >
                    {loading ? 'Lädt...' : '🏷️ Namen vorschlagen'}
                </button>
            </div>

            <div className="mb-4">
                <input
                    type="text"
                    value={question}
                    onChange={(e) => setQuestion(e.target.value)}
                    onKeyPress={(e) => e.key === 'Enter' && handleAskQuestion()}
                    placeholder="Frage den AI-Assistenten..."
                    className="w-full bg-neutral-700 text-white px-4 py-2 rounded mb-2 focus:ring-2 focus:ring-primary-500 outline-none"
                    disabled={loading}
                />
                <button
                    onClick={handleAskQuestion}
                    disabled={loading || !question.trim()}
                    className="w-full bg-neutral-700 hover:bg-neutral-600 text-white px-4 py-2 rounded transition-colors disabled:opacity-50"
                >
                    {loading ? 'Lädt...' : '💬 Frage stellen'}
                </button>
            </div>

            {result && (
                <div className="bg-neutral-700 rounded p-3 text-sm text-gray-200 whitespace-pre-wrap">
                    {result}
                </div>
            )}
        </div>
    );
};

export default AIAssistant;
