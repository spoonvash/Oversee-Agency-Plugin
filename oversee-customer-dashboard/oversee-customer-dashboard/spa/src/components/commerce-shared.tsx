// Commerce-specific shared primitives.
//
// These primitives back the Services storefront and Account hub.
// They exist so the page files don't redeclare card/filter/visual scaffolding
// inline. Zero inline styles — every visual is driven by Tailwind classes
// referencing the design tokens in index.css / tailwind.config.ts.

import { useState, type ReactNode } from "react";
import type { LucideIcon } from "lucide-react";
import {
  ArrowRight,
  ImageOff,
  Plus,
  Repeat,
  SlidersHorizontal,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { StatusPill, type StatusTone } from "@/components/shared";

// ─────────────────────────────────────────────────────────────────────────────
// FilterChipGroup — accessible chip-style filter group. Renders an actual
// <button> per option so screen-readers + keyboards work; selected state is
// communicated by aria-pressed AND by visible style. Replaces ad-hoc
// "looks-clickable text" filter lists.
// ─────────────────────────────────────────────────────────────────────────────
export type FilterOption = { value: string; label: string; count?: number };

export function FilterChipGroup({
  label,
  value,
  options,
  onChange,
  testId,
  variant = "chip",
}: {
  label?: string;
  value: string;
  options: FilterOption[];
  onChange: (v: string) => void;
  testId?: string;
  /** "chip" — pill-shaped chips suited for the storefront top toolbar.
   *  "stack" — vertical button-style stack used in sidebars. */
  variant?: "chip" | "stack";
}) {
  if (variant === "stack") {
    return (
      <div data-testid={testId}>
        {label && (
          <p className="mb-2 text-[10.5px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">
            {label}
          </p>
        )}
        <div className="flex flex-col gap-1">
          {options.map((o) => {
            const selected = value === o.value;
            return (
              <button
                key={o.value}
                type="button"
                onClick={() => onChange(o.value)}
                aria-pressed={selected}
                className={cn(
                  "inline-flex w-full items-center justify-between rounded-md border px-2.5 py-1.5 text-left text-xs transition",
                  selected
                    ? "border-primary/40 bg-primary-soft text-foreground shadow-sm"
                    : "border-border bg-card text-muted-foreground hover:border-strong hover:text-foreground",
                )}
                data-testid={`${testId}-${o.value}`}
              >
                <span className="truncate">{o.label}</span>
                {typeof o.count === "number" && (
                  <span
                    className={cn(
                      "ml-2 inline-flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-semibold tabular-nums",
                      selected
                        ? "bg-primary text-primary-foreground"
                        : "bg-muted text-muted-foreground",
                    )}
                  >
                    {o.count}
                  </span>
                )}
              </button>
            );
          })}
        </div>
      </div>
    );
  }
  return (
    <div data-testid={testId}>
      {label && (
        <p className="mb-1.5 text-[10.5px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">
          {label}
        </p>
      )}
      <div className="flex flex-wrap gap-1.5">
        {options.map((o) => {
          const selected = value === o.value;
          return (
            <button
              key={o.value}
              type="button"
              onClick={() => onChange(o.value)}
              aria-pressed={selected}
              className={cn(
                "inline-flex items-center gap-1 rounded-full border px-3 py-1 text-[11px] font-medium transition",
                selected
                  ? "border-primary bg-primary text-primary-foreground shadow-sm"
                  : "border-border bg-card text-muted-foreground hover:border-strong hover:text-foreground",
              )}
              data-testid={`${testId}-${o.value}`}
            >
              <span>{o.label}</span>
              {typeof o.count === "number" && (
                <span
                  className={cn(
                    "inline-flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-semibold tabular-nums",
                    selected
                      ? "bg-primary-foreground/20 text-primary-foreground"
                      : "bg-muted text-muted-foreground",
                  )}
                >
                  {o.count}
                </span>
              )}
            </button>
          );
        })}
      </div>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// ServiceVisual — image with category-driven fallback band. Avoids monotony
// when many products share a placeholder image: same product always renders
// the same color/initial, but different categories look different.
// ─────────────────────────────────────────────────────────────────────────────
const VISUAL_STYLES = [
  // (band, accent dot)
  "bg-gradient-to-br from-orange-100 via-amber-50 to-rose-50 text-orange-700 dark:from-orange-950/60 dark:via-amber-950/40 dark:to-rose-950/40 dark:text-orange-300",
  "bg-gradient-to-br from-sky-100 via-indigo-50 to-violet-50 text-sky-700 dark:from-sky-950/60 dark:via-indigo-950/40 dark:to-violet-950/40 dark:text-sky-300",
  "bg-gradient-to-br from-emerald-100 via-teal-50 to-cyan-50 text-emerald-700 dark:from-emerald-950/60 dark:via-teal-950/40 dark:to-cyan-950/40 dark:text-emerald-300",
  "bg-gradient-to-br from-fuchsia-100 via-pink-50 to-rose-50 text-fuchsia-700 dark:from-fuchsia-950/60 dark:via-pink-950/40 dark:to-rose-950/40 dark:text-fuchsia-300",
  "bg-gradient-to-br from-amber-100 via-yellow-50 to-orange-50 text-amber-700 dark:from-amber-950/60 dark:via-yellow-950/40 dark:to-orange-950/40 dark:text-amber-300",
  "bg-gradient-to-br from-slate-100 via-gray-50 to-zinc-50 text-slate-700 dark:from-slate-900 dark:via-gray-950 dark:to-zinc-950 dark:text-slate-300",
];

function hashIndex(seed: string, modulo: number): number {
  let h = 0;
  for (let i = 0; i < seed.length; i++) {
    h = (h << 5) - h + seed.charCodeAt(i);
    h |= 0;
  }
  return Math.abs(h) % modulo;
}

export function ServiceVisual({
  src,
  alt,
  seed,
  initials,
  ratio = "4/3",
  showImage = true,
  testId,
}: {
  src?: string;
  alt: string;
  /** Seed used for color rotation (typically product slug or category). */
  seed: string;
  /** Letters drawn on the fallback panel (typically the product's first 2 chars). */
  initials: string;
  ratio?: "4/3" | "16/9" | "1/1";
  showImage?: boolean;
  testId?: string;
}) {
  const [errored, setErrored] = useState(false);
  const styleIdx = hashIndex(seed, VISUAL_STYLES.length);
  const ratioCls =
    ratio === "16/9" ? "aspect-[16/9]" : ratio === "1/1" ? "aspect-square" : "aspect-[4/3]";
  const showFallback = !showImage || errored || !src;

  if (showFallback) {
    return (
      <div
        className={cn(
          "relative w-full overflow-hidden",
          ratioCls,
          VISUAL_STYLES[styleIdx],
        )}
        data-testid={testId}
        aria-label={alt}
        role="img"
      >
        <span className="pointer-events-none absolute inset-0 flex items-center justify-center text-[44px] font-semibold tracking-tight opacity-90">
          {initials.toUpperCase().slice(0, 2)}
        </span>
        <span className="pointer-events-none absolute inset-x-4 bottom-3 h-px bg-current opacity-30" />
        <span className="pointer-events-none absolute right-4 bottom-4 grid size-8 place-items-center rounded-full bg-current/10 text-current">
          <ImageOff className="size-4 opacity-60" />
        </span>
      </div>
    );
  }
  return (
    <div className={cn("relative w-full overflow-hidden bg-muted", ratioCls)} data-testid={testId}>
      <img
        src={src}
        alt={alt}
        onError={() => setErrored(true)}
        className="h-full w-full object-cover transition group-hover:scale-105"
        loading="lazy"
      />
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// ServiceCard — single canonical product/service card for the storefront.
// Replaces the local ProductCard previously declared in commerce.tsx.
// ─────────────────────────────────────────────────────────────────────────────
export type ServiceCardProps = {
  id: string;
  name: string;
  category: string;
  shortDescription: string;
  /** Already-formatted price strings to keep the card stupid (no business logic here) */
  priceMain: string;
  priceSub?: string;
  /** "subscription" | "one-time" | "configurable" — controls badges/CTA copy */
  kind: "subscription" | "one-time" | "configurable" | "configurable-subscription";
  /** Number of selectable variants (configurable products only). 0/undefined hides chip. */
  variantCount?: number;
  imageUrl?: string;
  imageAlt?: string;
  badge?: string;
  /** Short list of "what's included" — first 2 are rendered. */
  included?: string[];
  onOpen: () => void;
  testId?: string;
};

export function ServiceCard(p: ServiceCardProps) {
  const isSubscription = p.kind === "subscription" || p.kind === "configurable-subscription";
  const isConfigurable = p.kind === "configurable" || p.kind === "configurable-subscription";
  const ctaIcon = isConfigurable ? <SlidersHorizontal className="size-3.5" /> : <Plus className="size-3.5" />;
  const ctaLabel = isConfigurable ? "Configure" : "Add";
  return (
    <article
      className="group flex h-full flex-col overflow-hidden rounded-xl border border-border bg-card text-card-foreground transition hover:-translate-y-0.5 hover:border-strong hover:shadow-md"
      data-testid={p.testId ?? `service-card-${p.id}`}
    >
      <button
        type="button"
        onClick={p.onOpen}
        className="block w-full text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        aria-label={`Open ${p.name}`}
      >
        <div className="relative">
          <ServiceVisual
            src={p.imageUrl}
            alt={p.imageAlt ?? p.name}
            seed={`${p.category}-${p.id}`}
            initials={p.name}
          />
          {/* Top-left: category only (truncates if long). */}
          <span className="absolute left-3 top-3 inline-flex max-w-[60%] items-center truncate rounded-full border border-border/60 bg-background/90 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-foreground backdrop-blur">
            {p.category}
          </span>
          {/* Top-right: kind chip (Recurring | One-time). */}
          <span className="absolute right-3 top-3">
            {isSubscription ? (
              <StatusPill tone="primary" uppercase={false}>
                <Repeat className="size-2.5" />
                Recurring
              </StatusPill>
            ) : (
              <span className="inline-flex items-center rounded-full border border-border/60 bg-background/90 px-2 py-0.5 text-[10px] font-medium text-foreground backdrop-blur">
                One-time
              </span>
            )}
          </span>
          {/* Bottom-left: highlight badge ("POPULAR", "IN YOUR PLAN"…) when present. */}
          {p.badge && (
            <span className="absolute bottom-3 left-3 inline-flex items-center rounded-full border border-orange-200 bg-orange-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-orange-700 dark:border-orange-900/40 dark:bg-orange-950/40 dark:text-orange-300">
              {p.badge}
            </span>
          )}
        </div>
      </button>
      <div className="flex flex-1 flex-col gap-3 p-5">
        <div>
          <h3 className="text-[15px] font-semibold leading-snug text-foreground">{p.name}</h3>
          <p className="mt-1 line-clamp-2 text-xs leading-relaxed text-muted-foreground">
            {p.shortDescription}
          </p>
        </div>
        {(p.included && p.included.length > 0) || (isConfigurable && p.variantCount) ? (
          <ul className="flex flex-wrap gap-1.5 text-[10.5px]">
            {isConfigurable && p.variantCount ? (
              <li className="inline-flex items-center gap-1 rounded-md border border-border bg-muted/60 px-2 py-0.5 text-muted-foreground">
                <SlidersHorizontal className="size-2.5" />
                {p.variantCount} variants
              </li>
            ) : null}
            {p.included?.slice(0, 2).map((inc, idx) => (
              <li
                key={`${inc}-${idx}`}
                className="inline-flex items-center gap-1 rounded-md border border-border bg-muted/40 px-2 py-0.5 text-muted-foreground"
              >
                {inc}
              </li>
            ))}
          </ul>
        ) : null}
        <div className="mt-auto flex items-end justify-between gap-2 border-t border-border/60 pt-3">
          <div className="min-w-0 flex-1">
            <p className="truncate text-[15px] font-semibold tabular-nums text-foreground" title={p.priceMain}>
              {p.priceMain}
            </p>
            {p.priceSub && (
              <p className="truncate text-[11px] text-muted-foreground">{p.priceSub}</p>
            )}
          </div>
          <Button
            size="sm"
            onClick={p.onOpen}
            className="h-9 shrink-0 gap-1.5 whitespace-nowrap bg-orange-600 px-3.5 text-white hover:bg-orange-700 dark:bg-orange-500 dark:hover:bg-orange-600"
            data-testid={`button-configure-${p.id}`}
          >
            {ctaIcon}
            <span>{ctaLabel}</span>
          </Button>
        </div>
      </div>
    </article>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// AccountSummaryRow — uniform row for an account "card" item: a payment
// method, an address, a subscription, an invoice, etc. Replaces the repeated
// "<li className=…border…bg-card…flex flex-col gap-3…>" pattern.
// ─────────────────────────────────────────────────────────────────────────────
export function AccountSummaryRow({
  icon: Icon,
  iconLabel,
  iconTone = "neutral",
  title,
  subtitle,
  status,
  actions,
  testId,
}: {
  icon?: LucideIcon;
  /** When provided, overrides the icon and renders text inside the leading badge
   *  (e.g. card brand "MC" or "Visa"). */
  iconLabel?: string;
  iconTone?: "neutral" | "primary" | "success" | "warning" | "danger" | "info";
  title: ReactNode;
  subtitle?: ReactNode;
  status?: { tone: StatusTone; label: string };
  actions?: ReactNode;
  testId?: string;
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

  return (
    <li
      className="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 md:flex-row md:items-center md:gap-4 md:p-5"
      data-testid={testId}
    >
      {iconLabel ? (
        <span
          className={cn(
            "grid size-12 w-16 shrink-0 place-items-center rounded-lg border text-[11px] font-bold tracking-tight",
            iconCls,
          )}
          aria-hidden
        >
          {iconLabel}
        </span>
      ) : Icon ? (
        <span
          className={cn(
            "grid size-10 shrink-0 place-items-center rounded-lg border",
            iconCls,
          )}
          aria-hidden
        >
          <Icon className="size-5" />
        </span>
      ) : null}
      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
          {typeof title === "string" ? (
            <p className="text-sm font-semibold text-foreground">{title}</p>
          ) : (
            title
          )}
          {status && <StatusPill tone={status.tone}>{status.label}</StatusPill>}
        </div>
        {subtitle && (
          <p className="mt-0.5 text-xs text-muted-foreground">{subtitle}</p>
        )}
      </div>
      {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
    </li>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// QuickActionTile — one canonical "open this section" tile used in the
// account overview. Replaces ad-hoc <button class="…border…bg-card…"> blocks.
// ─────────────────────────────────────────────────────────────────────────────
export function QuickActionTile({
  icon: Icon,
  label,
  description,
  onClick,
  testId,
}: {
  icon: LucideIcon;
  label: string;
  description: string;
  onClick: () => void;
  testId?: string;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="flex h-full w-full flex-col items-start gap-2 rounded-xl border border-border bg-card p-4 text-left transition hover:-translate-y-0.5 hover:border-primary/40 hover:bg-primary-soft/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
      data-testid={testId}
    >
      <span className="grid size-9 place-items-center rounded-lg border border-border bg-muted text-foreground">
        <Icon className="size-4" />
      </span>
      <p className="text-sm font-semibold text-foreground">{label}</p>
      <p className="text-xs leading-relaxed text-muted-foreground">{description}</p>
      <span className="mt-auto inline-flex items-center gap-1 pt-2 text-[11px] font-medium text-primary">
        Open
        <ArrowRight className="size-3" />
      </span>
    </button>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// AttentionRow — top-of-page "what needs attention" line item used in the
// account overview. Single shape, optional CTA, color tone selects icon ring.
// ─────────────────────────────────────────────────────────────────────────────
export function AttentionRow({
  icon: Icon,
  tone = "danger",
  title,
  description,
  cta,
  testId,
}: {
  icon: LucideIcon;
  tone?: "danger" | "warning" | "primary";
  title: string;
  description: string;
  cta?: { label: string; onClick: () => void; testId?: string };
  testId?: string;
}) {
  const ringCls = {
    danger:
      "border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-300",
    warning:
      "border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-300",
    primary:
      "border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900/40 dark:bg-orange-950/40 dark:text-orange-300",
  }[tone];
  return (
    <li
      className="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 md:flex-row md:items-center md:gap-4 md:p-5"
      data-testid={testId}
    >
      <span className={cn("grid size-10 shrink-0 place-items-center rounded-lg border", ringCls)} aria-hidden>
        <Icon className="size-5" />
      </span>
      <div className="min-w-0 flex-1">
        <p className="text-sm font-semibold text-foreground">{title}</p>
        <p className="mt-0.5 text-xs text-muted-foreground">{description}</p>
      </div>
      {cta && (
        <Button size="sm" className="shrink-0 gap-1.5" onClick={cta.onClick} data-testid={cta.testId}>
          {cta.label}
          <ArrowRight className="size-3.5" />
        </Button>
      )}
    </li>
  );
}
