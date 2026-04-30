// approval-clarity.tsx
// ─────────────────────────────────────────────────────────────────────────────
// Shared UI components for showing approval ownership / status / next action.
//
// One canonical visual language across client + admin so users never have to
// guess "who is this waiting on?" or "who approved this?". All components take
// an `ApprovalMeta` (from `lib/approval-clarity`) plus the current viewer role
// and render role-appropriate wording. Color is purely semantic, mapped through
// the existing `StatusPill` component:
//
//   primary   → needs client approval (action you can take)
//   warning   → needs staff review (waiting internally)
//   info      → Oversee working / queued
//   success   → approved
//   danger    → changes requested or blocked
//   neutral   → reference only / not required
// ─────────────────────────────────────────────────────────────────────────────
import {
  ArrowRight,
  CheckCircle2,
  Clock,
  RefreshCcw,
  ShieldCheck,
  ThumbsUp,
  UserCircle2,
  Users,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { StatusPill, type StatusTone } from "@/components/shared";
import { cn } from "@/lib/utils";
import {
  type ApprovalMeta,
  type Role,
  approverLine,
  formatDue,
  waitingOnPhrase,
} from "@/lib/approval-clarity";

const STATUS_TO_TONE: Record<ApprovalMeta["tone"], StatusTone> = {
  primary: "primary",
  warning: "warning",
  info: "info",
  success: "success",
  danger: "danger",
  neutral: "neutral",
};

// ─────────────────────────────────────────────────────────────────────────────
// ApprovalStatusPill — coarse status. Use everywhere a one-word status badge
// might otherwise appear so wording stays consistent.
// ─────────────────────────────────────────────────────────────────────────────
export function ApprovalStatusPill({
  meta,
  testId,
  description,
  className,
}: {
  meta: ApprovalMeta;
  testId?: string;
  description?: string;
  className?: string;
}) {
  return (
    <StatusPill
      tone={STATUS_TO_TONE[meta.tone]}
      testId={testId ?? "approval-status-pill"}
      description={description ?? meta.nextActionLabel}
      className={className}
    >
      {meta.statusLabel}
    </StatusPill>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// WaitingOnBadge — explicit "who's blocked on whom" badge. Always names the
// party AND the named person where one exists.
// ─────────────────────────────────────────────────────────────────────────────
export function WaitingOnBadge({
  meta,
  viewer,
  testId,
}: {
  meta: ApprovalMeta;
  viewer: Role;
  testId?: string;
}) {
  if (meta.waitingOn === "none") {
    return null;
  }
  const tone: StatusTone =
    meta.waitingOn === "client"
      ? "primary"
      : meta.waitingOn === "staff"
        ? "warning"
        : "info";
  return (
    <StatusPill
      tone={tone}
      testId={testId ?? "waiting-on-badge"}
      uppercase={false}
      className="font-medium"
    >
      {waitingOnPhrase(meta, viewer)}
    </StatusPill>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// ApproverLine — small low-contrast line that explains who approves / approved.
// ─────────────────────────────────────────────────────────────────────────────
export function ApproverLine({
  meta,
  testId,
  className,
}: {
  meta: ApprovalMeta;
  testId?: string;
  className?: string;
}) {
  return (
    <p
      className={cn(
        "inline-flex items-center gap-1 text-[11px] text-muted-foreground",
        className,
      )}
      data-testid={testId ?? "approver-line"}
    >
      {meta.status === "approved" ? (
        <CheckCircle2 className="size-3 text-emerald-600 dark:text-emerald-400" />
      ) : meta.status === "changes_requested" ? (
        <RefreshCcw className="size-3 text-rose-600 dark:text-rose-400" />
      ) : (
        <ShieldCheck className="size-3" />
      )}
      <span className="truncate">{approverLine(meta)}</span>
    </p>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// ApprovalMetaRow — the canonical detail row used everywhere we explain who is
// doing what. Replaces ad-hoc "owner | due | status" piles. Compact mode shrinks
// it for cards / list rows; default mode is for drawers and detail panels.
// ─────────────────────────────────────────────────────────────────────────────
export function ApprovalMetaRow({
  meta,
  viewer,
  compact,
  showStatus = true,
  testId,
  className,
}: {
  meta: ApprovalMeta;
  viewer: Role;
  compact?: boolean;
  showStatus?: boolean;
  testId?: string;
  className?: string;
}) {
  const due = formatDue(meta.dueAt);
  return (
    <div
      className={cn(
        "flex flex-wrap items-center gap-x-3 gap-y-1.5",
        compact ? "text-[11px]" : "text-xs",
        className,
      )}
      data-testid={testId ?? "approval-meta-row"}
    >
      {showStatus && <ApprovalStatusPill meta={meta} />}
      <WaitingOnBadge meta={meta} viewer={viewer} />
      <span className="inline-flex items-center gap-1 text-muted-foreground">
        <UserCircle2 className="size-3" />
        Owner: <span className="font-medium text-foreground">{meta.ownerName}</span>
      </span>
      {meta.approverName !== "—" && meta.status !== "approved" && (
        <span className="inline-flex items-center gap-1 text-muted-foreground">
          <Users className="size-3" />
          Approver: <span className="font-medium text-foreground">{meta.approverName}</span>
        </span>
      )}
      {due && (
        <span
          className={cn(
            "inline-flex items-center gap-1 text-muted-foreground",
            due.includes("overdue") && "font-medium text-rose-700 dark:text-rose-300",
          )}
        >
          <Clock className="size-3" />
          {due}
        </span>
      )}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// NextActionBanner — the single most visible explanation of what to do next.
// Used in item drawer + file approval dialog so the reader never wonders.
// ─────────────────────────────────────────────────────────────────────────────
export function NextActionBanner({
  meta,
  viewer,
  cta,
  secondaryCta,
  testId,
  className,
}: {
  meta: ApprovalMeta;
  viewer: Role;
  cta?: { label: string; onClick: () => void; testId?: string; disabled?: boolean };
  secondaryCta?: {
    label: string;
    onClick: () => void;
    testId?: string;
    disabled?: boolean;
  };
  testId?: string;
  className?: string;
}) {
  const toneCls =
    meta.tone === "primary"
      ? "border-orange-300 bg-orange-50 text-orange-900 dark:border-orange-900/60 dark:bg-orange-950/30 dark:text-orange-100"
      : meta.tone === "warning"
        ? "border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100"
        : meta.tone === "success"
          ? "border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-100"
          : meta.tone === "danger"
            ? "border-rose-300 bg-rose-50 text-rose-900 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-100"
            : meta.tone === "info"
              ? "border-sky-300 bg-sky-50 text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-100"
              : "border-border bg-muted text-foreground";

  // Headline copy: explicit per role.
  const headline =
    meta.status === "approved"
      ? "Approved · nothing else to do"
      : meta.status === "needs_client_approval"
        ? viewer === "client"
          ? "Your approval is needed"
          : `Waiting on ${meta.waitingOnName ?? "the client"} to approve`
        : meta.status === "needs_staff_review"
          ? viewer === "admin"
            ? `Staff review by ${meta.waitingOnName ?? "Oversee"}`
            : "Oversee is reviewing this internally"
          : meta.status === "changes_requested"
            ? viewer === "client"
              ? "Oversee is making your requested changes"
              : `Revise — changes requested by ${meta.lastDecisionBy ?? "client"}`
            : meta.status === "waiting_on_oversee"
              ? viewer === "admin"
                ? `Oversee work — ${meta.waitingOnName ?? "team"}`
                : `${meta.waitingOnName ?? "Oversee"} is working on this`
              : "No decision required";

  const Icon =
    meta.status === "approved"
      ? CheckCircle2
      : meta.status === "needs_client_approval"
        ? ThumbsUp
        : meta.status === "changes_requested"
          ? RefreshCcw
          : meta.status === "needs_staff_review"
            ? ShieldCheck
            : Clock;

  return (
    <div
      className={cn(
        "flex flex-col gap-3 rounded-xl border px-4 py-3 md:flex-row md:items-center md:justify-between",
        toneCls,
        className,
      )}
      data-testid={testId ?? "next-action-banner"}
    >
      <div className="flex min-w-0 items-start gap-3">
        <span className="mt-0.5 grid size-8 shrink-0 place-items-center rounded-md bg-white/60 dark:bg-zinc-900/60">
          <Icon className="size-4" />
        </span>
        <div className="min-w-0">
          <p className="text-sm font-semibold leading-snug" data-testid="next-action-headline">
            {headline}
          </p>
          <p className="mt-0.5 text-[11px] opacity-80" data-testid="next-action-detail">
            {approverLine(meta)}
            {meta.dueAt && ` · ${formatDue(meta.dueAt)}`}
          </p>
        </div>
      </div>
      {(cta || secondaryCta) && (
        <div className="flex shrink-0 flex-wrap items-center gap-2">
          {secondaryCta && (
            <Button
              size="sm"
              variant="outline"
              onClick={secondaryCta.onClick}
              disabled={secondaryCta.disabled}
              data-testid={secondaryCta.testId ?? "next-action-secondary"}
              className="gap-1 bg-transparent"
            >
              <RefreshCcw className="size-3.5" />
              {secondaryCta.label}
            </Button>
          )}
          {cta && (
            <Button
              size="sm"
              onClick={cta.onClick}
              disabled={cta.disabled}
              data-testid={cta.testId ?? "next-action-primary"}
              className="gap-1"
            >
              {cta.label}
              <ArrowRight className="size-3.5" />
            </Button>
          )}
        </div>
      )}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// ApprovalTrailItem — single audit-trail entry for the "who decided what when"
// list. Used in item drawer Activity tab and file dialog history.
// ─────────────────────────────────────────────────────────────────────────────
export function ApprovalTrailItem({
  who,
  decision,
  at,
  note,
  testId,
}: {
  who: string;
  decision: "approved" | "changes-requested" | "submitted" | "reviewed";
  at: string;
  note?: string;
  testId?: string;
}) {
  const Icon =
    decision === "approved"
      ? CheckCircle2
      : decision === "changes-requested"
        ? RefreshCcw
        : decision === "submitted"
          ? ArrowRight
          : ShieldCheck;
  const tone =
    decision === "approved"
      ? "text-emerald-700 dark:text-emerald-300"
      : decision === "changes-requested"
        ? "text-rose-700 dark:text-rose-300"
        : "text-muted-foreground";
  const verb =
    decision === "approved"
      ? "approved"
      : decision === "changes-requested"
        ? "requested changes"
        : decision === "submitted"
          ? "submitted for review"
          : "reviewed";
  return (
    <li
      className="flex items-start gap-3 px-4 py-3"
      data-testid={testId ?? `approval-trail-${decision}`}
    >
      <span className={cn("mt-0.5 grid size-7 shrink-0 place-items-center rounded-full bg-muted", tone)}>
        <Icon className="size-3.5" />
      </span>
      <div className="min-w-0 flex-1">
        <p className="text-sm">
          <span className="font-medium text-foreground">{who}</span>{" "}
          <span className={cn("font-medium", tone)}>{verb}</span>
        </p>
        {note && (
          <p className="mt-0.5 line-clamp-3 text-xs text-muted-foreground">{note}</p>
        )}
        <p className="mt-0.5 text-[11px] text-muted-foreground">
          {new Date(at).toLocaleString(undefined, {
            month: "short",
            day: "numeric",
            hour: "numeric",
            minute: "2-digit",
          })}
        </p>
      </div>
    </li>
  );
}
