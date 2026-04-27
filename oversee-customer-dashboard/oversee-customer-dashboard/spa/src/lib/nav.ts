// Sidebar navigation per the latest authoritative Assembly-style spec.
//
// Client view: 12 items grouped HOME / WORK / COMMUNICATION / INSIGHTS / COMMERCE.
// Admin view: 15 items grouped OPERATIONS / COMMERCE / TOOLS / SYSTEM.

export type NavItem = {
    label: string;
    route: string;
    icon: string;
    badge?: "unread" | "pending" | "due";
};
export type NavGroup = { label: string; items: NavItem[] };

export const CLIENT_NAV: NavGroup[] = [
    {
        label: "HOME",
        items: [
            { label: "Home", route: "/", icon: "home" },
        ],
    },
    {
        label: "WORK",
        items: [
            { label: "Tasks", route: "/tasks", icon: "check-square", badge: "due" },
            { label: "Files", route: "/files", icon: "folder" },
            { label: "Forms", route: "/forms", icon: "list-checks", badge: "pending" },
            { label: "Contracts", route: "/contracts", icon: "file-signature", badge: "pending" },
        ],
    },
    {
        label: "COMMUNICATION",
        items: [
            { label: "Messages", route: "/messages", icon: "message-circle", badge: "unread" },
            { label: "Schedule a Call", route: "/schedule", icon: "calendar" },
        ],
    },
    {
        label: "INSIGHTS",
        items: [
            { label: "Performance Reports", route: "/reports", icon: "bar-chart-3" },
            { label: "Reviews", route: "/reviews", icon: "star" },
        ],
    },
    {
        label: "COMMERCE",
        items: [
            { label: "Browse Services", route: "/services", icon: "shopping-bag" },
            { label: "Subscriptions", route: "/subscriptions", icon: "repeat" },
            { label: "Billing", route: "/billing", icon: "credit-card" },
        ],
    },
];

export const ADMIN_NAV: NavGroup[] = [
    {
        label: "OPERATIONS",
        items: [
            { label: "Today", route: "/admin", icon: "sun" },
            { label: "Clients", route: "/admin/clients", icon: "users" },
            { label: "Projects", route: "/admin/projects", icon: "kanban" },
            { label: "Tasks", route: "/admin/tasks", icon: "check-square" },
            { label: "Messages", route: "/admin/messages", icon: "message-circle", badge: "unread" },
        ],
    },
    {
        label: "COMMERCE",
        items: [
            { label: "Orders", route: "/admin/orders", icon: "package" },
            { label: "Subscriptions", route: "/admin/subscriptions", icon: "repeat" },
            { label: "Service Templates", route: "/admin/service-templates", icon: "file-text" },
            { label: "Service Catalog", route: "/admin/service-catalog", icon: "shopping-bag" },
            { label: "Payments", route: "/admin/payments", icon: "credit-card" },
        ],
    },
    {
        label: "TOOLS",
        items: [
            { label: "Forms", route: "/admin/forms", icon: "list-checks" },
            { label: "Contracts", route: "/admin/contracts", icon: "file-signature" },
            { label: "Files", route: "/admin/files", icon: "folder" },
            { label: "Automations", route: "/admin/automations", icon: "zap" },
        ],
    },
    {
        label: "SYSTEM",
        items: [
            { label: "Team", route: "/admin/team", icon: "user-cog" },
            { label: "Settings", route: "/admin/settings", icon: "settings" },
        ],
    },
];

// Compile-time guards: surface mistakes if someone edits the lists.
const _CLIENT_TOTAL = CLIENT_NAV.reduce((n, g) => n + g.items.length, 0);
const _ADMIN_TOTAL = ADMIN_NAV.reduce((n, g) => n + g.items.length, 0);
if (_CLIENT_TOTAL !== 12) {
    // eslint-disable-next-line no-console
    console.warn(`Oversee CLIENT_NAV expected 12 items, got ${_CLIENT_TOTAL}`);
}
if (_ADMIN_TOTAL !== 15) {
    // eslint-disable-next-line no-console
    console.warn(`Oversee ADMIN_NAV expected 15 items, got ${_ADMIN_TOTAL}`);
}
