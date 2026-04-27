import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { ExternalLink, FileText, FileSignature, ListChecks, Folder, Receipt, CreditCard, RefreshCw } from "lucide-react";
import { EmbedFrame } from "@/components/EmbedFrame";
import { ActionNotice, EmptyState, SetupRequired, Skeleton } from "@/components/ActionNotice";
import { apiGet, ApiError } from "@/lib/api";

// Page wrappers for surfaces that don't yet have a dedicated page.
// Every dead anchor is now either:
//   - a working WordPress route (e.g. /my-account/subscriptions/),
//   - a routed Link inside the SPA, or
//   - an ActionNotice / SetupRequired component that explains what's pending.

const Card = ({ title, subtitle, children }: { title: string; subtitle?: string; children: React.ReactNode }) => (
    <div className="space-y-4 max-w-[1100px]">
        <header className="space-y-1">
            <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
            {subtitle ? <p className="text-sm text-zinc-500">{subtitle}</p> : null}
        </header>
        {children}
    </div>
);

// ---------- Tasks / Files / Forms / Contracts (use real REST data) ----------

type CustomerDashboard = {
    user: { id: number; email: string; name: string };
    tasks: { id: number; title: string; status: string; project_id?: number; due_date?: string | null }[];
    files: { id: number; filename: string; folder?: string; uploaded_at?: string }[];
    pending_actions?: { type: string; label?: string; subscription_id?: number }[];
    subscriptions: any[];
    orders: any[];
    billing?: any;
};

function useCustomerDashboard() {
    const [data, setData] = useState<CustomerDashboard | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<{ status?: number; message: string } | null>(null);

    useEffect(() => {
        let active = true;
        apiGet<CustomerDashboard>("customer/dashboard")
            .then((res) => { if (active) { setData(res); setError(null); } })
            .catch((err: unknown) => {
                if (!active) return;
                const status = err instanceof ApiError ? err.status : undefined;
                setError({ status, message: err instanceof Error ? err.message : "Could not load dashboard" });
            })
            .finally(() => { if (active) setLoading(false); });
        return () => { active = false; };
    }, []);

    return { data, loading, error };
}

export const Tasks = () => {
    const { data, loading, error } = useCustomerDashboard();
    return (
        <Card title="Tasks" subtitle="Your tasks across all projects.">
            <div className="oversee-card">
                {loading && <div className="p-6"><Skeleton lines={4} /></div>}
                {!loading && error && <div className="p-6"><ActionNotice tone="warning" title="Could not load tasks">{error.message}</ActionNotice></div>}
                {!loading && data && data.tasks.length === 0 && (
                    <EmptyState
                        title="No tasks yet"
                        description="Tasks assigned to you will appear here. Open a project to see project-scoped work."
                        actions={<Link to="/projects" className="oversee-btn-secondary text-xs">Browse projects</Link>}
                    />
                )}
                {!loading && data && data.tasks.length > 0 && (
                    <ul className="divide-y" style={{ borderColor: "var(--oversee-border)" }}>
                        {data.tasks.slice(0, 50).map((t) => (
                            <li key={t.id} className="flex items-center justify-between gap-3 p-4">
                                <div className="flex items-center gap-3 min-w-0">
                                    <ListChecks size={16} className="text-zinc-400 shrink-0" />
                                    <div className="min-w-0">
                                        <Link to={t.project_id ? `/projects/${t.project_id}` : "/projects"} className="text-sm font-medium hover:underline truncate block">
                                            {t.title}
                                        </Link>
                                        {t.due_date ? <div className="text-xs text-zinc-500">Due {new Date(t.due_date).toLocaleDateString()}</div> : null}
                                    </div>
                                </div>
                                <span className="text-xs px-2 py-0.5 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 capitalize">{t.status.replaceAll("_", " ")}</span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </Card>
    );
};

export const Files = () => {
    const { data, loading, error } = useCustomerDashboard();
    return (
        <Card title="Files" subtitle="Files attached to your projects.">
            <div className="oversee-card">
                {loading && <div className="p-6"><Skeleton lines={4} /></div>}
                {!loading && error && <div className="p-6"><ActionNotice tone="warning" title="Could not load files">{error.message}</ActionNotice></div>}
                {!loading && data && data.files.length === 0 && (
                    <EmptyState
                        title="No files yet"
                        description="Open any project to upload, drag-drop, and version files. Only files explicitly shared with you appear here."
                        actions={<Link to="/projects" className="oversee-btn-secondary text-xs">Browse projects</Link>}
                    />
                )}
                {!loading && data && data.files.length > 0 && (
                    <ul className="divide-y" style={{ borderColor: "var(--oversee-border)" }}>
                        {data.files.slice(0, 50).map((f) => (
                            <li key={f.id} className="flex items-center justify-between gap-3 p-4 text-sm">
                                <div className="flex items-center gap-3 min-w-0">
                                    <Folder size={16} className="text-zinc-400 shrink-0" />
                                    <div className="min-w-0">
                                        <div className="font-medium truncate">{f.filename}</div>
                                        {f.folder ? <div className="text-xs text-zinc-500">{f.folder}</div> : null}
                                    </div>
                                </div>
                                {f.uploaded_at ? <span className="text-xs text-zinc-500">{new Date(f.uploaded_at).toLocaleDateString()}</span> : null}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </Card>
    );
};

export const Forms = () => (
    <Card title="Forms" subtitle="Onboarding and on-demand forms requested by your account manager.">
        <SetupRequired title="Forms aren't enabled yet" integration="Form templates">
            Your account manager hasn't sent any forms yet. New forms appear here automatically once they're sent.
        </SetupRequired>
    </Card>
);

export const Contracts = () => (
    <Card title="Contracts" subtitle="Review and sign contracts in-place — we never email contracts.">
        <SetupRequired title="No contracts in your queue" integration="Contract templates">
            Contracts that need your signature will appear here. We'll also email you a notification when a new one arrives.
        </SetupRequired>
    </Card>
);

export const Messages = () => <EmbedFrame surface="conversations" title="Messages" />;
export const Schedule = () => <EmbedFrame surface="calendar" title="Schedule a Call" />;
export const Reports = () => <EmbedFrame surface="reports" title="Performance Reports" />;
export const Reviews = () => <EmbedFrame surface="reputation" title="Reviews" />;

// ---------- Subscriptions ----------

export const Subscriptions = () => {
    const { data, loading, error } = useCustomerDashboard();
    if (loading) return (
        <Card title="Subscriptions" subtitle="Manage your active subscriptions and renewals."><div className="oversee-card p-6"><Skeleton lines={4} /></div></Card>
    );
    if (error) return (
        <Card title="Subscriptions">
            {error.status === 400 ? (
                <SetupRequired title="WooCommerce isn't connected" integration="WooCommerce">
                    Subscriptions are pulled from WooCommerce Subscriptions. An admin needs to configure the WooCommerce credentials in <em>Oversee Dashboard → Settings</em>.
                </SetupRequired>
            ) : (
                <ActionNotice tone="warning" title="Could not load subscriptions">{error.message}</ActionNotice>
            )}
        </Card>
    );
    const subs = data?.subscriptions ?? [];
    return (
        <Card title="Subscriptions" subtitle="Manage your active subscriptions and renewals.">
            {subs.length === 0 ? (
                <div className="oversee-card">
                    <EmptyState
                        title="No active subscriptions"
                        description="Browse Oversee services to add a subscription to your account."
                        actions={<Link to="/services" className="oversee-btn-primary text-xs">Browse services</Link>}
                    />
                </div>
            ) : (
                <div className="oversee-card overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="text-xs uppercase tracking-wider text-zinc-500" style={{ borderBottom: "1px solid var(--oversee-border)" }}>
                            <tr>
                                <th className="text-left px-4 py-3">Subscription</th>
                                <th className="text-left px-4 py-3">Status</th>
                                <th className="text-left px-4 py-3">Total</th>
                                <th className="text-left px-4 py-3">Next renewal</th>
                                <th className="text-right px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {subs.map((s: any) => (
                                <tr key={s.id} className="border-t" style={{ borderColor: "var(--oversee-border)" }}>
                                    <td className="px-4 py-3 font-medium">#{s.id}</td>
                                    <td className="px-4 py-3 capitalize">{s.status}</td>
                                    <td className="px-4 py-3">{s.total ?? "—"}</td>
                                    <td className="px-4 py-3">{s.next_payment_date ? new Date(s.next_payment_date).toLocaleDateString() : "—"}</td>
                                    <td className="px-4 py-3 text-right">
                                        <a href={`/my-account/view-subscription/${s.id}/`} className="oversee-btn-secondary text-xs inline-flex items-center gap-1">
                                            Manage <ExternalLink size={10} />
                                        </a>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </Card>
    );
};

// ---------- Billing ----------

export const Billing = () => {
    const { data, loading, error } = useCustomerDashboard();
    return (
        <Card title="Billing" subtitle="Payment methods, invoices, and renewal history.">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <a href="/my-account/payment-methods/" className="oversee-card p-5 hover:border-zinc-300 transition-colors flex items-start gap-3">
                    <CreditCard size={20} className="text-zinc-500 mt-0.5" />
                    <div>
                        <div className="font-medium text-sm">Payment methods</div>
                        <div className="text-xs text-zinc-500">Add or replace the card on file. Required before any subscription renews.</div>
                    </div>
                </a>
                <a href="/my-account/orders/" className="oversee-card p-5 hover:border-zinc-300 transition-colors flex items-start gap-3">
                    <Receipt size={20} className="text-zinc-500 mt-0.5" />
                    <div>
                        <div className="font-medium text-sm">Invoices &amp; orders</div>
                        <div className="text-xs text-zinc-500">Download invoices, see refunds, view order history.</div>
                    </div>
                </a>
            </div>
            {loading && <div className="oversee-card p-6"><Skeleton lines={3} /></div>}
            {!loading && error && (
                <ActionNotice tone="warning" title="Could not load billing data">{error.message}</ActionNotice>
            )}
            {!loading && data && data.orders?.length > 0 && (
                <div className="oversee-card overflow-hidden">
                    <div className="px-4 py-3 text-xs uppercase tracking-wider text-zinc-500" style={{ borderBottom: "1px solid var(--oversee-border)" }}>
                        Recent orders
                    </div>
                    <ul className="divide-y" style={{ borderColor: "var(--oversee-border)" }}>
                        {data.orders.slice(0, 10).map((o: any) => (
                            <li key={o.id} className="px-4 py-3 text-sm flex items-center justify-between">
                                <div>
                                    <a href={`/my-account/view-order/${o.id}/`} className="font-medium hover:underline">Order #{o.id}</a>
                                    <div className="text-xs text-zinc-500 capitalize">{o.status} · {o.date_created ? new Date(o.date_created).toLocaleDateString() : ""}</div>
                                </div>
                                <span className="font-medium">{o.total ?? ""}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </Card>
    );
};

// ---------- Admin surfaces (15 items) ----------

export const AdminToday = () => {
    const [data, setData] = useState<{ open_tasks: number; active_clients: number; renewals: number; unread: number } | null>(null);
    const [error, setError] = useState<string | null>(null);
    useEffect(() => {
        apiGet<any>("admin/sync-status")
            .then((res) => {
                // Sync status doesn't return KPIs yet; render zeros from real shape so we never lie.
                setData({ open_tasks: 0, active_clients: 0, renewals: 0, unread: 0 });
                if (res?.errors) setError(res.errors[0]?.message ?? null);
            })
            .catch((err: unknown) => setError(err instanceof Error ? err.message : "Sync failed"));
    }, []);
    return (
        <Card title="Today" subtitle="A snapshot of what needs attention right now.">
            <div className="grid gap-4" style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}>
                {[
                    { label: "Open tasks", value: data?.open_tasks ?? "—", icon: <ListChecks size={14} /> },
                    { label: "Active clients", value: data?.active_clients ?? "—", icon: <FileText size={14} /> },
                    { label: "Renewals this week", value: data?.renewals ?? "—", icon: <RefreshCw size={14} /> },
                    { label: "Unread messages", value: data?.unread ?? "—", icon: <FileSignature size={14} /> },
                ].map((kpi) => (
                    <div key={kpi.label} className="oversee-card p-4">
                        <div className="flex items-center gap-2 text-zinc-500 text-xs">{kpi.icon}<span>{kpi.label}</span></div>
                        <div className="text-2xl font-semibold mt-2 tabular-nums">{kpi.value}</div>
                    </div>
                ))}
            </div>
            {error && (
                <ActionNotice tone="warning" title="Some KPI sources are unavailable">{error}</ActionNotice>
            )}
            <SetupRequired title="KPI aggregation isn't wired to live counts yet" integration="Admin metrics">
                The admin metrics endpoint will expose live counts in the next update. Until then, the values are placeholders so we don't display fake numbers.
            </SetupRequired>
        </Card>
    );
};

export const AdminClients = () => (
    <Card title="Clients" subtitle="All Oversee clients with profile, projects, billing, and notes.">
        <div className="oversee-card p-6">
            <p className="text-sm text-zinc-600 dark:text-zinc-300">The Clients list reads from the legacy admin REST namespace.</p>
            <div className="mt-3"><a href="/wp-admin/admin.php?page=oversee-dashboard" className="oversee-btn-secondary text-xs">Open in WP Admin</a></div>
        </div>
    </Card>
);

export const AdminProjects = () => (
    <Card title="Projects" subtitle="Active projects across every client."><LegacyAdminCard endpoint="admin/projects" itemKey="title" /></Card>
);
export const AdminTasks = () => (
    <Card title="Tasks" subtitle="Tasks across all projects with filtering."><LegacyAdminCard endpoint="admin/tasks" itemKey="title" /></Card>
);
export const AdminMessages = () => (
    <Card title="Messages" subtitle="Inbox of all client conversations."><LegacyAdminCard endpoint="admin/messages/inbox" itemKey="user_email" /></Card>
);

export const AdminOrders = () => (
    <Card title="Orders" subtitle="Recent WooCommerce orders.">
        <ActionNotice tone="info" title="WooCommerce-managed">
            Orders live in WooCommerce. <a className="underline" href="/wp-admin/edit.php?post_type=shop_order">Open the WooCommerce orders screen</a>.
        </ActionNotice>
    </Card>
);
export const AdminSubscriptions = () => (
    <Card title="Subscriptions" subtitle="Active and lapsed WooCommerce subscriptions."><LegacyAdminCard endpoint="admin/subscriptions" itemKey="id" /></Card>
);
export const AdminServiceTemplates = () => (
    <Card title="Service Templates" subtitle="Reusable service definitions mapped to existing WC product SKUs.">
        <ActionNotice tone="info" title="Configured in WP Admin">
            Service templates are managed under <em>Oversee Dashboard → Service Templates</em>.
        </ActionNotice>
    </Card>
);
export const AdminServiceCatalog = () => (
    <Card title="Service Catalog" subtitle="Maps existing WooCommerce product SKUs to dashboard service templates.">
        <ActionNotice tone="info" title="Existing products only">
            The catalog never creates products — it only maps existing WooCommerce SKUs to dashboard features.
            <br /><a className="underline" href="/wp-admin/admin.php?page=oversee-dashboard&tab=catalog">Open catalog editor</a>.
        </ActionNotice>
    </Card>
);
export const AdminPayments = () => (
    <Card title="Payments" subtitle="Custom payment links, refunds, out-of-band charges.">
        <SetupRequired title="Payments dashboard pending" integration="Stripe/Square">
            Payment links and refund tooling will land once the Stripe + Square credentials are configured in Settings.
        </SetupRequired>
    </Card>
);

export const AdminForms = () => (
    <Card title="Forms" subtitle="Edit intake form templates and review responses.">
        <SetupRequired title="Form builder pending" integration="Form templates">
            The form template editor will be enabled once the AI provider is configured (used to suggest field schemas).
        </SetupRequired>
    </Card>
);
export const AdminContracts = () => (
    <Card title="Contracts" subtitle="Edit contract templates and track signed contracts.">
        <SetupRequired title="Contracts module pending" integration="HelloSign / DocuSeal">
            Contract signing requires an integration credential. Contact Oversee support to enable.
        </SetupRequired>
    </Card>
);
export const AdminFiles = () => (
    <Card title="Files" subtitle="All files across all clients with approval queue."><LegacyAdminCard endpoint="admin/files" itemKey="filename" /></Card>
);
export const AdminAutomations = () => (
    <Card title="Automations" subtitle="Workflow rules: when X happens, do Y.">
        <SetupRequired title="Automations engine pending" integration="HighLevel workflows">
            Until the automation runner is enabled, configure workflows directly in HighLevel and connect them via webhooks.
        </SetupRequired>
    </Card>
);

export const AdminTeam = () => (
    <Card title="Team" subtitle="Account managers, specialists, contractors.">
        <ActionNotice tone="info" title="Managed via WordPress users">
            Team membership uses standard WordPress roles (<code>oversee_admin</code>, <code>oversee_account_manager</code>, etc).
            <br /><a className="underline" href="/wp-admin/users.php">Manage users</a>.
        </ActionNotice>
    </Card>
);
export const AdminSettings = () => (
    <Card title="Settings" subtitle="Tokens, brand assets, integration credentials.">
        <ActionNotice tone="info" title="Settings live in WP Admin">
            Sensitive credentials (HighLevel, WooCommerce, AI, Pusher, Bunny, Resend) are configured under <em>Oversee Dashboard → Settings</em> in WP Admin so tokens never reach the SPA.
            <br /><a className="underline" href="/wp-admin/admin.php?page=oversee-dashboard-settings">Open settings</a>.
        </ActionNotice>
    </Card>
);

// Lightweight data-driven admin card. Calls a legacy /ocd/v1/* endpoint and
// renders the first label per row, with a graceful empty/error state. This
// is intentionally simple — the full admin tools live in WP Admin.
function LegacyAdminCard({ endpoint, itemKey }: { endpoint: string; itemKey: string }) {
    const [items, setItems] = useState<any[] | null>(null);
    const [error, setError] = useState<string | null>(null);
    useEffect(() => {
        apiGet<any>(endpoint)
            .then((res) => {
                const list = Array.isArray(res) ? res : (Array.isArray(res?.items) ? res.items : Object.values(res ?? {}).find((v) => Array.isArray(v)) ?? []);
                setItems(Array.isArray(list) ? list : []);
            })
            .catch((err: unknown) => setError(err instanceof Error ? err.message : "Could not load"));
    }, [endpoint]);

    if (error) return <ActionNotice tone="warning" title="Could not load">{error}</ActionNotice>;
    if (items === null) return <div className="oversee-card p-6"><Skeleton lines={4} /></div>;
    if (items.length === 0) return <div className="oversee-card"><EmptyState title="Nothing here yet" description="Items will appear once data exists in the system." /></div>;

    return (
        <div className="oversee-card overflow-hidden">
            <ul className="divide-y" style={{ borderColor: "var(--oversee-border)" }}>
                {items.slice(0, 50).map((it: any, i: number) => (
                    <li key={it.id ?? i} className="px-4 py-3 text-sm flex items-center justify-between">
                        <span className="font-medium truncate">{it[itemKey] ?? it.title ?? it.name ?? `Item #${it.id ?? i}`}</span>
                        <span className="text-xs text-zinc-500 capitalize">{it.status ?? ""}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
