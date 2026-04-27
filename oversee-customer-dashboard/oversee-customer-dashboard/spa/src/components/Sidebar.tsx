import { NavLink } from "react-router-dom";
import { useState } from "react";
import {
    Home, Folder, MessageCircle, Calendar, BarChart3, FileText, Star,
    BookOpen, ShoppingBag, CreditCard, User, LayoutDashboard, Users,
    UserCog, Package, Repeat, Zap, Link as LinkIcon, Settings, History,
    PanelLeftClose, PanelLeftOpen, CheckSquare, ListChecks, FileSignature,
    Sun,
    type LucideIcon,
} from "lucide-react";
import { CLIENT_NAV, ADMIN_NAV, type NavGroup } from "@/lib/nav";

const ICONS: Record<string, LucideIcon> = {
    home: Home,
    kanban: LayoutDashboard,
    folder: Folder,
    "message-circle": MessageCircle,
    calendar: Calendar,
    "bar-chart-3": BarChart3,
    "file-text": FileText,
    star: Star,
    "book-open": BookOpen,
    "shopping-bag": ShoppingBag,
    "credit-card": CreditCard,
    user: User,
    "layout-dashboard": LayoutDashboard,
    users: Users,
    "user-cog": UserCog,
    package: Package,
    repeat: Repeat,
    zap: Zap,
    link: LinkIcon,
    settings: Settings,
    history: History,
    "check-square": CheckSquare,
    "list-checks": ListChecks,
    "file-signature": FileSignature,
    sun: Sun,
};

export function Sidebar({ admin, badges }: { admin: boolean; badges?: Record<string, number | undefined> }) {
    const [collapsed, setCollapsed] = useState(false);
    const groups: NavGroup[] = admin ? ADMIN_NAV : CLIENT_NAV;

    return (
        <aside
            className="oversee-card flex flex-col"
            style={{
                width: collapsed ? 60 : 220,
                minHeight: "calc(100vh - 32px)",
                margin: 16,
                padding: 12,
                borderRadius: 12,
                transition: "width 0.18s ease",
            }}
            aria-label={admin ? "Admin navigation" : "Client navigation"}
        >
            <div className="flex items-center justify-between mb-4 px-2">
                {!collapsed && (
                    <span className="font-semibold tracking-tight text-base">Oversee</span>
                )}
                <button
                    type="button"
                    aria-label={collapsed ? "Expand sidebar" : "Collapse sidebar"}
                    onClick={() => setCollapsed(!collapsed)}
                    className="p-1.5 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800"
                >
                    {collapsed ? <PanelLeftOpen size={16} /> : <PanelLeftClose size={16} />}
                </button>
            </div>

            <nav className="flex-1 overflow-y-auto">
                {groups.map((group) => (
                    <div key={group.label} className="mb-4">
                        {!collapsed && (
                            <div className="px-2 mb-1 text-[10px] font-semibold tracking-widest text-zinc-500">
                                {group.label}
                            </div>
                        )}
                        <ul>
                            {group.items.map((item) => {
                                const Icon = ICONS[item.icon] ?? Home;
                                const badgeCount = item.badge && badges ? badges[item.badge] : undefined;
                                return (
                                    <li key={item.route}>
                                        <NavLink
                                            to={item.route}
                                            end={item.route === "/" || item.route === "/admin"}
                                            className={({ isActive }) =>
                                                "flex items-center gap-2 px-2 py-2 rounded-md text-sm " +
                                                (isActive
                                                    ? "oversee-nav-active"
                                                    : "hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-200")
                                            }
                                        >
                                            <Icon size={16} />
                                            {!collapsed && <span className="flex-1 truncate">{item.label}</span>}
                                            {!collapsed && badgeCount && badgeCount > 0 ? (
                                                <span className="ml-auto inline-flex items-center justify-center min-w-[18px] h-[18px] px-1.5 rounded-full text-[10px] font-medium" style={{ background: "var(--oversee-accent)", color: "#fff" }}>
                                                    {badgeCount > 99 ? "99+" : badgeCount}
                                                </span>
                                            ) : null}
                                        </NavLink>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                ))}
            </nav>
        </aside>
    );
}
