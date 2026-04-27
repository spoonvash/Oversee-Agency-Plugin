import { useEffect, useState } from "react";
import { apiGet, ApiError } from "@/lib/api";

// Generic HighLevel iframe wrapper. Calls /highlevel/embed/<surface>, handles
// the unconfigured / no-contact / not-yet-set-up cases with a friendly empty
// state, and renders the iframe once a magic-link URL comes back.
type EmbedConfig = { surface: string; embed_url: string; expires_at: string | null };

export function EmbedFrame({ surface, title }: { surface: string; title: string }) {
    const [state, setState] = useState<
        | { kind: "loading" }
        | { kind: "ready"; cfg: EmbedConfig }
        | { kind: "error"; code: string; message: string }
    >({ kind: "loading" });

    useEffect(() => {
        let active = true;
        apiGet<EmbedConfig>(`highlevel/embed/${surface}`)
            .then((cfg) => {
                if (!active) return;
                setState({ kind: "ready", cfg });
            })
            .catch((err: unknown) => {
                if (!active) return;
                const code = err instanceof ApiError ? `http_${err.status}` : "unknown";
                const message = err instanceof Error ? err.message : "Could not load embed.";
                setState({ kind: "error", code, message });
            });
        return () => {
            active = false;
        };
    }, [surface]);

    if (state.kind === "loading") {
        return (
            <div className="oversee-card p-8 text-center text-zinc-500">
                Loading {title}…
            </div>
        );
    }
    if (state.kind === "error") {
        return (
            <div className="oversee-card p-6">
                <h2 className="font-semibold text-base mb-1">{title} isn't connected yet</h2>
                <p className="text-sm text-zinc-500 mb-3">
                    Your account manager needs to link your HighLevel contact before this section is available.
                </p>
                <details className="text-xs text-zinc-400">
                    <summary>Technical detail</summary>
                    <pre className="mt-2 whitespace-pre-wrap">{state.message}</pre>
                </details>
            </div>
        );
    }
    return (
        <div className="oversee-card overflow-hidden" style={{ height: "calc(100vh - 140px)" }}>
            <iframe
                src={state.cfg.embed_url}
                title={title}
                style={{ width: "100%", height: "100%", border: 0 }}
                allow="clipboard-read; clipboard-write; microphone; camera"
            />
        </div>
    );
}
