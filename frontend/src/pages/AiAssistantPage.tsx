import { useEffect, useRef, useState, type FormEvent } from "react";
import {
    Archive,
    Bot,
    MessageSquare,
    Plus,
    Send,
    Sparkles,
    UserRound,
} from "lucide-react";
import { Empty, ErrorBox, PageTitle, Spinner } from "../components/ui";
import { api, errorMessage } from "../lib/api";
import { useAuth } from "../lib/auth";
import type { AiConversation, AiMessage, Pagination } from "../types";

const suggestions = [
    "¿Cuánto vendimos hoy?",
    "¿Cuánto vendimos este mes?",
    "¿Qué productos tienen stock bajo?",
    "¿Cuáles son nuestros productos más vendidos?",
];

export default function AiAssistantPage() {
    const { membership } = useAuth();
    const organizationId = membership?.organization.id;
    const [conversations, setConversations] = useState<AiConversation[]>([]);
    const [activeId, setActiveId] = useState<number | null>(null);
    const [messages, setMessages] = useState<AiMessage[]>([]);
    const [input, setInput] = useState("");
    const [loading, setLoading] = useState(true);
    const [sending, setSending] = useState(false);
    const [error, setError] = useState("");
    const endRef = useRef<HTMLDivElement>(null);

    const basePath = `/organizations/${organizationId}/ai/conversations`;

    useEffect(() => {
        if (!organizationId) return;
        setLoading(true);
        setError("");
        api.get<Pagination<AiConversation>>(basePath)
            .then(async ({ data }) => {
                setConversations(data.data);
                const first = data.data[0];
                setActiveId(first?.id ?? null);
                if (first) await loadConversation(first.id);
                else setMessages([]);
            })
            .catch((reason) => setError(errorMessage(reason)))
            .finally(() => setLoading(false));
    }, [organizationId]);

    useEffect(() => {
        endRef.current?.scrollIntoView({ behavior: "smooth" });
    }, [messages, sending]);

    async function loadConversation(id: number) {
        const { data } = await api.get<{ data: AiConversation }>(
            `${basePath}/${id}`,
        );
        setMessages(data.data.messages ?? []);
    }

    async function selectConversation(id: number) {
        setActiveId(id);
        setError("");
        setLoading(true);
        try {
            await loadConversation(id);
        } catch (reason) {
            setError(errorMessage(reason));
        } finally {
            setLoading(false);
        }
    }

    async function createConversation() {
        const { data } = await api.post<{ data: AiConversation }>(basePath, {});
        setConversations((current) => [data.data, ...current]);
        setActiveId(data.data.id);
        setMessages([]);
        return data.data.id;
    }

    async function startConversation() {
        setError("");
        try {
            await createConversation();
        } catch (reason) {
            setError(errorMessage(reason));
        }
    }

    async function archiveConversation() {
        if (!activeId || sending) return;
        try {
            await api.delete(`${basePath}/${activeId}`);
            const remaining = conversations.filter(
                (conversation) => conversation.id !== activeId,
            );
            setConversations(remaining);
            const nextId = remaining[0]?.id ?? null;
            setActiveId(nextId);
            if (nextId) await loadConversation(nextId);
            else setMessages([]);
        } catch (reason) {
            setError(errorMessage(reason));
        }
    }

    async function sendMessage(content = input) {
        const cleanContent = content.trim();
        if (!cleanContent || sending) return;
        setSending(true);
        setError("");
        setInput("");

        const optimisticMessage: AiMessage = {
            id: -Date.now(),
            role: "user",
            content: cleanContent,
            status: "pending",
            metadata: null,
            created_at: new Date().toISOString(),
        };
        setMessages((current) => [...current, optimisticMessage]);

        try {
            const conversationId = activeId ?? (await createConversation());
            const { data } = await api.post<{ data: AiMessage }>(
                `${basePath}/${conversationId}/messages`,
                { content: cleanContent },
            );
            await loadConversation(conversationId);
            setConversations((current) =>
                current.map((conversation) =>
                    conversation.id === conversationId
                        ? {
                              ...conversation,
                              title: conversation.title || cleanContent,
                              last_message_at: data.data.created_at,
                          }
                        : conversation,
                ),
            );
        } catch (reason) {
            setMessages((current) =>
                current.filter((message) => message.id !== optimisticMessage.id),
            );
            setError(errorMessage(reason));
            setInput(cleanContent);
        } finally {
            setSending(false);
        }
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        void sendMessage();
    }

    if (loading && conversations.length === 0) return <Spinner />;

    return (
        <div className="assistant-page">
            <PageTitle
                eyebrow="SMARTBUSINESS AI"
                title="Asistente empresarial"
                description="Consulta indicadores reales de tu empresa con lenguaje natural."
                action={
                    <button className="button" onClick={() => void startConversation()}>
                        <Plus size={17} /> Nueva conversación
                    </button>
                }
            />
            <ErrorBox message={error} />

            <div className="assistant-layout">
                <aside className="assistant-conversations panel">
                    <div className="assistant-panel-title">
                        <div>
                            <strong>Conversaciones</strong>
                            <small>Solo visibles para ti</small>
                        </div>
                        <MessageSquare size={18} />
                    </div>
                    <div className="assistant-conversation-list">
                        {conversations.map((conversation) => (
                            <button
                                key={conversation.id}
                                className={conversation.id === activeId ? "active" : ""}
                                onClick={() => void selectConversation(conversation.id)}
                            >
                                <MessageSquare size={15} />
                                <span>
                                    <strong>
                                        {conversation.title || "Nueva conversación"}
                                    </strong>
                                    <small>
                                        {conversation.last_message_at
                                            ? new Intl.DateTimeFormat("es-CO", {
                                                  dateStyle: "medium",
                                              }).format(
                                                  new Date(
                                                      conversation.last_message_at,
                                                  ),
                                              )
                                            : "Sin mensajes"}
                                    </small>
                                </span>
                            </button>
                        ))}
                    </div>
                </aside>

                <section className="assistant-chat panel">
                    <div className="assistant-chat-header">
                        <div className="assistant-bot-mark">
                            <Sparkles size={19} />
                        </div>
                        <div>
                            <strong>SmartBusiness Assistant</strong>
                            <small>Consulta segura · Solo lectura</small>
                        </div>
                        {activeId && (
                            <button
                                className="icon-button"
                                aria-label="Archivar conversación"
                                title="Archivar conversación"
                                onClick={() => void archiveConversation()}
                            >
                                <Archive size={18} />
                            </button>
                        )}
                    </div>

                    <div className="assistant-messages" aria-live="polite">
                        {messages.length === 0 ? (
                            <div className="assistant-welcome">
                                <div className="assistant-welcome-icon">
                                    <Bot size={30} />
                                </div>
                                <h2>¿Qué quieres saber de tu negocio?</h2>
                                <p>
                                    El asistente consultará únicamente los indicadores
                                    autorizados de {membership?.organization.name}.
                                </p>
                                <div className="assistant-suggestions">
                                    {suggestions.map((suggestion) => (
                                        <button
                                            key={suggestion}
                                            onClick={() => void sendMessage(suggestion)}
                                        >
                                            {suggestion}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        ) : (
                            messages.map((message) => (
                                <article
                                    key={message.id}
                                    className={`assistant-message ${message.role}`}
                                >
                                    <span className="assistant-message-avatar">
                                        {message.role === "assistant" ? (
                                            <Bot size={17} />
                                        ) : (
                                            <UserRound size={17} />
                                        )}
                                    </span>
                                    <div>
                                        <small>
                                            {message.role === "assistant"
                                                ? "Asistente"
                                                : "Tú"}
                                        </small>
                                        <p>{message.content}</p>
                                        {message.metadata?.tool_calls?.length ? (
                                            <span className="assistant-tool-badge">
                                                <Sparkles size={12} /> Datos empresariales
                                                consultados
                                            </span>
                                        ) : null}
                                    </div>
                                </article>
                            ))
                        )}
                        {sending && (
                            <article className="assistant-message assistant">
                                <span className="assistant-message-avatar">
                                    <Bot size={17} />
                                </span>
                                <div>
                                    <small>Asistente</small>
                                    <p className="assistant-thinking">
                                        Analizando tus indicadores…
                                    </p>
                                </div>
                            </article>
                        )}
                        <div ref={endRef} />
                    </div>

                    <form className="assistant-composer" onSubmit={submit}>
                        <textarea
                            aria-label="Pregunta para el asistente"
                            placeholder="Pregunta por ventas, productos o stock…"
                            value={input}
                            maxLength={4000}
                            rows={2}
                            disabled={sending}
                            onChange={(event) => setInput(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === "Enter" && !event.shiftKey) {
                                    event.preventDefault();
                                    void sendMessage();
                                }
                            }}
                        />
                        <button
                            className="button"
                            type="submit"
                            disabled={!input.trim() || sending}
                        >
                            <Send size={17} /> Enviar
                        </button>
                    </form>
                    <p className="assistant-disclaimer">
                        La IA puede equivocarse. Verifica decisiones importantes con los
                        reportes del sistema.
                    </p>
                </section>
            </div>
        </div>
    );
}
