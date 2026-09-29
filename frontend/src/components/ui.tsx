import { useEffect, useId, useRef, type ReactNode } from "react";
import {
    AlertCircle,
    ChevronLeft,
    ChevronRight,
    Inbox,
    LoaderCircle,
    X,
} from "lucide-react";

export function Spinner() {
    return (
        <div className="loading" role="status">
            <LoaderCircle className="spin" size={23} />
            <span>Cargando información…</span>
        </div>
    );
}
export function ErrorBox({ message }: { message: string }) {
    return message ? (
        <div className="error-box" role="alert">
            <AlertCircle size={18} />
            <span>{message}</span>
        </div>
    ) : null;
}
export function Empty({
    title = "Todavía no hay registros",
    description = "Los registros que crees aparecerán aquí.",
}: {
    title?: string;
    description?: string;
}) {
    return (
        <div className="empty">
            <Inbox size={32} />
            <h3>{title}</h3>
            <p>{description}</p>
        </div>
    );
}
export function Badge({
    children,
    tone = "neutral",
}: {
    children: ReactNode;
    tone?: "green" | "orange" | "neutral" | "red";
}) {
    return <span className={`badge ${tone}`}>{children}</span>;
}
export function PageTitle({
    eyebrow,
    title,
    description,
    action,
}: {
    eyebrow: string;
    title: string;
    description: string;
    action?: ReactNode;
}) {
    return (
        <div className="page-title">
            <div>
                <p className="eyebrow">{eyebrow}</p>
                <h1>{title}</h1>
                <p>{description}</p>
            </div>
            {action}
        </div>
    );
}
export function Pagination({
    page,
    last,
    total,
    onChange,
}: {
    page: number;
    last: number;
    total: number;
    onChange: (page: number) => void;
}) {
    return (
        <div className="pagination">
            <span>
                {total} registros · Página {page} de {last}
            </span>
            <div>
                <button
                    className="icon-button"
                    aria-label="Página anterior"
                    disabled={page <= 1}
                    onClick={() => onChange(page - 1)}
                >
                    <ChevronLeft size={18} />
                </button>
                <button
                    className="icon-button"
                    aria-label="Página siguiente"
                    disabled={page >= last}
                    onClick={() => onChange(page + 1)}
                >
                    <ChevronRight size={18} />
                </button>
            </div>
        </div>
    );
}
export function Modal({
    title,
    children,
    onClose,
    wide = false,
}: {
    title: string;
    children: ReactNode;
    onClose: () => void;
    wide?: boolean;
}) {
    const ref = useRef<HTMLDivElement>(null);
    const titleId = useId();
    const closeRef = useRef(onClose);
    closeRef.current = onClose;
    useEffect(() => {
        const previous = document.activeElement as HTMLElement;
        const savedOverflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";
        ref.current
            ?.querySelector<HTMLElement>("input, button, select, textarea")
            ?.focus();
        function key(event: KeyboardEvent) {
            if (event.key === "Escape") closeRef.current();
            if (event.key === "Tab") {
                const elements = [
                    ...(ref.current?.querySelectorAll<HTMLElement>(
                        "button:not(:disabled), input, select, textarea, a[href]",
                    ) ?? []),
                ];
                const first = elements[0];
                const last = elements[elements.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last?.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first?.focus();
                }
            }
        }
        document.addEventListener("keydown", key);
        return () => {
            document.body.style.overflow = savedOverflow;
            document.removeEventListener("keydown", key);
            previous?.focus();
        };
    }, []);
    return (
        <div
            className="modal-backdrop"
            onMouseDown={(event) => {
                if (event.target === event.currentTarget) onClose();
            }}
        >
            <div
                ref={ref}
                role="dialog"
                aria-modal="true"
                aria-labelledby={titleId}
                className={`modal ${wide ? "wide" : ""}`}
            >
                <div className="modal-heading">
                    <h2 id={titleId}>{title}</h2>
                    <button
                        className="icon-button"
                        aria-label="Cerrar diálogo"
                        onClick={onClose}
                    >
                        <X size={20} />
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}
