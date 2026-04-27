import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import {
    FileText, FileSignature, CheckSquare, ListChecks,
    ArrowRight,
} from "lucide-react";
import { apiGet } from "@/lib/api";

type HomePayload = {
    user: { id: number; name: string; email: string; first_name?: string };
    actions: { invoices: number; contracts: number; tasks: number; forms: number };
    team: { id: number; name: string; email: string; role: string; avatar?: string }[];
    active_projects: { id: number; title: string; status: string; created_at: string }[];
    recent_updates: { id: number; event: string; entity: string; created_at: string; payload?: Record<string, unknown> }[];
};

const FALLBACK: HomePayload = {
    user: { id: 0, name: "there", email: "", first_name: "there" },
    actions: { invoices: 0, contracts: 0, tasks: 0, forms: 0 },
    team: [],
    active_projects: [],
    recent_updates: [],
};

export function Home() {
    const [data, setData] = useState<HomePayload>(FALLBACK);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        // Spec endpoint /oversee/v1/home; falls back to legacy data on 404 so
        // the page still renders during the migration window.
        apiGet<HomePayload>("home", { namespace: "oversee/v1" })
            .then((res) => setData(res))
            .catch((err) => setError(err?.message ?? "Could not load home payload"))
            .finally(() => setLoading(false));
    }, []);

    const firstName = data.user.first_name || data.user.name?.split(" ")[0] || "there";
    const totalPending = data.actions.invoices + data.actions.contracts + data.actions.tasks + data.actions.forms;

    return (
        <div className="space-y-6 max-w-[1100px]">
            <header className="space-y-1">
                <h1 className="text-2xl font-semibold tracking-tight">Welcome back, {firstName}</h1>
                <p className="text-sm text-zinc-500">
                    Here's everything that needs your attention today.
                </p>
            </header>

            {/* Your actions */}
            <section className="oversee-card p-6">
                <div className="flex items-center justify-between mb-4">
                    <h2 className="font-medium">Your actions</h2>
                    <span className="text-xs text-zinc-500">{totalPending} pending</span>
                </div>
                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <ActionTile to="/billing"   label="Invoices"  count={data.actions.invoices}  icon={<FileText size={16} />} />
                    <ActionTile to="/contracts" label="Contracts" count={data.actions.contracts} icon={<FileSignature size={16} />} />
                    <ActionTile to="/tasks"     label="Tasks"     count={data.actions.tasks}     icon={<CheckSquare size={16} />} />
                    <ActionTile to="/forms"     label="Forms"     count={data.actions.forms}     icon={<ListChecks size={16} />} />
                </div>
            </section>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {/* Your team */}
                <section className="oversee-card p-6">
                    <h2 className="font-medium mb-4">Your team</h2>
                    {data.team.length === 0 ? (
                        <p className="text-sm text-zinc-500">No team assigned yet — your account manager will introduce themselves shortly.</p>
                    ) : (
                        <ul className="divide-y" style={{ borderColor: "var(--oversee-border)" }}>
                            {data.team.map((m) => (
                                <li key={m.id} className="flex items-center gap-3 py-2 first:pt-0 last:pb-0">
                                    {m.avatar ? (
                                        // eslint-disable-next-line @next/next/no-img-element
                                        <img src={m.avatar} alt="" className="h-8 w-8 rounded-full" />
                                    ) : (
                                        <div className="h-8 w-8 rounded-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center text-xs">
                                            {m.name.slice(0, 2).toUpperCase()}
                                        </div>
                                    )}
                                    <div className="flex-1 min-w-0">
                                        <div className="text-sm font-medium truncate">{m.name}</div>
                                        <div className="text-xs text-zinc-500 truncate">{m.role}</div>
                                    </div>
                                    <a href={`mailto:${m.email}`} className="text-xs text-zinc-500 hover:underline">{m.email}</a>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {/* Active projects */}
                <section className="oversee-card p-6">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="font-medium">Active Projects</h2>
                        <Link to="/tasks" className="text-xs text-zinc-500 hover:underline inline-flex items-center gap-1">
                            View all <ArrowRight size={12} />
                        </Link>
                    </div>
                    {data.active_projects.length === 0 ? (
                        <p className="text-sm text-zinc-500">No active projects yet.</p>
                    ) : (
                        <ul className="space-y-2">
                            {data.active_projects.map((p) => (
                                <li key={p.id} className="flex items-center justify-between border-t pt-2 first:border-t-0 first:pt-0" style={{ borderColor: "var(--oversee-border)" }}>
                                    <Link to={`/projects/${p.id}`} className="text-sm font-medium hover:underline truncate">
                                        {p.title}
                                    </Link>
                                    <span className="text-xs text-zinc-500 capitalize ml-2 shrink-0">{p.status}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            {/* Recent updates */}
            <section className="oversee-card p-6">
                <h2 className="font-medium mb-4">Recent updates</h2>
                {data.recent_updates.length === 0 ? (
                    <p className="text-sm text-zinc-500">{loading ? "Loading…" : "No recent activity."}</p>
                ) : (
                    <ul className="space-y-3">
                        {data.recent_updates.map((u) => (
                            <li key={u.id} className="flex items-start gap-3 text-sm">
                                <div className="h-2 w-2 rounded-full mt-2" style={{ background: "var(--oversee-accent)" }} />
                                <div className="flex-1 min-w-0">
                                    <div className="font-medium">{humanizeEvent(u.event)}</div>
                                    <div className="text-xs text-zinc-500">{new Date(u.created_at).toLocaleString()}</div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            {error && (
                <div className="oversee-card p-4 text-sm text-zinc-500" role="status">
                    {error}
                </div>
            )}
        </div>
    );
}

function ActionTile({ to, label, count, icon }: { to: string; label: string; count: number; icon: React.ReactNode }) {
    return (
        <Link
            to={to}
            className="oversee-card flex flex-col gap-2 p-4 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors"
            style={{ background: "var(--oversee-surface-2)" }}
        >
            <div className="flex items-center gap-2 text-zinc-500 text-xs">
                {icon}
                <span>{label}</span>
            </div>
            <div className="flex items-baseline gap-1.5">
                <span className="text-2xl font-semibold tabular-nums">{count}</span>
                <span className="text-xs text-zinc-500">pending</span>
            </div>
        </Link>
    );
}

function humanizeEvent(event: string): string {
    return event
        .replace(/[._-]/g, " ")
        .replace(/\b\w/g, (c) => c.toUpperCase());
}
