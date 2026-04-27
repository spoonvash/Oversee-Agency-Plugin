import { ReactNode } from "react";
import { AlertCircle, Info, CheckCircle2, Clock, Wrench } from "lucide-react";

// Shared empty/setup states for surfaces that depend on third-party
// integrations (HighLevel, AI provider, Pusher/Bunny/Resend, file
// uploads, etc). Render this instead of letting a button silently fail.

type Tone = "info" | "warning" | "success" | "pending" | "setup";

const TONE_CONFIG: Record<Tone, { icon: ReactNode; bg: string; border: string; iconColor: string }> = {
    info:    { icon: <Info size={18} />,        bg: "rgba(255, 130, 1, 0.06)", border: "rgba(255, 130, 1, 0.25)", iconColor: "var(--oversee-accent, #ff8201)" },
    warning: { icon: <AlertCircle size={18} />, bg: "rgba(234, 88, 12, 0.06)", border: "rgba(234, 88, 12, 0.25)", iconColor: "#ea580c" },
    success: { icon: <CheckCircle2 size={18} />,bg: "rgba(22, 163, 74, 0.06)", border: "rgba(22, 163, 74, 0.25)", iconColor: "#16a34a" },
    pending: { icon: <Clock size={18} />,       bg: "rgba(100, 116, 139, 0.06)", border: "rgba(100, 116, 139, 0.25)", iconColor: "#64748b" },
    setup:   { icon: <Wrench size={18} />,      bg: "rgba(79, 70, 229, 0.06)", border: "rgba(79, 70, 229, 0.25)", iconColor: "#4f46e5" },
};

export function ActionNotice({
    tone = "info",
    title,
    children,
    actions,
}: {
    tone?: Tone;
    title: string;
    children?: ReactNode;
    actions?: ReactNode;
}) {
    const cfg = TONE_CONFIG[tone];
    return (
        <div
            role="status"
            className="rounded-lg border p-4 flex gap-3"
            style={{ background: cfg.bg, borderColor: cfg.border }}
        >
            <div style={{ color: cfg.iconColor }} className="shrink-0 mt-0.5">{cfg.icon}</div>
            <div className="flex-1 min-w-0">
                <div className="text-sm font-semibold">{title}</div>
                {children ? <div className="text-sm text-zinc-600 mt-1">{children}</div> : null}
                {actions ? <div className="mt-3 flex flex-wrap gap-2">{actions}</div> : null}
            </div>
        </div>
    );
}

// SetupRequired is the variant we render when a button or surface needs an
// integration to be configured before it can be used. It frames the missing
// piece as a setup task rather than a generic error.
export function SetupRequired({
    title,
    integration,
    docsUrl,
    children,
}: {
    title: string;
    integration: string;
    docsUrl?: string;
    children?: ReactNode;
}) {
    return (
        <ActionNotice
            tone="setup"
            title={title}
            actions={
                docsUrl ? (
                    <a className="oversee-btn-secondary inline-block text-xs" href={docsUrl} target="_blank" rel="noreferrer">
                        Setup guide
                    </a>
                ) : null
            }
        >
            <p>
                {children ?? (
                    <>
                        This surface relies on the <strong>{integration}</strong> integration. An admin
                        needs to connect it under <em>Oversee Dashboard → Settings</em> before this is available.
                    </>
                )}
            </p>
        </ActionNotice>
    );
}

// Inline empty state used inside cards/lists. Same pattern as ActionNotice
// but lighter weight, suitable for "no data yet" cases that aren't blocking.
export function EmptyState({
    title,
    description,
    actions,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
}) {
    return (
        <div className="text-center py-10 px-6">
            <div className="text-sm font-semibold text-zinc-700 dark:text-zinc-200">{title}</div>
            {description ? <p className="text-xs text-zinc-500 mt-1 max-w-md mx-auto">{description}</p> : null}
            {actions ? <div className="mt-4 flex flex-wrap gap-2 justify-center">{actions}</div> : null}
        </div>
    );
}

// Skeleton block — shown during loading instead of leaving the page blank.
export function Skeleton({ className = "h-4 w-full", lines = 1 }: { className?: string; lines?: number }) {
    return (
        <div className="space-y-2">
            {Array.from({ length: lines }).map((_, i) => (
                <div
                    key={i}
                    className={`rounded animate-pulse ${className}`}
                    style={{ background: "rgba(100, 116, 139, 0.12)" }}
                />
            ))}
        </div>
    );
}
