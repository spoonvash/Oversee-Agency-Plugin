// Client Home — refactored to consume shared primitives.
// Hero (PageHeader+SummaryStat strip), Start-here (PriorityBanner),
// onboarding banner (PriorityBanner tone="primary"), action list (SectionCard),
// services rail (SectionCard + DataListRow).
import { useMemo, useState } from "react";
import {
  ArrowRight,
  ArrowUpRight,
  CheckCircle2,
  Clock,
  MessageCircleReply,
  Repeat,
  Rocket,
  ShoppingBag,
  ThumbsUp,
  Upload,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Progress } from "@/components/ui/progress";
import { useDemoStore, type BoardItem, type FeedUpdate } from "@/lib/demo-store";
import { ItemDetailSheet } from "@/components/item-detail-sheet";
import { ReadyForReviewSection } from "@/components/ready-for-review-section";
import {
  PageHeader,
  PriorityBanner,
  SectionCard,
  SummaryStat,
} from "@/components/shared";
import { ApprovalMetaRow } from "@/components/approval-clarity";
import { getItemApproval, type ApprovalMeta } from "@/lib/approval-clarity";
import { useLocation } from "wouter";
import { shortDate } from "@/lib/format";

type ActionKind = "approve" | "reply" | "upload" | "review";

type ClientAction = {
  id: string;
  kind: ActionKind;
  title: string;
  why: string;
  due?: string;
  status: string;
  buttonLabel: string;
  open: () => void;
  meta?: string;
  approval?: ApprovalMeta;
};

export default function ClientHome() {
  const { customer, items, feed, boards, subscriptions, services, files, onboardingPlans } = useDemoStore();
  const [, navigate] = useLocation();
  const [openItem, setOpenItem] = useState<BoardItem | null>(null);

  const actions = useMemo<ClientAction[]>(() => {
    const list: ClientAction[] = [];

    items
      .filter((i) => i.status === "review")
      .forEach((i) => {
        const meta = getItemApproval(i);
        // Skip items where staff (not the client) is the approver — those
        // shouldn't appear on the client "needs you" list.
        if (meta.status === "needs_staff_review") return;
        list.push({
          id: `approve-${i.id}`,
          kind: "approve",
          title: i.title,
          why: `${meta.ownerName} needs your approval before this can ship.`,
          due: i.due,
          status: meta.statusLabel,
          buttonLabel: "Review & approve",
          open: () => setOpenItem(i),
          meta: boards.find((b) => b.id === i.boardId)?.name,
          approval: meta,
        });
      });

    items
      .filter((i) => i.status === "client-input")
      .forEach((i) => {
        const meta = getItemApproval(i);
        list.push({
          id: `review-${i.id}`,
          kind: "review",
          title: i.title,
          why: `${meta.ownerName} is waiting on info or a decision from you to keep moving.`,
          due: i.due,
          status: meta.statusLabel,
          buttonLabel: "Open task",
          open: () => setOpenItem(i),
          meta: boards.find((b) => b.id === i.boardId)?.name,
          approval: meta,
        });
      });

    feed
      .filter((u) => !u.internalOnly && (u.unread || u.mentioned))
      .slice(0, 3)
      .forEach((u: FeedUpdate) => {
        const item = u.itemId ? items.find((i) => i.id === u.itemId) : undefined;
        list.push({
          id: `reply-${u.id}`,
          kind: "reply",
          title: item ? `${u.authorName} on “${item.title}”` : `${u.authorName} sent an update`,
          why: u.body.length > 110 ? u.body.slice(0, 110) + "…" : u.body,
          status: u.mentioned ? "Mentioned you" : "New update",
          buttonLabel: "Reply",
          open: () => (item ? setOpenItem(item) : navigate("/client/work")),
          meta: boards.find((b) => b.id === u.boardId)?.name,
        });
      });

    return list.slice(0, 8);
  }, [items, feed, boards, navigate]);

  const liveOpenItem = openItem ? items.find((i) => i.id === openItem.id) || null : null;

  const headline =
    actions.length === 0
      ? "You’re all caught up."
      : actions.length === 1
        ? "You have 1 thing to review"
        : `You have ${actions.length} things to review`;

  const activeSubs = subscriptions.filter((s) => s.status === "active" || s.status === "past-due");

  const pendingPreviews = files.filter((f) => f.approval === "pending");
  const hasAnyVisibleFiles = files.some(
    (f) =>
      (f.kind === "image" || f.kind === "pdf" || f.kind === "video") &&
      (f.approval === "pending" || f.approval === "approved" || f.approval === "changes-requested"),
  );

  const activePlans = subscriptions
    .filter((s) => s.status === "active" || s.status === "past-due")
    .map((s) => onboardingPlans.find((p) => p.serviceId === s.serviceId))
    .filter(Boolean) as typeof onboardingPlans;
  const onboardingTotal = activePlans.reduce((sum, p) => sum + p.steps.length, 0);
  const onboardingDone = activePlans.reduce(
    (sum, p) => sum + p.steps.filter((s) => s.status === "complete").length,
    0,
  );
  const onboardingPct = onboardingTotal === 0 ? 0 : Math.round((onboardingDone / onboardingTotal) * 100);
  const showOnboardingBanner = activePlans.length > 0 && onboardingPct < 100;

  return (
    <div className="space-y-5">
      <PageHeader
        eyebrow={`Hi ${customer.name.split(" ")[0]}`}
        title={headline}
        subtitle="Here's what Oversee needs from you."
        testId="heading-client-home"
      />

      {/* Stat strip — three calm numeric pills with tone dots */}
      <div className="grid gap-3 sm:grid-cols-3" data-testid="client-home-stats">
        <SummaryStat
          tone={actions.length > 0 ? "primary" : "neutral"}
          label="Need you"
          value={actions.length}
          hint={actions.length === 1 ? "item awaits your input" : "items await your input"}
          testId="stat-actions"
        />
        <SummaryStat
          tone="success"
          label="Active services"
          value={activeSubs.length}
          hint={activeSubs.length === 1 ? "subscription running" : "subscriptions running"}
          testId="stat-active-subs"
        />
        <SummaryStat
          tone="info"
          label="Active boards"
          value={boards.length}
          hint={boards.length === 1 ? "project in flight" : "projects in flight"}
          testId="stat-active-boards"
        />
      </div>

      {/* START HERE — single primary CTA */}
      {actions.length > 0 && (
        <PriorityBanner
          icon={
            actions[0].kind === "approve"
              ? ThumbsUp
              : actions[0].kind === "reply"
                ? MessageCircleReply
                : Upload
          }
          eyebrow="Start here"
          title={actions[0].title}
          subtitle={actions[0].why}
          cta={{
            label: actions[0].buttonLabel,
            onClick: actions[0].open,
            testId: "button-start-here-primary",
          }}
          tone="primary"
          testId="client-start-here"
        />
      )}

      {/* ONBOARDING BANNER — surface when client still has setup left */}
      {showOnboardingBanner && (
        <section
          className="flex flex-col gap-3 rounded-xl border border-orange-200 bg-orange-50/40 px-5 py-4 dark:border-orange-900/40 dark:bg-orange-950/20 md:flex-row md:items-center md:justify-between"
          data-testid="home-onboarding-banner"
        >
          <div className="flex items-start gap-3">
            <span className="grid size-10 shrink-0 place-items-center rounded-md bg-orange-100 text-orange-700 dark:bg-orange-950/60 dark:text-orange-300">
              <Rocket className="size-5" />
            </span>
            <div className="min-w-0">
              <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-orange-700 dark:text-orange-300">
                Finish your setup
              </p>
              <p className="mt-0.5 text-sm font-semibold leading-snug">
                {onboardingDone} of {onboardingTotal} onboarding steps complete
              </p>
              <p className="mt-0.5 text-xs text-muted-foreground">
                Grant access, complete intake forms, and book your kickoff so we can start delivering.
              </p>
            </div>
          </div>
          <div className="flex w-full items-center gap-3 md:w-auto">
            <div className="flex-1 md:w-44">
              <div className="flex items-center justify-between text-[11px] tabular-nums text-muted-foreground">
                <span>{onboardingPct}%</span>
                <span>complete</span>
              </div>
              <Progress value={onboardingPct} className="mt-1 h-2" />
            </div>
            <Button
              onClick={() => navigate("/client/onboarding")}
              className="h-10 gap-1.5"
              data-testid="button-open-onboarding"
            >
              Open onboarding <ArrowRight className="size-4" />
            </Button>
          </div>
        </section>
      )}

      {/* FILES TO REVIEW — always visible when any preview-able files exist. */}
      {hasAnyVisibleFiles && (
        <ReadyForReviewSection
          role="client"
          title={pendingPreviews.length > 0 ? "Files to review" : "Latest files from Oversee"}
          subtitle={
            pendingPreviews.length > 0
              ? `${pendingPreviews.length} preview${pendingPreviews.length === 1 ? "" : "s"} need your approval. Click any thumbnail to open the full preview.`
              : "Recent deliverables. Click any thumbnail to open the full preview."
          }
          approvalStates={["pending", "changes-requested", "approved"]}
          limit={8}
        />
      )}

      {/* Two-column layout: actions on left, services on right. */}
      <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_400px]">
        {/* PRIMARY — action list */}
        <SectionCard
          title="What Oversee needs from you"
          hint={
            actions.length
              ? `${actions.length} item${actions.length === 1 ? "" : "s"} need your input`
              : "Nothing pending right now"
          }
          testId="client-action-list-section"
        >
          {actions.length === 0 ? (
            <EmptyState />
          ) : (
            <ul className="divide-y divide-border/60" data-testid="client-action-list">
              {actions.map((a) => (
                <ActionRow key={a.id} action={a} />
              ))}
            </ul>
          )}
        </SectionCard>

        {/* SECONDARY — services summary */}
        <aside className="space-y-4" data-testid="client-services-summary">
          <SectionCard
            title="Your services"
            hint={`${activeSubs.length} active`}
            trailing={
              <button
                type="button"
                onClick={() => navigate("/client/account/subscriptions")}
                className="text-[11px] font-medium text-primary hover:underline"
                data-testid="link-all-subs"
              >
                View all
              </button>
            }
            testId="client-services-card"
          >
            {activeSubs.length === 0 ? (
              <div className="px-5 py-8 text-center">
                <Repeat className="mx-auto size-5 text-muted-foreground" />
                <p className="mt-2 text-xs text-muted-foreground">No active services yet.</p>
              </div>
            ) : (
              <ul className="divide-y divide-border/60">
                {activeSubs.slice(0, 4).map((s) => {
                  const svc = services.find((sv) => sv.id === s.serviceId);
                  if (!svc) return null;
                  return (
                    <li
                      key={s.id}
                      className="flex items-start gap-3 px-5 py-3.5"
                      data-testid={`home-sub-${s.id}`}
                    >
                      <span className="grid size-9 shrink-0 place-items-center rounded-md border border-border bg-muted">
                        <Repeat className="size-4 text-muted-foreground" />
                      </span>
                      <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-semibold leading-snug">{svc.title}</p>
                        <p className="mt-0.5 text-[11px] text-muted-foreground">
                          ${s.amount.toLocaleString()}/{svc.cadence === "monthly" ? "mo" : svc.cadence}
                          {s.status === "active" ? ` · renews ${shortDate(s.renewsOn)}` : " · payment failed"}
                        </p>
                      </div>
                      <Badge
                        variant="outline"
                        className={`shrink-0 px-1.5 py-0 text-[10px] uppercase tracking-wide ${
                          s.status === "active"
                            ? "border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300"
                            : "border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300"
                        }`}
                      >
                        {s.status}
                      </Badge>
                    </li>
                  );
                })}
              </ul>
            )}
            <div className="border-t border-border/60 bg-surface-elevated px-5 py-3 /60 ">
              <Button
                size="sm"
                variant="outline"
                className="w-full gap-1.5"
                onClick={() => navigate("/client/services")}
                data-testid="button-browse-services"
              >
                <ShoppingBag className="size-3.5" /> Browse services
              </Button>
            </div>
          </SectionCard>

          <SectionCard
            title="Billing & renewals"
            hint="Payment, invoices, and renewals are handled in WooCommerce."
            tone="neutral"
            testId="client-billing-pointer"
          >
            <button
              type="button"
              className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
              onClick={() => navigate("/client/account")}
              data-testid="link-account-from-home"
            >
              Open Account page
              <ArrowUpRight className="size-3" />
            </button>
          </SectionCard>
        </aside>
      </div>

      <ItemDetailSheet item={liveOpenItem} onClose={() => setOpenItem(null)} role="client" />
    </div>
  );
}

function ActionRow({ action }: { action: ClientAction }) {
  const Icon =
    action.kind === "approve"
      ? ThumbsUp
      : action.kind === "reply"
        ? MessageCircleReply
        : action.kind === "upload"
          ? Upload
          : ArrowUpRight;

  const iconCls =
    action.kind === "approve"
      ? "border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900/60 dark:bg-orange-950/40 dark:text-orange-300"
      : action.kind === "reply"
        ? "border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/60 dark:bg-sky-950/40 dark:text-sky-300"
        : "border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300";

  return (
    <li
      className="flex flex-col gap-3 px-5 py-4 md:flex-row md:items-center md:justify-between"
      data-testid={`action-card-${action.id}`}
    >
      <div className="flex min-w-0 items-start gap-3">
        <span
          className={`mt-0.5 grid size-9 shrink-0 place-items-center rounded-md border ${iconCls}`}
          aria-hidden
        >
          <Icon className="size-4" />
        </span>
        <div className="min-w-0">
          <h3
            className="text-sm font-semibold leading-snug"
            data-testid={`action-title-${action.id}`}
          >
            {action.title}
          </h3>
          {action.meta && (
            <p className="mt-0.5 text-[11px] uppercase tracking-[0.14em] text-muted-foreground">
              {action.meta}
            </p>
          )}
          {action.approval ? (
            <div className="mt-1.5">
              <ApprovalMetaRow
                meta={action.approval}
                viewer="client"
                compact
                testId={`approval-meta-${action.id}`}
              />
            </div>
          ) : (
            <div className="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1">
              <span className="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                {action.status}
              </span>
              {action.due && (
                <span className="inline-flex items-center gap-1 text-[11px] text-muted-foreground">
                  <Clock className="size-3" />
                  Due{" "}
                  {new Date(action.due).toLocaleDateString(undefined, {
                    month: "short",
                    day: "numeric",
                  })}
                </span>
              )}
            </div>
          )}
          <p className="mt-1.5 max-w-prose text-xs text-muted-foreground">{action.why}</p>
        </div>
      </div>
      <Button
        size="sm"
        className="md:shrink-0"
        onClick={action.open}
        data-testid={`button-action-${action.id}`}
      >
        {action.buttonLabel}
        <ArrowRight className="ml-1 size-3.5" />
      </Button>
    </li>
  );
}

function EmptyState() {
  return (
    <div
      className="px-6 py-12 text-center"
      data-testid="empty-state-home"
    >
      <div className="mx-auto grid size-12 place-items-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400">
        <CheckCircle2 className="size-6" />
      </div>
      <h2 className="mt-4 text-base font-semibold">You&rsquo;re all caught up.</h2>
      <p className="mt-1 text-xs text-muted-foreground">
        We&rsquo;ll notify you when we need anything.
      </p>
    </div>
  );
}
