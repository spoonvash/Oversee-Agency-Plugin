// Sidebar navigation definitions per the user's spec.
//
// Client view: 12 items grouped into MAIN / COMMUNICATION / RESOURCES / COMMERCE.
// Admin view: 13 items grouped into OPERATIONS / COMMERCE / LIBRARY / SYSTEM.

export type NavItem = { label: string; route: string; icon: string };
export type NavGroup = { label: string; items: NavItem[] };

export const CLIENT_NAV: NavGroup[] = [
    {
        label: "MAIN",
        items: [
            { label: "Home", route: "/", icon: "home" },
            { label: "My Projects", route: "/projects", icon: "kanban" },
            { label: "Files", route: "/files", icon: "folder" },
        ],
    },
    {
        label: "COMMUNICATION",
        items: [
            { label: "Messages", route: "/messages", icon: "message-circle" },
            { label: "Schedule a call", route: "/schedule-call", icon: "calendar" },
            { label: "Performance reports", route: "/performance-reports", icon: "bar-chart-3" },
        ],
    },
    {
        label: "RESOURCES",
        items: [
            { label: "Documents", route: "/documents", icon: "file-text" },
            { label: "Reviews", route: "/reviews", icon: "star" },
            { label: "Knowledge base", route: "/knowledge", icon: "book-open" },
        ],
    },
    {
        label: "COMMERCE",
        items: [
            { label: "Services", route: "/services", icon: "shopping-bag" },
            { label: "Billing", route: "/billing", icon: "credit-card" },
            { label: "Account", route: "/account", icon: "user" },
        ],
    },
];

export const ADMIN_NAV: NavGroup[] = [
    {
        label: "OPERATIONS",
        items: [
            { label: "Dashboard", route: "/admin", icon: "layout-dashboard" },
            { label: "Clients", route: "/admin/clients", icon: "users" },
            { label: "Boards", route: "/admin/boards", icon: "kanban" },
            { label: "Specialists", route: "/admin/specialists", icon: "user-cog" },
        ],
    },
    {
        label: "COMMERCE",
        items: [
            { label: "Orders", route: "/admin/orders", icon: "package" },
            { label: "Subscriptions", route: "/admin/subscriptions", icon: "repeat" },
            { label: "Catalog", route: "/admin/catalog", icon: "shopping-bag" },
        ],
    },
    {
        label: "LIBRARY",
        items: [
            { label: "Templates", route: "/admin/templates", icon: "file-text" },
            { label: "Automations", route: "/admin/automations", icon: "zap" },
            { label: "Knowledge base", route: "/admin/knowledge", icon: "book-open" },
        ],
    },
    {
        label: "SYSTEM",
        items: [
            { label: "HighLevel SSO", route: "/admin/highlevel", icon: "link" },
            { label: "Settings", route: "/admin/settings", icon: "settings" },
            { label: "Activity log", route: "/admin/activity", icon: "history" },
        ],
    },
];
