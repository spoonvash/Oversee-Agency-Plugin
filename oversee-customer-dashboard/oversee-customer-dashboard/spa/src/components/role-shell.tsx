import { type PropsWithChildren, useState, useMemo } from "react";
import { Link, useLocation } from "wouter";
import {
  Bell,
  ChevronRight,
  Search,
  AlertCircle,
  CheckCircle2,
  Rocket,
  CreditCard,
  Settings as SettingsIcon,
  CheckCheck,
} from "lucide-react";
import { SidebarProvider, SidebarTrigger } from "@/components/ui/sidebar";
import { RoleSidebar, type RoleNavGroup } from "@/components/role-sidebar";
import { ThemeToggle } from "@/components/theme-toggle";
import { CommandPalette, useCommandPalette } from "@/components/command-palette";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import { Button } from "@/components/ui/button";
import { AuditSummary } from "@/components/audit-summary";
import { useDemoStore } from "@/lib/demo-store";
import { ItemDetailSheet } from "@/components/item-detail-sheet";
import { shortDateTime } from "@/lib/format";
import { cn } from "@/lib/utils";

type Crumb = { label: string; href?: string };

type Props = PropsWithChildren<{
  role: "client" | "admin";
  groups: RoleNavGroup[];
  identity: { name: string; sub: string; initials: string };
  breadcrumbs: Crumb[];
}>;

export function RoleShell({
  role,
  groups,
  identity,
  breadcrumbs,
  children,
}: Props) {
  const cmdk = useCommandPalette();
  const [auditOpen, setAuditOpen] = useState(false);

  return (
    <SidebarProvider className="app-sidebar-provider">
      <CommandPalette open={cmdk.open} onOpenChange={cmdk.setOpen} role={role} />
      <AuditSummary open={auditOpen} onOpenChange={setAuditOpen} area={role} />
      <div className="flex h-screen w-full overflow-hidden bg-background">
        <RoleSidebar role={role} groups={groups} />
        <div className="flex min-w-0 flex-1 flex-col">
          <header className="sticky top-0 z-30 flex min-h-16 items-center gap-3 border-b border-border bg-background/95 px-3 backdrop-blur md:px-5">
            <SidebarTrigger data-testid="button-sidebar-toggle" />
            <Breadcrumbs crumbs={breadcrumbs} />
            <div className="ml-auto flex items-center gap-2">
              <SearchTopbarTrigger onClick={() => cmdk.setOpen(true)} />
              <NotificationBell role={role} />
              <ThemeToggle />
              <UserMenu identity={identity} role={role} onAuditOpen={() => setAuditOpen(true)} />
            </div>
          </header>
          <main className="min-h-0 flex-1 overflow-y-auto overscroll-contain scrollbar-soft">
            {children}
          </main>
        </div>
      </div>
    </SidebarProvider>
  );
}

// Topbar search trigger — wider, taller pill that opens the command palette.
// Mobile collapses to an icon-only button. Desktop shows full search affordance.
function SearchTopbarTrigger({ onClick }: { onClick: () => void }) {
  return (
    <>
      <button
        type="button"
        onClick={onClick}
        className="hidden h-10 w-56 items-center gap-2 rounded-lg border border-border bg-surface-elevated px-3 text-sm font-normal text-muted-foreground transition hover:border-strong hover:bg-card focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring md:inline-flex lg:w-72 xl:w-80"
        data-testid="button-cmdk-trigger"
        aria-label="Open search (⌘K)"
        title="Search (⌘K)"
      >
        <Search className="size-4 opacity-70" />
        <span className="flex-1 text-left text-sm">Search</span>
        <kbd className="ml-2 rounded border border-border bg-background px-1.5 py-0.5 text-[10px] font-medium tracking-wider text-muted-foreground">
          ⌘K
        </kbd>
      </button>
      <Button
        variant="ghost"
        size="icon"
        className="size-10 md:hidden"
        onClick={onClick}
        data-testid="button-cmdk-trigger-mobile"
        aria-label="Open search (⌘K)"
        title="Search (⌘K)"
      >
        <Search className="size-[18px]" />
      </Button>
    </>
  );
}

function Breadcrumbs({ crumbs }: { crumbs: Crumb[] }) {
  return (
    <nav aria-label="Breadcrumb" className="hidden min-w-0 items-center gap-1.5 text-sm md:flex">
      {crumbs.map((c, i) => {
        const last = i === crumbs.length - 1;
        return (
          <span key={i} className="flex items-center gap-1.5">
            {i > 0 && <ChevronRight className="size-3.5 shrink-0 text-muted-foreground/60" />}
            {c.href && !last ? (
              <Link href={c.href} className="text-muted-foreground hover:text-foreground" data-testid={`crumb-${i}`}>
                {c.label}
              </Link>
            ) : (
              <span className={last ? "font-medium text-foreground" : "text-muted-foreground"} data-testid={`crumb-${i}`}>
                {c.label}
              </span>
            )}
          </span>
        );
      })}
    </nav>
  );
}

type NotifCategory = "message" | "approval" | "onboarding" | "billing" | "system";

type DerivedNotif = {
  id: string;
  category: NotifCategory;
  title: string;
  body: string;
  unread: boolean;
  // Where this notification points
  itemId?: string;
  href?: string;
  postedAt: string;
  authorName?: string;
};

const CATEGORY_META: Record<NotifCategory, { label: string; Icon: typeof Bell; color: string }> = {
  message: { label: "Messages", Icon: AlertCircle, color: "text-primary" },
  approval: { label: "Approvals", Icon: CheckCircle2, color: "text-blue-500" },
  onboarding: { label: "Onboarding", Icon: Rocket, color: "text-emerald-500" },
  billing: { label: "Billing", Icon: CreditCard, color: "text-rose-500" },
  system: { label: "System", Icon: SettingsIcon, color: "text-muted-foreground" },
};

function NotificationBell({ role }: { role: "client" | "admin" }) {
  const [, navigate] = useLocation();
  const {
    feed,
    items,
    files,
    services,
    subscriptions,
    invoices,
    onboardingPlans,
    markUpdateRead,
  } = useDemoStore();

  const [open, setOpen] = useState(false);
  const [activeCategory, setActiveCategory] = useState<NotifCategory | "all">("all");
  // Track items dismissed/marked read manually for synthetic non-feed notifs
  const [dismissed, setDismissed] = useState<Record<string, boolean>>({});
  const [openItemId, setOpenItemId] = useState<string | null>(null);

  // Build a single, comprehensive notification list pulled from real preview state.
  const notifications = useMemo<DerivedNotif[]>(() => {
    const list: DerivedNotif[] = [];

    // 1. Project messages (from feed) — only those visible to the role
    const visibleFeed = role === "client" ? feed.filter((f) => !f.internalOnly) : feed;
    visibleFeed.slice(0, 8).forEach((f) => {
      list.push({
        id: `feed:${f.id}`,
        category: "message",
        title: f.authorName,
        body: f.body,
        unread: f.unread && !dismissed[`feed:${f.id}`],
        itemId: f.itemId,
        postedAt: f.postedAt,
        authorName: f.authorName,
      });
    });

    // 2. Approvals — files pending review (client) or files just submitted by client with changes-requested (admin)
    if (role === "client") {
      files
        .filter((f) => f.approval === "pending")
        .slice(0, 5)
        .forEach((f) => {
          list.push({
            id: `approval:${f.id}`,
            category: "approval",
            title: `${f.name} ready for your review`,
            body: `${f.boardName ? f.boardName + " · " : ""}Uploaded by ${f.uploadedBy}. Approve or request changes.`,
            unread: !dismissed[`approval:${f.id}`],
            href: "/client/files",
            postedAt: f.uploadedAt,
          });
        });
    } else {
      files
        .filter((f) => f.approval === "changes-requested")
        .slice(0, 5)
        .forEach((f) => {
          list.push({
            id: `approval:${f.id}`,
            category: "approval",
            title: `Changes requested on ${f.name}`,
            body: `${f.boardName ? f.boardName + " · " : ""}Client asked for revisions.`,
            unread: !dismissed[`approval:${f.id}`],
            href: "/admin/boards",
            postedAt: f.uploadedAt,
          });
        });
    }

    // 3. Onboarding — grouped per plan to avoid drawer spam.
    if (role === "client") {
      onboardingPlans.forEach((plan) => {
        const pending = plan.steps.filter(
          (s) => s.status === "not-started" || s.status === "in-progress",
        );
        if (pending.length === 0) return;
        const planLabel = plan.label ?? services.find((s) => s.id === plan.serviceId)?.title ?? "Service";
        list.push({
          id: `onb:${plan.serviceId}`,
          category: "onboarding",
          title: `${pending.length} access step${pending.length === 1 ? "" : "s"} for ${planLabel}`,
          body: pending
            .slice(0, 2)
            .map((s) => s.title)
            .join(", ") + (pending.length > 2 ? ", …" : ""),
          unread: !dismissed[`onb:${plan.serviceId}`],
          href: "/client/onboarding",
          postedAt: new Date().toISOString(),
        });
      });
    } else {
      // Admin: surface plans with steps still pending so admin knows to follow up
      onboardingPlans
        .filter((p) => p.steps.some((s) => s.status !== "complete"))
        .slice(0, 2)
        .forEach((plan) => {
          const remaining = plan.steps.filter((s) => s.status !== "complete").length;
          const label = plan.label ?? services.find((s) => s.id === plan.serviceId)?.title ?? "Client";
          list.push({
            id: `onb:admin:${plan.serviceId}`,
            category: "onboarding",
            title: `${label} onboarding in progress`,
            body: `${remaining} step${remaining === 1 ? "" : "s"} pending. Review client access status.`,
            unread: !dismissed[`onb:admin:${plan.serviceId}`],
            href: "/admin/clients",
            postedAt: new Date().toISOString(),
          });
        });
    }

    // 4. Billing (client only) — past-due/open invoices, paused subs
    if (role === "client") {
      invoices
        .filter((i) => i.status === "open" || i.status === "past-due")
        .slice(0, 3)
        .forEach((inv) => {
          list.push({
            id: `bill:${inv.id}`,
            category: "billing",
            title: `Invoice ${inv.number} ${inv.status === "past-due" ? "past due" : "open"}`,
            body: `$${inv.amount.toFixed(2)} — ${inv.service}. Review in Account.`,
            unread: !dismissed[`bill:${inv.id}`],
            href: "/client/account/orders",
            postedAt: inv.date,
          });
        });
      subscriptions
        .filter((s) => s.status === "paused" || s.status === "past-due")
        .slice(0, 2)
        .forEach((sub) => {
          const svc = services.find((x) => x.id === sub.serviceId);
          list.push({
            id: `bill:sub:${sub.id}`,
            category: "billing",
            title: `${svc?.title ?? "Subscription"} is ${sub.status}`,
            body: "Manage your subscription in Account → Subscriptions.",
            unread: !dismissed[`bill:sub:${sub.id}`],
            href: "/client/account/subscriptions",
            postedAt: sub.renewsOn,
          });
        });
    }

    // 5. System (admin only) — surface board items needing assignment as a soft system nudge
    if (role === "admin") {
      const unassigned = items.filter((i) => !i.assignee).slice(0, 2);
      unassigned.forEach((it) => {
        list.push({
          id: `sys:unassigned:${it.id}`,
          category: "system",
          title: `Unassigned: ${it.title}`,
          body: "No owner set — assign someone in Boards.",
          unread: !dismissed[`sys:unassigned:${it.id}`],
          itemId: it.id,
          postedAt: new Date().toISOString(),
        });
      });
    }

    return list;
  }, [role, feed, files, services, subscriptions, invoices, onboardingPlans, items, dismissed]);

  const liveUnread = notifications.filter((n) => n.unread).length;
  const grouped = useMemo(() => {
    const out: Record<NotifCategory, DerivedNotif[]> = { message: [], approval: [], onboarding: [], billing: [], system: [] };
    notifications.forEach((n) => out[n.category].push(n));
    return out;
  }, [notifications]);
  const visible = activeCategory === "all" ? notifications : grouped[activeCategory];

  const markAllRead = () => {
    const next: Record<string, boolean> = { ...dismissed };
    notifications.forEach((n) => {
      if (n.unread) {
        next[n.id] = true;
        // Also mark feed items read in store so other surfaces stay consistent
        if (n.id.startsWith("feed:")) {
          const feedId = n.id.slice("feed:".length);
          markUpdateRead(feedId);
        }
      }
    });
    setDismissed(next);
  };

  const handleAction = (n: DerivedNotif) => {
    // Mark this item read both locally and (for feed) in the store
    setDismissed((p) => ({ ...p, [n.id]: true }));
    if (n.id.startsWith("feed:")) {
      markUpdateRead(n.id.slice("feed:".length));
    }
    // Navigate
    if (n.itemId) {
      // Open the item-detail sheet for context preservation
      setOpenItemId(n.itemId);
      setOpen(false);
      return;
    }
    if (n.href) {
      // Wouter useHashLocation expects path starting with /
      navigate(n.href);
      setOpen(false);
    }
  };

  const liveOpenItem = openItemId ? items.find((i) => i.id === openItemId) ?? null : null;

  return (
    <>
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <Button
            variant="ghost"
            size="icon"
            className="relative size-10"
            data-testid="button-notifications"
            aria-label={`Notifications${liveUnread ? ` (${liveUnread} unread)` : ""}`}
            title="Notifications"
          >
            <Bell className="size-[18px]" />
            {liveUnread > 0 && (
              <span
                className="absolute right-1.5 top-1.5 grid h-4 min-w-4 place-items-center rounded-full bg-primary px-1 text-[9px] font-bold text-primary-foreground ring-2 ring-background"
                data-testid="notif-unread-count"
              >
                {liveUnread}
              </span>
            )}
          </Button>
        </PopoverTrigger>
        <PopoverContent align="end" className="w-[400px] p-0" data-testid="popover-notifications">
          <div className="flex items-center justify-between border-b border-border bg-surface-elevated px-4 py-3">
            <div>
              <p className="text-sm font-semibold">Notifications</p>
              <p className="text-xs text-muted-foreground" data-testid="text-unread-summary">
                {liveUnread === 0 ? "You're all caught up." : `${liveUnread} unread`}
              </p>
            </div>
            <Button
              variant="ghost"
              size="sm"
              className="h-7 gap-1 px-2 text-xs"
              onClick={markAllRead}
              disabled={liveUnread === 0}
              data-testid="button-notifications-mark-all-read"
            >
              <CheckCheck className="size-3.5" /> Mark all read
            </Button>
          </div>
          <div className="flex flex-wrap gap-1 border-b border-border px-2 py-2">
            <CategoryTab
              label="All"
              count={notifications.length}
              active={activeCategory === "all"}
              onClick={() => setActiveCategory("all")}
              testId="notif-tab-all"
            />
            {(Object.keys(CATEGORY_META) as NotifCategory[]).map((k) => {
              const meta = CATEGORY_META[k];
              const count = grouped[k].length;
              if (count === 0) return null;
              return (
                <CategoryTab
                  key={k}
                  label={meta.label}
                  count={count}
                  active={activeCategory === k}
                  onClick={() => setActiveCategory(k)}
                  testId={`notif-tab-${k}`}
                />
              );
            })}
          </div>
          <div className="max-h-[420px] overflow-y-auto">
            {visible.length === 0 ? (
              <div className="px-4 py-12 text-center text-sm text-muted-foreground" data-testid="notif-empty">
                <CheckCheck className="mx-auto mb-2 size-5 opacity-60" />
                Nothing here.
              </div>
            ) : (
              visible.map((n) => (
                <NotificationRow key={n.id} notif={n} onActivate={() => handleAction(n)} />
              ))
            )}
          </div>
        </PopoverContent>
      </Popover>
      <ItemDetailSheet
        item={liveOpenItem}
        onClose={() => setOpenItemId(null)}
        role={role}
      />
    </>
  );
}

// NotificationRow — the entire row is a single accessible button. Click or
// Enter/Space activates the notification (mark read + navigate / open item).
// No inner buttons. Mark-all-read still lives in the dropdown header.
function NotificationRow({ notif, onActivate }: { notif: DerivedNotif; onActivate: () => void }) {
  const Icon = CATEGORY_META[notif.category].Icon;
  const colorClass = CATEGORY_META[notif.category].color;
  const ariaLabel = `${CATEGORY_META[notif.category].label}: ${notif.title}.${notif.unread ? " Unread." : ""} Activate to open.`;
  return (
    <button
      type="button"
      onClick={onActivate}
      aria-label={ariaLabel}
      title={notif.title}
      data-testid={`notif-item-${notif.id}`}
      className={cn(
        "group flex w-full cursor-pointer items-start gap-3 border-b border-border/70 px-4 py-3 text-left transition",
        "last:border-0 hover:bg-muted/50 focus-visible:bg-muted/60 focus:outline-none focus-visible:ring-1 focus-visible:ring-inset focus-visible:ring-ring",
        notif.unread && "bg-primary-soft/40",
      )}
    >
      <Icon className={cn("mt-0.5 size-4 shrink-0", colorClass)} aria-hidden />
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-2">
          <p className={cn("truncate text-sm leading-snug", notif.unread ? "font-semibold" : "font-medium")}>
            {notif.title}
          </p>
          {notif.unread && <span className="size-1.5 shrink-0 rounded-full bg-primary" aria-hidden />}
        </div>
        <p className="mt-0.5 line-clamp-2 text-xs text-muted-foreground">{notif.body}</p>
        <div className="mt-1.5 flex items-center justify-between gap-2">
          <span className="text-[10px] text-muted-foreground">{shortDateTime(notif.postedAt)}</span>
          <span className="text-[10px] font-medium text-muted-foreground transition group-hover:text-primary group-focus-visible:text-primary">
            {notif.itemId ? "Open item" : notif.href ? "Open" : "Mark read"}
          </span>
        </div>
      </div>
    </button>
  );
}

function CategoryTab({ label, count, active, onClick, testId }: { label: string; count: number; active: boolean; onClick: () => void; testId: string }) {
  return (
    <button
      type="button"
      onClick={onClick}
      data-testid={testId}
      className={`inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium transition ${
        active ? "bg-foreground text-background" : "text-muted-foreground hover:bg-muted"
      }`}
    >
      {label}
      <span className={`rounded px-1 text-[10px] ${active ? "bg-background/20" : "bg-muted"}`}>{count}</span>
    </button>
  );
}

function UserMenu({
  identity,
  role,
  onAuditOpen,
}: {
  identity: { name: string; sub: string; initials: string };
  role: "client" | "admin";
  onAuditOpen: () => void;
}) {
  const [, navigate] = useLocation();
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <button
          className="flex h-10 items-center gap-2 rounded-lg border border-border bg-card px-2 transition hover:border-strong hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          data-testid="button-user-menu"
          aria-label={`Account menu — ${identity.name}`}
          title={identity.name}
        >
          <div className="grid size-7 shrink-0 place-items-center rounded-md bg-primary text-xs font-semibold text-primary-foreground">
            {identity.initials}
          </div>
          <div className="hidden pr-1 text-left leading-tight md:grid">
            <span className="text-xs font-medium">{identity.name}</span>
            <span className="text-[11px] text-muted-foreground">{identity.sub}</span>
          </div>
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-56">
        <DropdownMenuLabel>
          <div className="flex flex-col">
            <span className="text-sm font-medium">{identity.name}</span>
            <span className="text-xs text-muted-foreground">{identity.sub}</span>
          </div>
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuItem
          onClick={() =>
            navigate(role === "client" ? "/client/account/profile" : "/admin/settings/team")
          }
          data-testid="menu-profile"
        >
          Profile settings
        </DropdownMenuItem>
        <DropdownMenuItem
          onClick={() => navigate(role === "client" ? "/client/account" : "/admin/settings")}
          data-testid="menu-workspace-settings"
        >
          Workspace settings
        </DropdownMenuItem>
        <DropdownMenuItem onClick={onAuditOpen} data-testid="menu-preview-audit">
          Preview QA / audit…
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem
          onClick={() => navigate(role === "client" ? "/admin" : "/client")}
          data-testid="menu-switch-role"
        >
          Switch to {role === "client" ? "Admin" : "Client"} view
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <Link href="/" data-testid="menu-back-to-selector">Back to role selector</Link>
        </DropdownMenuItem>
        <DropdownMenuItem onClick={() => navigate("/login")} data-testid="menu-sign-out">
          Sign out
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

export function PageHeader({
  title,
  description,
  actions,
}: {
  title: string;
  description?: string;
  actions?: React.ReactNode;
}) {
  return (
    <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
      <div className="min-w-0">
        <h1 className="text-xl font-semibold tracking-tight md:text-2xl">{title}</h1>
        {description && (
          <p className="mt-1 text-sm text-muted-foreground">{description}</p>
        )}
      </div>
      {actions && <div className="flex items-center gap-2">{actions}</div>}
    </div>
  );
}
