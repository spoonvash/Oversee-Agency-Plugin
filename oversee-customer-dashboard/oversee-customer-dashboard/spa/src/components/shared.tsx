// Shared UI primitives — used across client + admin pages.
// Keep cards, headers, badges, action rows uniform. Avoid one-off Tailwind piles.
//
// Design system contract:
//  - One canonical card shape:        SectionCard / AppCard
//  - One canonical metric strip:      SummaryStat
//  - One canonical row pattern:       DataListRow inside DataList
//  - One canonical priority banner:   PriorityBanner
//  - One canonical settings panel:    SettingsPanel
//  - One canonical integration card:  IntegrationCard
//  - One canonical status pill:       StatusPill (description prop = tooltip)
//  - One canonical detail rail:       DetailRail
//  - One canonical empty state:       EmptyState
//  - One canonical icon-only button:  IconActionButton (forces aria-label/title)
//  - One canonical group:             ButtonGroup (max 3 actions; menu beyond)
//  - One canonical link control:      SectionLink (for header trailing "Settings"/"All"/etc)
import { type ReactNode, type ComponentType } from "react";
import type { LucideIcon } from "lucide-react";
import {
  ArrowDown,
  ArrowRight,
  ArrowUp,
  CheckCircle2,
  ChevronRight,
  ExternalLink,
} from "lucide-react";
import { Button, type ButtonProps } from "@/components/ui/button";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";

// ─────────────────────────────────────────────────────────────────────────────
// PageHeader — page title + subtext + optional right-side actions.
// One per page. Replaces ad-hoc h1+p+button rows everywhere.
// ─────────────────────────────────────────────────────────────────────────────
export function PageHeader({
  eyebrow,
  title,
  subtitle,
  actions,
  testId,
}: {
  eyebrow?: string;
  title: string;
  subtitle?: string;
  actions?: ReactNode;
  testId?: string;
}) {
  return (
    <header
      className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between md:gap-6"
      data-testid={testId ?? "page-header"}
    >
      <div className="min-w-0 max-w-3xl">
        {eyebrow && (
          <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">
            {eyebrow}
          </p>
        )}
        <h1
          className="mt-1 text-xl font-semibold tracking-tight text-foreground md:text-2xl"
          data-testid="heading-page"
        >
          {title}
        </h1>
        {subtitle && (
          <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground">{subtitle}</p>
        )}
      </div>
      {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
    </header>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// SectionHeader — small section title above a list/grid.
// ─────────────────────────────────────────────────────────────────────────────
export function SectionHeader({
  title,
  hint,
  trailing,
  testId,
}: {
  title: string;
  hint?: string;
  trailing?: ReactNode;
  testId?: string;
}) {
  return (
    <div className="flex items-baseline justify-between gap-3" data-testid={testId}>
      <div>
        <h2 className="text-sm font-semibold text-foreground">{title}</h2>
        {hint && <p className="mt-0.5 text-xs text-muted-foreground">{hint}</p>}
      </div>
      {trailing && <div className="shrink-0">{trailing}</div>}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// AppCard — the single card style used across the app.
// Always: 1px border, rounded-xl, card bg. No nested cards. No thick left borders.
// ─────────────────────────────────────────────────────────────────────────────
export function AppCard({
  children,
  className,
  padded = true,
  testId,
}: {
  children: ReactNode;
  className?: string;
  padded?: boolean;
  testId?: string;
}) {
  return (
    <section
      className={cn(
        "rounded-xl border border-border bg-card text-card-foreground",
        padded && "p-5 md:p-6",
        className,
      )}
      data-testid={testId}
    >
      {children}
    </section>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// StatusPill — uniform status display. Never hover/cursor (these are metadata,
// not buttons). Tones map to semantic colors only.
// ─────────────────────────────────────────────────────────────────────────────
export type StatusTone =
  | "neutral"
  | "success"
  | "warning"
  | "danger"
  | "info"
  | "primary";

export function StatusPill({
  tone = "neutral",
  children,
  description,
  className,
  testId,
  uppercase = true,
}: {
  tone?: StatusTone;
  children: ReactNode;
  /** Optional explanation shown as a tooltip + title attribute. */
  description?: string;
  className?: string;
  testId?: string;
  uppercase?: boolean;
}) {
  const map: Record<StatusTone, string> = {
    neutral: "border-border bg-muted/60 text-muted-foreground",
    success:
      "border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300",
    warning:
      "border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300",
    danger:
      "border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300",
    info:
      "border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/40 dark:bg-sky-950/30 dark:text-sky-300",
    primary:
      "border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900/40 dark:bg-orange-950/30 dark:text-orange-300",
  };
  const pill = (
    <span
      className={cn(
        "inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[10px] font-medium tracking-wide",
        uppercase && "uppercase",
        map[tone],
        description && "cursor-help",
        className,
      )}
      data-testid={testId}
      title={description}
    >
      {children}
    </span>
  );
  if (!description) return pill;
  return (
    <TooltipProvider delayDuration={200}>
      <Tooltip>
        <TooltipTrigger asChild>{pill}</TooltipTrigger>
        <TooltipContent side="top" className="max-w-xs text-[11px]">
          {description}
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// MetricCard — concise KPI tile. Big value + label, optional delta, optional
// secondary metadata. No charts inside.
// ─────────────────────────────────────────────────────────────────────────────
export function MetricCard({
  label,
  value,
  meta,
  trend,
  delta,
  tone = "neutral",
  testId,
}: {
  label: string;
  value: string | number;
  meta?: string;
  trend?: "up" | "down" | "flat";
  delta?: string;
  tone?: "neutral" | "warning" | "danger" | "success";
  testId?: string;
}) {
  const toneClass =
    tone === "danger"
      ? "text-rose-600 dark:text-rose-400"
      : tone === "warning"
        ? "text-amber-700 dark:text-amber-300"
        : tone === "success"
          ? "text-emerald-700 dark:text-emerald-300"
          : "text-foreground";
  const TrendIcon = trend === "down" ? ArrowDown : ArrowUp;
  return (
    <div
      className="rounded-xl border border-border bg-card p-4"
      data-testid={testId}
    >
      <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
        {label}
      </p>
      <p className={cn("mt-2 text-2xl font-semibold tabular-nums tracking-tight", toneClass)}>
        {value}
      </p>
      <div className="mt-1.5 flex items-center justify-between text-[11px]">
        {trend && delta ? (
          <span
            className={cn(
              "inline-flex items-center gap-1 font-medium",
              trend === "up"
                ? "text-emerald-600 dark:text-emerald-400"
                : trend === "down"
                  ? "text-rose-600 dark:text-rose-400"
                  : "text-muted-foreground",
            )}
          >
            {trend !== "flat" && <TrendIcon className="size-3" />}
            {delta}
          </span>
        ) : (
          <span />
        )}
        {meta && <span className="text-muted-foreground">{meta}</span>}
      </div>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// AccountTabs — uniform tab strip styling. Wraps shadcn Tabs.
// ─────────────────────────────────────────────────────────────────────────────
export function AccountTabs<T extends string>({
  value,
  onChange,
  options,
}: {
  value: T;
  onChange: (v: T) => void;
  options: { value: T; label: string; testId?: string; count?: number }[];
}) {
  return (
    <Tabs value={value} onValueChange={(v) => onChange(v as T)}>
      <TabsList className="h-10 flex-wrap gap-1 bg-muted/60 p-1">
        {options.map((o) => (
          <TabsTrigger
            key={o.value}
            value={o.value}
            className="h-8 px-3 text-xs font-medium"
            data-testid={o.testId}
          >
            {o.label}
            {typeof o.count === "number" && (
              <span className="ml-1.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-background px-1 text-[10px] font-semibold text-muted-foreground">
                {o.count}
              </span>
            )}
          </TabsTrigger>
        ))}
      </TabsList>
    </Tabs>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// ActionListItem — clickable row in a "what needs attention" list.
// Has explicit primary button — the row itself is not the only target.
// ─────────────────────────────────────────────────────────────────────────────
// ─────────────────────────────────────────────────────────────────────────────
// EmptyState — uniform empty/zero state.
// ─────────────────────────────────────────────────────────────────────────────
export function EmptyState({
  icon: Icon = CheckCircle2,
  title,
  description,
  cta,
  tone = "neutral",
  testId,
  compact = false,
}: {
  icon?: LucideIcon;
  title: string;
  description?: string;
  cta?: { label: string; onClick: () => void; testId?: string };
  tone?: "neutral" | "success";
  testId?: string;
  compact?: boolean;
}) {
  const iconClass =
    tone === "success"
      ? "bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400"
      : "bg-muted text-muted-foreground";
  return (
    <div
      className={cn(
        "flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card text-center",
        compact ? "px-6 py-10" : "px-6 py-14",
      )}
      data-testid={testId ?? "empty-state"}
    >
      <div
        className={cn(
          "grid size-12 place-items-center rounded-full",
          iconClass,
        )}
      >
        <Icon className="size-5" />
      </div>
      <h3 className="mt-4 text-base font-semibold text-foreground">{title}</h3>
      {description && (
        <p className="mt-1 max-w-md text-sm text-muted-foreground">{description}</p>
      )}
      {cta && (
        <Button onClick={cta.onClick} className="mt-4" data-testid={cta.testId}>
          {cta.label}
        </Button>
      )}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// DetailSheetHeader — uniform header inside Sheet detail panels.
// ─────────────────────────────────────────────────────────────────────────────
export function DetailSheetHeader({
  eyebrow,
  title,
  subtitle,
  meta,
}: {
  eyebrow?: string;
  title: string;
  subtitle?: string;
  meta?: ReactNode;
}) {
  return (
    <div className="border-b border-border pb-4">
      {eyebrow && (
        <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">
          {eyebrow}
        </p>
      )}
      <p className="mt-1 text-base font-semibold text-foreground">{title}</p>
      {subtitle && (
        <p className="mt-0.5 text-xs text-muted-foreground">{subtitle}</p>
      )}
      {meta && <div className="mt-2">{meta}</div>}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// HeroPanel — subtle top hero/orientation panel. Calmer than gradient.
// Use only on the first paint of a top-level page when it improves orientation.
// ─────────────────────────────────────────────────────────────────────────────
// ─────────────────────────────────────────────────────────────────────────────
// InlineHelp — small contextual hint paragraph.
// ─────────────────────────────────────────────────────────────────────────────
export function InlineHelp({
  icon: Icon,
  children,
}: {
  icon?: LucideIcon;
  children: ReactNode;
}) {
  return (
    <p className="inline-flex items-start gap-1.5 text-[11px] leading-relaxed text-muted-foreground">
      {Icon && <Icon className="mt-0.5 size-3 shrink-0" />}
      <span>{children}</span>
    </p>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// PageShell — wraps pages with consistent spacing. Optional max width.
// ─────────────────────────────────────────────────────────────────────────────
export function PageShell({
  children,
  className,
  testId,
}: {
  children: ReactNode;
  className?: string;
  testId?: string;
}) {
  return (
    <div className={cn("space-y-6", className)} data-testid={testId}>
      {children}
    </div>
  );
}

// Re-export so old imports keep working if any.
export type SharedComponent = ComponentType<unknown>;

// ─────────────────────────────────────────────────────────────────────────────
// SoftCard — lighter alt to AppCard. Lighter border (token), no dark fill.
// ─────────────────────────────────────────────────────────────────────────────
export function SoftCard({
  children,
  className,
  padded = true,
  testId,
  onClick,
}: {
  children: ReactNode;
  className?: string;
  padded?: boolean;
  testId?: string;
  onClick?: () => void;
}) {
  return (
    <section
      onClick={onClick}
      className={cn(
        "rounded-xl border border-border bg-card text-card-foreground transition",
        padded && "p-4 md:p-5",
        onClick && "cursor-pointer hover:border-strong",
        className,
      )}
      data-testid={testId}
    >
      {children}
    </section>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// DataPanel — a simple labeled panel with header row + slot for body.
// ─────────────────────────────────────────────────────────────────────────────
// ─────────────────────────────────────────────────────────────────────────────
// PageHero — a small hero strip used at the top of dashboards.
// Subtle gradient, low-saturation. No giant orange bars.
// ─────────────────────────────────────────────────────────────────────────────
// ─────────────────────────────────────────────────────────────────────────────
// PriorityItem — single high-priority row inside a Start Here panel.
// ─────────────────────────────────────────────────────────────────────────────
// ─────────────────────────────────────────────────────────────────────────────
// EmptyCompact — collapsed empty state. Single row, not a giant centered block.
// ─────────────────────────────────────────────────────────────────────────────

// ─────────────────────────────────────────────────────────────────────────────
// PageFrame — top-of-page wrapper. Combines PageShell spacing + standard PageHeader
// in one. Replace ad-hoc <div className="space-y-X"><h1>…</h1>…</div> patterns.
// ─────────────────────────────────────────────────────────────────────────────
// ─────────────────────────────────────────────────────────────────────────────
// SectionCard — labeled card with header strip. Single canonical pattern for any
// "section with title + body" used by both client + admin (Boards, Clients,
// Settings, Today queues). Soft border, soft header, body slot.
// ─────────────────────────────────────────────────────────────────────────────
export function SectionCard({
  title,
  hint,
  trailing,
  children,
  className,
  bodyClassName,
  testId,
  tone,
}: {
  title?: string;
  hint?: string;
  trailing?: ReactNode;
  children: ReactNode;
  className?: string;
  bodyClassName?: string;
  testId?: string;
  // Optional tone for the leading dot in the title row
  tone?: "neutral" | "primary" | "warning" | "success" | "info" | "danger";
}) {
  const dot = tone
    ? {
        primary: "bg-orange-500",
        warning: "bg-amber-500",
        success: "bg-emerald-500",
        info: "bg-sky-500",
        danger: "bg-rose-500",
        neutral: "bg-muted-foreground/40",
      }[tone]
    : null;
  return (
    <section
      className={cn(
        "overflow-hidden rounded-xl border border-border bg-card text-card-foreground",
        className,
      )}
      data-testid={testId}
    >
      {(title || trailing) && (
        <div className="flex items-center justify-between gap-3 border-b border-border bg-surface-elevated px-5 py-3">
          <div className="min-w-0 flex items-center gap-2.5">
            {dot && <span className={cn("size-1.5 rounded-full", dot)} aria-hidden />}
            <div className="min-w-0">
              {title && <h2 className="truncate text-sm font-semibold text-foreground">{title}</h2>}
              {hint && <p className="mt-0.5 truncate text-[11px] text-muted-foreground">{hint}</p>}
            </div>
          </div>
          {trailing && <div className="shrink-0">{trailing}</div>}
        </div>
      )}
      <div className={cn(bodyClassName)}>{children}</div>
    </section>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// DataListRow — single canonical row inside any list (admin Boards table,
// client Work, admin Today queue, admin Clients work tab). Replaces ad-hoc
// <li class="flex …"> implementations.
// ─────────────────────────────────────────────────────────────────────────────
export function DataListRow({
  icon: Icon,
  iconTone = "neutral",
  title,
  description,
  meta,
  trailing,
  primary,
  secondary,
  onOpen,
  testId,
  href,
}: {
  icon?: LucideIcon;
  iconTone?: "neutral" | "primary" | "success" | "warning" | "danger" | "info";
  title: ReactNode;
  description?: ReactNode;
  meta?: ReactNode;
  trailing?: ReactNode;
  primary?: { label: string; onClick: () => void; testId?: string };
  secondary?: { label: string; onClick: () => void; testId?: string };
  onOpen?: () => void;
  testId?: string;
  href?: string;
}) {
  const iconCls = {
    neutral: "border-border bg-muted text-muted-foreground",
    primary:
      "border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900/40 dark:bg-orange-950/40 dark:text-orange-300",
    success:
      "border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-300",
    warning:
      "border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-300",
    danger:
      "border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-300",
    info:
      "border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/40 dark:bg-sky-950/40 dark:text-sky-300",
  }[iconTone];

  const body = (
    <div
      className={cn(
        "flex flex-col gap-3 px-5 py-4 md:flex-row md:items-center md:justify-between",
        (onOpen || href) && "transition hover:bg-muted/40",
      )}
      data-testid={testId}
    >
      <div className="flex min-w-0 flex-1 items-start gap-3">
        {Icon && (
          <span
            className={cn(
              "mt-0.5 grid size-8 shrink-0 place-items-center rounded-md border",
              iconCls,
            )}
            aria-hidden
          >
            <Icon className="size-4" />
          </span>
        )}
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
            <p className="truncate text-sm font-semibold leading-snug text-foreground">{title}</p>
            {meta && <span className="text-[11px] text-muted-foreground">{meta}</span>}
          </div>
          {description && (
            <p className="mt-0.5 line-clamp-2 text-[11px] text-muted-foreground">{description}</p>
          )}
        </div>
      </div>
      <div className="flex shrink-0 items-center gap-2 md:ml-3">
        {trailing}
        {secondary && (
          <Button
            variant="ghost"
            size="sm"
            onClick={(e) => {
              e.stopPropagation();
              secondary.onClick();
            }}
            data-testid={secondary.testId}
          >
            {secondary.label}
          </Button>
        )}
        {primary && (
          <Button
            size="sm"
            className="gap-1"
            onClick={(e) => {
              e.stopPropagation();
              primary.onClick();
            }}
            data-testid={primary.testId}
          >
            {primary.label}
            <ChevronRight className="size-3.5" />
          </Button>
        )}
      </div>
    </div>
  );

  if (onOpen) {
    return (
      <button
        type="button"
        onClick={onOpen}
        className="block w-full text-left"
      >
        {body}
      </button>
    );
  }
  if (href) {
    return (
      <a href={href} target="_blank" rel="noopener noreferrer" className="block w-full">
        {body}
      </a>
    );
  }
  return body;
}

// ─────────────────────────────────────────────────────────────────────────────
// DataList — wraps a list of DataListRows with consistent dividers + empty state.
// ─────────────────────────────────────────────────────────────────────────────
export function DataList({
  children,
  emptyText,
  testId,
}: {
  children: ReactNode;
  emptyText?: string;
  testId?: string;
}) {
  const arr = Array.isArray(children) ? children : [children];
  const filtered = arr.filter(Boolean);
  if (filtered.length === 0) {
    return (
      <p
        className="px-5 py-6 text-center text-xs text-muted-foreground"
        data-testid={testId ?? "data-list-empty"}
      >
        {emptyText ?? "Nothing here right now."}
      </p>
    );
  }
  return (
    <ul
      className="divide-y divide-border/60"
      data-testid={testId}
    >
      {filtered.map((c, i) => (
        <li key={i}>{c}</li>
      ))}
    </ul>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// ConnectedActivityLine — small "Client replied · 2h ago" line, color-coded
// by source role. Used on admin Boards and Today.
// ─────────────────────────────────────────────────────────────────────────────
export function ConnectedActivityLine({
  role,
  verb,
  ago,
  testId,
}: {
  role: "client" | "admin" | "system";
  verb: string;
  ago: string;
  testId?: string;
}) {
  const cls =
    role === "client"
      ? "text-orange-700 dark:text-orange-300"
      : role === "system"
      ? "text-muted-foreground"
      : "text-muted-foreground";
  const subject = role === "client" ? "Client" : role === "admin" ? "You" : "System";
  return (
    <p className={cn("line-clamp-1 text-[10.5px]", cls)} data-testid={testId}>
      {subject} {verb} · {ago}
    </p>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// DetailRail — right rail wrapper used on Clients, Today right-rail. Just spacing.
// ─────────────────────────────────────────────────────────────────────────────
export function DetailRail({
  children,
  className,
  testId,
}: {
  children: ReactNode;
  className?: string;
  testId?: string;
}) {
  return (
    <aside className={cn("space-y-4", className)} data-testid={testId}>
      {children}
    </aside>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// SettingsPanel — common shape for any Settings tab body. Replaces "AppCard
// with section header + content" repetition. Provides consistent form padding.
// ─────────────────────────────────────────────────────────────────────────────
export function SettingsPanel({
  title,
  hint,
  trailing,
  status,
  children,
  footer,
  testId,
}: {
  title: string;
  hint?: string;
  trailing?: ReactNode;
  status?: ReactNode;
  children: ReactNode;
  footer?: ReactNode;
  testId?: string;
}) {
  return (
    <section
      className="overflow-hidden rounded-xl border border-border bg-card"
      data-testid={testId}
    >
      <div className="flex flex-wrap items-start justify-between gap-3 border-b border-border px-5 py-4">
        <div className="min-w-0">
          <p className="text-sm font-semibold">{title}</p>
          {hint && <p className="mt-1 max-w-prose text-xs text-muted-foreground">{hint}</p>}
        </div>
        <div className="flex shrink-0 flex-col items-end gap-1.5">
          {status}
          {trailing}
        </div>
      </div>
      <div className="p-5">{children}</div>
      {footer && (
        <div className="flex flex-wrap items-center justify-end gap-2 border-t border-border bg-surface-subtle px-5 py-3">
          {footer}
        </div>
      )}
    </section>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// IntegrationCard — uniform connection card (Slack, HighLevel, Woo, Leadsie, etc.)
// Logo slot · name · description · connection state · primary action.
// ─────────────────────────────────────────────────────────────────────────────
export function IntegrationCard({
  icon,
  name,
  description,
  connected,
  state,
  primaryAction,
  secondaryAction,
  meta,
  testId,
}: {
  icon: ReactNode;
  name: string;
  description?: string;
  connected: boolean;
  // optional override label, e.g. "Needs attention"
  state?: { label: string; tone: "success" | "warning" | "danger" | "neutral" };
  primaryAction?: { label: string; onClick: () => void; testId?: string };
  secondaryAction?: { label: string; onClick: () => void; testId?: string };
  meta?: ReactNode;
  testId?: string;
}) {
  const tone = state?.tone ?? (connected ? "success" : "neutral");
  const label = state?.label ?? (connected ? "Connected" : "Not connected");
  return (
    <section
      className="flex flex-wrap items-center gap-3 rounded-xl border border-border bg-card p-4"
      data-testid={testId}
    >
      <span className="grid size-10 shrink-0 place-items-center rounded-md border border-border bg-muted">
        {icon}
      </span>
      <div className="min-w-0 flex-1">
        <p className="text-sm font-semibold">{name}</p>
        {description && (
          <p className="mt-0.5 line-clamp-2 max-w-prose text-[11px] text-muted-foreground">{description}</p>
        )}
        {meta && <div className="mt-1 text-[11px] text-muted-foreground">{meta}</div>}
      </div>
      <div className="flex shrink-0 items-center gap-2">
        <StatusPill tone={tone}>{label}</StatusPill>
        {secondaryAction && (
          <Button
            variant="ghost"
            size="sm"
            onClick={secondaryAction.onClick}
            data-testid={secondaryAction.testId}
          >
            {secondaryAction.label}
          </Button>
        )}
        {primaryAction && (
          <Button
            variant="outline"
            size="sm"
            onClick={primaryAction.onClick}
            data-testid={primaryAction.testId}
          >
            {primaryAction.label}
          </Button>
        )}
      </div>
    </section>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// SummaryStat — small metric pill in a stat strip. (Replaces SummaryStat copies
// in admin Boards / admin system.)
// ─────────────────────────────────────────────────────────────────────────────
export function SummaryStat({
  label,
  value,
  hint,
  tone = "neutral",
  testId,
}: {
  label: string;
  value: ReactNode;
  hint?: string;
  tone?: "neutral" | "primary" | "warning" | "success" | "danger" | "info";
  testId?: string;
}) {
  const dot = {
    neutral: "bg-muted-foreground/40",
    primary: "bg-orange-500",
    warning: "bg-amber-500",
    success: "bg-emerald-500",
    danger: "bg-rose-500",
    info: "bg-sky-500",
  }[tone];
  const valueCls = {
    neutral: "text-foreground",
    primary: "text-orange-700 dark:text-orange-300",
    warning: "text-amber-700 dark:text-amber-300",
    success: "text-emerald-700 dark:text-emerald-300",
    danger: "text-rose-700 dark:text-rose-300",
    info: "text-sky-700 dark:text-sky-300",
  }[tone];
  return (
    <div
      className="rounded-xl border border-border bg-card px-4 py-3"
      data-testid={testId}
    >
      <div className="flex items-center gap-1.5">
        <span className={cn("size-1.5 rounded-full", dot)} aria-hidden />
        <p className="text-[10.5px] font-semibold uppercase tracking-wide text-muted-foreground">{label}</p>
      </div>
      <p className={cn("mt-1.5 text-2xl font-semibold tabular-nums tracking-tight", valueCls)}>{value}</p>
      {hint && <p className="mt-0.5 text-[11px] text-muted-foreground">{hint}</p>}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// QueueLane — vertical lane used on admin Today (priority lanes). Replaces the
// heavy gradient "QueueGroup" with a cleaner card matching client visual lang.
// ─────────────────────────────────────────────────────────────────────────────
export function QueueLane({
  icon: Icon,
  title,
  subtitle,
  count,
  tone = "neutral",
  emptyText = "Nothing here right now.",
  rows,
  testId,
}: {
  icon?: LucideIcon;
  title: string;
  subtitle?: string;
  count: number;
  tone?: "primary" | "info" | "success" | "danger" | "warning" | "neutral";
  emptyText?: string;
  rows: {
    id: string;
    title: ReactNode;
    sub?: ReactNode;
    meta?: ReactNode;
    onClick?: () => void;
  }[];
  testId?: string;
}) {
  const dotCls = {
    primary: "bg-orange-500",
    info: "bg-sky-500",
    success: "bg-emerald-500",
    danger: "bg-rose-500",
    warning: "bg-amber-500",
    neutral: "bg-muted-foreground/40",
  }[tone];
  const iconCls = {
    primary: "bg-orange-50 text-orange-600 dark:bg-orange-950/40 dark:text-orange-300",
    info: "bg-sky-50 text-sky-600 dark:bg-sky-950/40 dark:text-sky-300",
    success: "bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-300",
    danger: "bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-300",
    warning: "bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-300",
    neutral: "bg-muted text-muted-foreground",
  }[tone];
  return (
    <section
      className="overflow-hidden rounded-xl border border-border bg-card"
      data-testid={testId}
    >
      <div className="flex items-center justify-between gap-3 border-b border-border bg-surface-elevated px-5 py-3">
        <div className="flex min-w-0 items-center gap-2.5">
          {Icon ? (
            <span className={cn("grid size-7 shrink-0 place-items-center rounded-md", iconCls)}>
              <Icon className="size-3.5" />
            </span>
          ) : (
            <span className={cn("size-1.5 rounded-full", dotCls)} aria-hidden />
          )}
          <div className="min-w-0">
            <h3 className="truncate text-sm font-semibold">{title}</h3>
            {subtitle && <p className="mt-0.5 truncate text-[11px] text-muted-foreground">{subtitle}</p>}
          </div>
        </div>
        <span
          className={cn(
            "inline-flex h-6 min-w-[28px] shrink-0 items-center justify-center rounded-full px-2 text-[11px] font-semibold tabular-nums",
            count > 0
              ? "bg-foreground text-background"
              : "bg-muted text-muted-foreground",
          )}
        >
          {count}
        </span>
      </div>
      {rows.length === 0 ? (
        <div className="flex flex-col items-center justify-center gap-1.5 px-5 py-8 text-center">
          <CheckCircle2 className="size-5 text-emerald-500" />
          <p className="text-xs text-muted-foreground">{emptyText}</p>
        </div>
      ) : (
        <ul className="divide-y divide-border/60">
          {rows.map((r) => (
            <li key={r.id}>
              <button
                type="button"
                onClick={r.onClick}
                className="group flex w-full items-center gap-3 px-5 py-3 text-left transition hover:bg-muted/40"
                data-testid={r.id}
              >
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-medium leading-snug text-foreground">{r.title}</p>
                  {r.sub && (
                    <p className="mt-0.5 truncate text-[11px] text-muted-foreground">{r.sub}</p>
                  )}
                </div>
                {r.meta && (
                  <span className="shrink-0 text-[11px] tabular-nums text-muted-foreground">
                    {r.meta}
                  </span>
                )}
                <ChevronRight className="size-4 shrink-0 text-muted-foreground transition group-hover:translate-x-0.5" />
              </button>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// ExternalLinkRow — uniform row for "open in WordPress / WooCommerce / HighLevel /
// Slack" external links inside Settings panels.
// ─────────────────────────────────────────────────────────────────────────────
export function ExternalLinkRow({
  label,
  href,
  description,
  testId,
}: {
  label: string;
  href: string;
  description?: string;
  testId?: string;
}) {
  return (
    <a
      href={href}
      target="_blank"
      rel="noopener noreferrer"
      className="flex items-center justify-between gap-2 rounded-lg border border-border bg-card px-3 py-2.5 text-xs font-medium transition hover:border-primary/40 hover:bg-muted/40"
      data-testid={testId}
    >
      <div className="min-w-0">
        <span className="block truncate">{label}</span>
        {description && (
          <span className="block truncate text-[10.5px] font-normal text-muted-foreground">{description}</span>
        )}
      </div>
      <ExternalLink className="size-3.5 shrink-0 text-muted-foreground" />
    </a>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// PriorityBanner — top-of-page "Start here" nudge bar. Used on Today + Home.
// One canonical look — orange tint, icon, title/sub, primary CTA.
// ─────────────────────────────────────────────────────────────────────────────
export function PriorityBanner({
  icon: Icon,
  eyebrow = "Start here",
  title,
  subtitle,
  cta,
  testId,
  tone = "primary",
}: {
  icon?: LucideIcon;
  eyebrow?: string;
  title: ReactNode;
  subtitle?: ReactNode;
  cta?: { label: string; onClick: () => void; testId?: string };
  testId?: string;
  tone?: "primary" | "success" | "warning" | "danger";
}) {
  const ringCls = {
    primary: "border-primary-soft bg-gradient-priority",
    success: "border-emerald-200/70 bg-emerald-50/40 dark:border-emerald-900/40 dark:bg-emerald-950/20",
    warning: "border-amber-200/70 bg-amber-50/40 dark:border-amber-900/40 dark:bg-amber-950/20",
    danger: "border-rose-200/70 bg-rose-50/40 dark:border-rose-900/40 dark:bg-rose-950/20",
  }[tone];
  const iconCls = {
    primary: "bg-primary-soft-strong text-primary dark:bg-primary-soft dark:text-primary",
    success: "bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300",
    warning: "bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300",
    danger: "bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300",
  }[tone];
  const eyebrowCls = {
    primary: "text-primary",
    success: "text-emerald-700 dark:text-emerald-300",
    warning: "text-amber-700 dark:text-amber-300",
    danger: "text-rose-700 dark:text-rose-300",
  }[tone];
  return (
    <section
      className={cn("flex flex-wrap items-center gap-3 rounded-xl border p-4 shadow-sm md:p-5", ringCls)}
      data-testid={testId}
    >
      {Icon && (
        <span className={cn("grid size-10 shrink-0 place-items-center rounded-md", iconCls)}>
          <Icon className="size-5" />
        </span>
      )}
      <div className="min-w-0 flex-1">
        <p className={cn("text-[11px] font-semibold uppercase tracking-[0.16em]", eyebrowCls)}>{eyebrow}</p>
        <p className="mt-0.5 text-sm font-semibold leading-tight">{title}</p>
        {subtitle && (
          <p className="mt-0.5 line-clamp-1 text-xs text-muted-foreground">{subtitle}</p>
        )}
      </div>
      {cta && (
        <Button onClick={cta.onClick} className="shrink-0 gap-1.5" data-testid={cta.testId}>
          {cta.label}
          <ArrowRight className="size-3.5" />
        </Button>
      )}
    </section>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// SectionLink — small "Settings" / "All" / "View" trailing link in section
// headers. Replaces ad-hoc <button className="text-xs ...">Settings</button>.
// Renders as either a link-styled button (onClick) or anchor (href).
// ─────────────────────────────────────────────────────────────────────────────
export function SectionLink({
  label,
  onClick,
  href,
  trailing = "chevron",
  testId,
}: {
  label: string;
  onClick?: () => void;
  href?: string;
  trailing?: "chevron" | "external" | "none";
  testId?: string;
}) {
  const trailIcon =
    trailing === "chevron" ? (
      <ChevronRight className="size-3.5 opacity-70" />
    ) : trailing === "external" ? (
      <ExternalLink className="size-3 opacity-70" />
    ) : null;
  const cls =
    "inline-flex items-center gap-0.5 text-xs font-medium text-foreground/80 transition hover:text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring rounded";
  if (href) {
    return (
      <a
        href={href}
        target={href.startsWith("http") ? "_blank" : undefined}
        rel={href.startsWith("http") ? "noopener noreferrer" : undefined}
        className={cls}
        data-testid={testId}
      >
        {label}
        {trailIcon}
      </a>
    );
  }
  return (
    <button type="button" onClick={onClick} className={cls} data-testid={testId}>
      {label}
      {trailIcon}
    </button>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// IconActionButton — single canonical icon-only button. Forces aria-label and
// a tooltip — no anonymous icon clicks anywhere in the app.
// ─────────────────────────────────────────────────────────────────────────────
export function IconActionButton({
  icon: Icon,
  label,
  onClick,
  variant = "ghost",
  size = "icon",
  className,
  disabled,
  testId,
}: {
  icon: LucideIcon;
  /** Required — used for aria-label, title, and tooltip. */
  label: string;
  onClick?: () => void;
  variant?: ButtonProps["variant"];
  size?: ButtonProps["size"];
  className?: string;
  disabled?: boolean;
  testId?: string;
}) {
  return (
    <TooltipProvider delayDuration={250}>
      <Tooltip>
        <TooltipTrigger asChild>
          <Button
            type="button"
            variant={variant}
            size={size}
            className={className}
            onClick={onClick}
            disabled={disabled}
            aria-label={label}
            title={label}
            data-testid={testId}
          >
            <Icon className="size-4" />
          </Button>
        </TooltipTrigger>
        <TooltipContent side="bottom" className="text-[11px]">
          {label}
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// ButtonGroup — wraps related actions. Enforces visual rhythm: gap-2, items
// align right by default. Use this around any set of 2-3 buttons that belong
// together (page header actions, dialog footer, row trailing). Caller is
// responsible for collapsing 4+ actions into a menu — convention, not enforced.
// ─────────────────────────────────────────────────────────────────────────────
export function ButtonGroup({
  children,
  align = "end",
  className,
  testId,
}: {
  children: ReactNode;
  align?: "start" | "end" | "between";
  className?: string;
  testId?: string;
}) {
  return (
    <div
      className={cn(
        "flex flex-wrap items-center gap-2",
        align === "end" && "justify-end",
        align === "between" && "justify-between",
        className,
      )}
      data-testid={testId}
    >
      {children}
    </div>
  );
}
