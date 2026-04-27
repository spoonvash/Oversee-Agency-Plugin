import { createContext, useCallback, useContext, useEffect, useState, ReactNode } from "react";

type Tone = "success" | "error" | "info";
type ToastItem = { id: number; tone: Tone; message: string };

type ToastContextValue = {
    push: (msg: string, tone?: Tone) => void;
    pushError: (err: unknown) => void;
};

const ToastContext = createContext<ToastContextValue | null>(null);

export function useToast() {
    const ctx = useContext(ToastContext);
    if (!ctx) {
        // Falls back to console so call sites stay safe even outside the provider.
        return {
            push: (m: string) => console.info("[toast]", m),
            pushError: (e: unknown) => console.error("[toast]", e),
        } satisfies ToastContextValue;
    }
    return ctx;
}

export function ToastProvider({ children }: { children: ReactNode }) {
    const [items, setItems] = useState<ToastItem[]>([]);

    const push = useCallback((message: string, tone: Tone = "info") => {
        const id = Date.now() + Math.random();
        setItems((cur) => [...cur, { id, tone, message }]);
        setTimeout(() => setItems((cur) => cur.filter((i) => i.id !== id)), 4000);
    }, []);

    const pushError = useCallback((err: unknown) => {
        const message = err instanceof Error ? err.message : String(err ?? "Something went wrong");
        push(message, "error");
    }, [push]);

    return (
        <ToastContext.Provider value={{ push, pushError }}>
            {children}
            <div
                aria-live="polite"
                className="fixed bottom-4 right-4 z-50 flex flex-col gap-2 max-w-sm"
            >
                {items.map((t) => (
                    <ToastBubble key={t.id} item={t} />
                ))}
            </div>
        </ToastContext.Provider>
    );
}

function ToastBubble({ item }: { item: ToastItem }) {
    const [visible, setVisible] = useState(false);
    useEffect(() => {
        const r = requestAnimationFrame(() => setVisible(true));
        return () => cancelAnimationFrame(r);
    }, []);
    const colors: Record<Tone, { bg: string; border: string; color: string }> = {
        success: { bg: "rgba(22, 163, 74, 0.95)", border: "rgba(22, 163, 74, 1)", color: "white" },
        error:   { bg: "rgba(220, 38, 38, 0.95)", border: "rgba(220, 38, 38, 1)", color: "white" },
        info:    { bg: "rgba(30, 41, 59, 0.95)",  border: "rgba(30, 41, 59, 1)",  color: "white" },
    };
    const c = colors[item.tone];
    return (
        <div
            role="alert"
            className="rounded-lg shadow-lg px-4 py-3 text-sm transition-all duration-200"
            style={{
                background: c.bg,
                border: `1px solid ${c.border}`,
                color: c.color,
                opacity: visible ? 1 : 0,
                transform: visible ? "translateY(0)" : "translateY(8px)",
            }}
        >
            {item.message}
        </div>
    );
}
