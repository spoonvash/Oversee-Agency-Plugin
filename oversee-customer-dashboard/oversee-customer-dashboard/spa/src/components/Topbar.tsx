import { useEffect, useState } from "react";
import { Bell, Moon, Search, Sun } from "lucide-react";
import { useDarkMode } from "@/lib/theme";

// Top bar shows: breadcrumb (left), Cmd+K command-palette trigger (center),
// notifications bell + dark mode + user menu (right).
//
// The command palette itself is intentionally a no-op stub here — it opens a
// modal and shows a placeholder; integrators can wire it to fuzzy search /
// REST queries later. The keybinding plumbing is in place so the rest of the
// SPA already routes Cmd/Ctrl+K to the same trigger.

export function Topbar({ breadcrumb }: { breadcrumb: string }) {
    const { dark, toggle } = useDarkMode();
    const [cmdkOpen, setCmdkOpen] = useState(false);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") {
                e.preventDefault();
                setCmdkOpen(true);
            } else if (e.key === "Escape") {
                setCmdkOpen(false);
            }
        };
        window.addEventListener("keydown", onKey);
        return () => window.removeEventListener("keydown", onKey);
    }, []);

    return (
        <>
            <header
                className="oversee-card flex items-center gap-4 px-4"
                style={{ height: 56, margin: "16px 16px 0 0", borderRadius: 12 }}
            >
                <div className="text-sm text-zinc-600 dark:text-zinc-300 truncate">
                    {breadcrumb}
                </div>
                <button
                    type="button"
                    onClick={() => setCmdkOpen(true)}
                    className="ml-auto flex items-center gap-2 px-3 py-1.5 text-sm border rounded-md text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-200"
                    style={{ borderColor: "var(--oversee-border)" }}
                    aria-label="Open command palette"
                >
                    <Search size={14} />
                    <span>Search…</span>
                    <kbd className="text-xs px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800">⌘K</kbd>
                </button>
                <button
                    type="button"
                    aria-label="Notifications"
                    className="p-2 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-600 dark:text-zinc-300"
                >
                    <Bell size={16} />
                </button>
                <button
                    type="button"
                    onClick={toggle}
                    aria-label={dark ? "Switch to light mode" : "Switch to dark mode"}
                    className="p-2 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-600 dark:text-zinc-300"
                >
                    {dark ? <Sun size={16} /> : <Moon size={16} />}
                </button>
            </header>
            {cmdkOpen && <CommandPalette onClose={() => setCmdkOpen(false)} />}
        </>
    );
}

function CommandPalette({ onClose }: { onClose: () => void }) {
    return (
        <div
            role="dialog"
            aria-modal="true"
            className="fixed inset-0 z-50 flex items-start justify-center"
            style={{ background: "rgba(0,0,0,0.45)", paddingTop: "12vh" }}
            onClick={onClose}
        >
            <div
                className="oversee-card w-full max-w-xl"
                style={{ padding: 12 }}
                onClick={(e) => e.stopPropagation()}
            >
                <input
                    autoFocus
                    placeholder="Search boards, items, files…"
                    className="oversee-input"
                />
                <div className="mt-3 text-sm text-zinc-500 px-2 py-6 text-center">
                    Type to search. Esc to close.
                </div>
            </div>
        </div>
    );
}
