import { EmbedFrame } from "@/components/EmbedFrame";

// Skeleton page wrappers for the Assembly-style spec. Most surfaces are
// placeholders that drill into either the dashboard plugin REST or the
// HighLevel SSO embed.

const Card = ({ title, children }: { title: string; children: React.ReactNode }) => (
    <div>
        <h1 className="text-xl font-semibold mb-4">{title}</h1>
        <div className="oversee-card p-6 text-sm text-zinc-500">{children}</div>
    </div>
);

// ---------- Client surfaces (12 items) ----------

export const Tasks = () => (
    <Card title="Tasks">
        Your tasks across all projects. Filter by status, priority, due date.
    </Card>
);
export const Files = () => (
    <Card title="Files">
        Files attached to your projects appear here. Open any project to upload, drag-drop, and version files.
    </Card>
);
export const Forms = () => (
    <Card title="Forms">
        Onboarding and on-demand forms requested by your account manager.
    </Card>
);
export const Contracts = () => (
    <Card title="Contracts">
        Review and sign contracts. We never email you a contract — they live here, signable in-place.
    </Card>
);

export const Messages = () => <EmbedFrame surface="conversations" title="Messages" />;
export const Schedule = () => <EmbedFrame surface="calendar" title="Schedule a Call" />;
export const Reports = () => <EmbedFrame surface="reports" title="Performance Reports" />;
export const Reviews = () => <EmbedFrame surface="reputation" title="Reviews" />;

export const BrowseServices = () => (
    <Card title="Browse Services">
        <p>Browse Oversee services in the WooCommerce shop. Checkout and recurring billing run through your existing payment method.</p>
        <p className="mt-3">
            <a href="/shop/" className="oversee-btn-primary inline-block">Open the shop</a>
        </p>
    </Card>
);
export const Subscriptions = () => (
    <Card title="Subscriptions">
        <p>Manage your active subscriptions, change plans, or cancel.</p>
        <p className="mt-3"><a href="/my-account/subscriptions/" className="oversee-btn-primary inline-block">My subscriptions</a></p>
    </Card>
);
export const Billing = () => (
    <Card title="Billing">
        <p>Manage payment methods and download invoices.</p>
        <p className="mt-3 flex gap-2">
            <a href="/my-account/payment-methods/" className="oversee-btn-secondary inline-block">Payment methods</a>
            <a href="/my-account/orders/" className="oversee-btn-secondary inline-block">Invoices</a>
        </p>
    </Card>
);

// ---------- Admin surfaces (15 items) ----------

export const AdminToday = () => (
    <div>
        <h1 className="text-xl font-semibold mb-4">Today</h1>
        <div className="grid gap-4" style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}>
            {["Open tasks", "Active clients", "Renewals this week", "Unread messages"].map((label) => (
                <div key={label} className="oversee-card p-4">
                    <div className="text-xs uppercase tracking-wider text-zinc-500">{label}</div>
                    <div className="text-2xl font-semibold mt-2">—</div>
                </div>
            ))}
        </div>
    </div>
);
export const AdminClients = () => <Card title="Clients">List of clients. Master/detail view with profile, projects, billing, notes.</Card>;
export const AdminProjects = () => <Card title="Projects">All active projects across clients.</Card>;
export const AdminTasks = () => <Card title="Tasks">Tasks across all projects with filtering by client, project, assignee.</Card>;
export const AdminMessages = () => <Card title="Messages">Inbox of all client conversations (mirrored from HighLevel).</Card>;

export const AdminOrders = () => <Card title="Orders">Recent WooCommerce orders.</Card>;
export const AdminSubscriptions = () => <Card title="Subscriptions">Active and lapsed WooCommerce subscriptions.</Card>;
export const AdminServiceTemplates = () => <Card title="Service Templates">Reusable service definitions with default tasks, mapped to existing WC product SKUs.</Card>;
export const AdminServiceCatalog = () => <Card title="Service Catalog">SKU → service template mapping (uses existing WooCommerce products only — never creates new products).</Card>;
export const AdminPayments = () => <Card title="Payments">Custom payment links, refunds, and out-of-band charges.</Card>;

export const AdminForms = () => <Card title="Forms">Edit intake form templates and review responses.</Card>;
export const AdminContracts = () => <Card title="Contracts">Edit contract templates and track signed contracts.</Card>;
export const AdminFiles = () => <Card title="Files">All files across all clients with approval queue.</Card>;
export const AdminAutomations = () => <Card title="Automations">Workflow rules: when X happens, do Y.</Card>;

export const AdminTeam = () => <Card title="Team">Account managers, specialists, contractors. Assign clients/projects.</Card>;
export const AdminSettings = () => (
    <Card title="Settings">
        Tokens, brand assets, HighLevel SSO endpoint, AI provider key, Pusher/Bunny/Resend credentials.
    </Card>
);
