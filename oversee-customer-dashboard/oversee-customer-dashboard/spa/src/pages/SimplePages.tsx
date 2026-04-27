import { EmbedFrame } from "@/components/EmbedFrame";

// Simple page wrappers around embeds and read-only WC links. Each page has a
// skeleton/empty state and never blanks the screen.

export const Messages = () => <EmbedFrame surface="conversations" title="Messages" />;
export const ScheduleCall = () => <EmbedFrame surface="calendar" title="Schedule a call" />;
export const PerformanceReports = () => <EmbedFrame surface="reports" title="Performance reports" />;
export const Documents = () => <EmbedFrame surface="documents" title="Documents" />;
export const Reviews = () => <EmbedFrame surface="reputation" title="Reviews" />;

export const Files = () => (
    <div>
        <h1 className="text-xl font-semibold mb-4">Files</h1>
        <div className="oversee-card p-6 text-sm text-zinc-500">
            Files attached to your projects appear here. Open any project to upload, drag-drop, and version files.
        </div>
    </div>
);

export const Knowledge = () => (
    <div>
        <h1 className="text-xl font-semibold mb-4">Knowledge base</h1>
        <div className="oversee-card p-6 text-sm text-zinc-500">
            How-tos, onboarding videos, and FAQ articles will appear here.
        </div>
    </div>
);

export const Services = () => (
    <div>
        <h1 className="text-xl font-semibold mb-4">Services</h1>
        <div className="oversee-card p-6">
            <p className="text-sm text-zinc-500">
                Browse Oversee services in the WooCommerce shop. Checkout and recurring billing run through your existing payment method.
            </p>
            <p className="mt-3">
                <a href="/shop/" className="oversee-btn-primary inline-block">Open the shop</a>
            </p>
        </div>
    </div>
);

export const Billing = () => (
    <div>
        <h1 className="text-xl font-semibold mb-4">Billing</h1>
        <div className="oversee-card p-6">
            <p className="text-sm text-zinc-500">
                Manage payment methods, subscriptions, and invoices in your account area.
            </p>
            <p className="mt-3 flex gap-2">
                <a href="/my-account/subscriptions/" className="oversee-btn-primary inline-block">Subscriptions</a>
                <a href="/my-account/payment-methods/" className="oversee-btn-secondary inline-block">Payment methods</a>
                <a href="/my-account/orders/" className="oversee-btn-secondary inline-block">Invoices</a>
            </p>
        </div>
    </div>
);

export const Account = () => (
    <div>
        <h1 className="text-xl font-semibold mb-4">Account</h1>
        <div className="oversee-card p-6">
            <p className="text-sm text-zinc-500">Update your profile, password, and notification preferences.</p>
            <p className="mt-3"><a href="/my-account/edit-account/" className="oversee-btn-primary inline-block">Edit account</a></p>
        </div>
    </div>
);

// Admin counterparts — minimal stubs the rest of the SPA can drill into.

export const AdminHome = () => (
    <div>
        <h1 className="text-xl font-semibold mb-4">Admin dashboard</h1>
        <div className="grid gap-4" style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}>
            {["Active boards", "Open client requests", "Renewals this week"].map((label) => (
                <div key={label} className="oversee-card p-4">
                    <div className="text-xs uppercase tracking-wider text-zinc-500">{label}</div>
                    <div className="text-2xl font-semibold mt-2">—</div>
                </div>
            ))}
        </div>
    </div>
);

export const AdminClients = () => (
    <div><h1 className="text-xl font-semibold mb-4">Clients</h1><div className="oversee-card p-6 text-sm text-zinc-500">Client list coming online — connects to /admin/customers REST endpoint.</div></div>
);
export const AdminBoards = () => (
    <div><h1 className="text-xl font-semibold mb-4">All boards</h1><div className="oversee-card p-6 text-sm text-zinc-500">All Oversee boards across all clients.</div></div>
);
export const AdminSpecialists = () => (
    <div><h1 className="text-xl font-semibold mb-4">Specialists</h1><div className="oversee-card p-6 text-sm text-zinc-500">Specialist roster + workload.</div></div>
);
export const AdminOrders = () => (
    <div><h1 className="text-xl font-semibold mb-4">Orders</h1><div className="oversee-card p-6 text-sm text-zinc-500">Recent WooCommerce orders.</div></div>
);
export const AdminSubscriptions = () => (
    <div><h1 className="text-xl font-semibold mb-4">Subscriptions</h1><div className="oversee-card p-6 text-sm text-zinc-500">Active and lapsed subscriptions.</div></div>
);
export const AdminCatalog = () => (
    <div><h1 className="text-xl font-semibold mb-4">Catalog</h1><div className="oversee-card p-6 text-sm text-zinc-500">SKU → board template mapping.</div></div>
);
export const AdminTemplates = () => (
    <div><h1 className="text-xl font-semibold mb-4">Templates</h1><div className="oversee-card p-6 text-sm text-zinc-500">Edit board templates that get spawned on order completion.</div></div>
);
export const AdminAutomations = () => (
    <div><h1 className="text-xl font-semibold mb-4">Automations</h1><div className="oversee-card p-6 text-sm text-zinc-500">Workflow rules per board template.</div></div>
);
export const AdminKnowledge = () => (
    <div><h1 className="text-xl font-semibold mb-4">Knowledge base (admin)</h1><div className="oversee-card p-6 text-sm text-zinc-500">Curate the client-facing knowledge base.</div></div>
);
export const AdminHighLevel = () => (
    <div>
        <h1 className="text-xl font-semibold mb-4">HighLevel SSO</h1>
        <div className="oversee-card p-6 text-sm text-zinc-500">
            Configure the magic-link endpoint (settings or constant <code>HIGHLEVEL_MAGIC_LINK_ENDPOINT</code>) so the embed surfaces work end-to-end.
        </div>
    </div>
);
export const AdminSettings = () => (
    <div><h1 className="text-xl font-semibold mb-4">Settings</h1><div className="oversee-card p-6 text-sm text-zinc-500">Tokens, brand assets, and dashboard policies.</div></div>
);
export const AdminActivity = () => (
    <div><h1 className="text-xl font-semibold mb-4">Activity log</h1><div className="oversee-card p-6 text-sm text-zinc-500">Append-only audit log across boards.</div></div>
);
