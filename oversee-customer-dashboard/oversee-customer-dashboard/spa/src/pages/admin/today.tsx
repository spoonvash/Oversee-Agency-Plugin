// Admin Today — Operations command center.
//
// Refactored to consume shared primitives (PriorityBanner, SummaryStat,
// QueueLane, SectionCard, DataListRow). One canonical card treatment, one
// canonical row treatment. Color is reserved for tone dots and small icon
// chips — no large gradient panels.
import { useMemo, useState } from "react";
import { useLocation } from "wouter";
import {
  AlertTriangle,
  ArrowUpRight,
  CheckCircle2,
  Clock,
  CreditCard,
  FileCheck2,
  MessageSquareWarning,
  Plus,
  Send,
  ShoppingCart,
  Sparkles,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { useDemoStore, type LeadsieRequestStatus, type OnboardingStep } from "@/lib/demo-store";
import { CreateBoardWizard } from "@/components/create-board-wizard";
import { ReadyForReviewSection } from "@/components/ready-for-review-section";
import { PlatformIcon, STATUS_LABEL, STATUS_TONE } from "@/components/leadsie";
import { getItemApproval, getFileApproval, formatDue } from "@/lib/approval-clarity";
import {
  PageHeader,
  PriorityBanner,
  SummaryStat,
  QueueLane,
  SectionCard,
  SectionLink,
  DetailRail,
  DataList,
  ButtonGroup,
} from "@/components/shared";

export default function AdminToday() {
  const {
    items,
    orders,
    payments,
    boards,
    feed,
    clients,
    subscriptions,
    files,
    services,
    onboardingPlans,
    leadsieSettings,
    customer,
  } = useDemoStore();
  const [, navigate] = useLocation();
  const [createOpen, setCreateOpen] = useState(false);

  // === Derived data ===
  const overdue = items.filter(
    (i) => i.status !== "done" && i.due && new Date(i.due) < new Date(),
  );
  const onHoldOrders = orders.filter((o) => o.status === "on-hold");
  const failedPayments = payments.filter((p) => p.status === "failed");
  // Split review items by who actually has to make the decision so each lane is
  // unambiguous on the admin side.
  const reviewItems = items.filter((i) => i.status === "review");
  const clientApprovalItems = reviewItems.filter(
    (i) => getItemApproval(i).status === "needs_client_approval",
  );
  const staffReviewItems = reviewItems.filter(
    (i) => getItemApproval(i).status === "needs_staff_review",
  );
  const draftsReady = items.filter(
    (i) =>
      i.status === "in-progress" &&
      (i.workflowStage?.toLowerCase().includes("ready") ||
        i.workflowStage?.toLowerCase().includes("review")),
  );
  const unreadFromClients = feed.filter((u) => u.authorRole === "client" && u.unread);
  const pendingPreviews = files.filter((f) => f.approval === "pending");
  const filesNeedingClient = pendingPreviews.filter(
    (f) => getFileApproval(f).status === "needs_client_approval",
  );
  const filesNeedingStaff = pendingPreviews.filter(
    (f) => getFileApproval(f).status === "needs_staff_review",
  );
  const recentDecisions = files
    .filter((f) => f.approval === "approved" || f.approval === "changes-requested")
    .sort(
      (a, b) => new Date(b.uploadedAt).getTime() - new Date(a.uploadedAt).getTime(),
    )
    .slice(0, 4);
  const pastDueSubs = subscriptions.filter((s) => s.status === "past-due");

  // Onboarding / access health derived from plans.
  const accessSteps = useMemo(
    () =>
      onboardingPlans.flatMap((plan) => {
        const sub = subscriptions.find((s) => s.serviceId === plan.serviceId);
        const service = services.find((s) => s.id === plan.serviceId);
        if (!sub) return [];
        return plan.steps
          .filter((step) => step.kind === "leadsie-access")
          .map((step) => ({
            id: `${plan.serviceId}-${step.id}`,
            step,
            serviceId: plan.serviceId,
            serviceName: service?.title ?? "Service",
            customerName: customer.company,
          }));
      }),
    [onboardingPlans, subscriptions, services, customer.company],
  );
  const accessOpen = accessSteps.filter((a) => a.step.status !== "complete");
  const accessRecentlyApproved = accessSteps.filter((a) => a.step.status === "complete");

  const riskCount = failedPayments.length + overdue.length + pastDueSubs.length;

  // Pick the single highest-priority next action for the Start Here banner.
  const top = (() => {
    if (failedPayments.length > 0) {
      const p = failedPayments[0];
      return {
        icon: CreditCard,
        title: `Resolve failed payment for ${p.customer}`,
        subtitle: `$${p.amount.toLocaleString()} · ${p.service}`,
        cta: { label: "Triage now", testId: "start-here-cta", onClick: () => navigate("/admin/clients") },
      };
    }
    if (unreadFromClients.length > 0) {
      const u = unreadFromClients[0];
      return {
        icon: MessageSquareWarning,
        title: `Reply to ${u.authorName}`,
        subtitle: u.body.length > 100 ? u.body.slice(0, 100) + "…" : u.body,
        cta: { label: "Open boards", testId: "start-here-cta", onClick: () => navigate("/admin/boards") },
      };
    }
    if (pendingPreviews.length > 0) {
      return {
        icon: FileCheck2,
        title: `Review ${pendingPreviews.length} file${pendingPreviews.length === 1 ? "" : "s"} awaiting client decision`,
        subtitle: "Files ready for client sign-off.",
        cta: { label: "Review files", testId: "start-here-cta", onClick: () => navigate("/admin/files-approvals") },
      };
    }
    if (overdue.length > 0) {
      const i = overdue[0];
      return {
        icon: AlertTriangle,
        title: `Resolve overdue: ${i.title}`,
        subtitle: `Assigned to ${i.assignee}`,
        cta: { label: "Open task", testId: "start-here-cta", onClick: () => navigate("/admin/boards") },
      };
    }
    return null;
  })();

  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow={new Date().toLocaleDateString(undefined, {
          weekday: "long",
          month: "long",
          day: "numeric",
        })}
        title={`Good ${greeting()}, Sasha`}
        subtitle="Here's what needs your attention across clients today. Work top to bottom — most urgent on the left."
        testId="admin-today-header"
        actions={
          <ButtonGroup>
            <Button
              size="sm"
              variant="outline"
              className="gap-1.5"
              onClick={() => navigate("/admin/boards")}
              data-testid="button-open-boards"
            >
              <Sparkles className="size-4" /> Open boards
            </Button>
            <Button
              size="sm"
              onClick={() => setCreateOpen(true)}
              className="gap-1.5"
              data-testid="button-create-board"
            >
              <Plus className="size-4" /> New project
            </Button>
          </ButtonGroup>
        }
      />

      {/* START HERE — single highest-priority CTA */}
      {top ? (
        <PriorityBanner
          icon={top.icon}
          title={top.title}
          subtitle={top.subtitle}
          cta={top.cta}
          testId="start-here-panel"
        />
      ) : (
        <PriorityBanner
          icon={CheckCircle2}
          tone="success"
          eyebrow="All clear"
          title="Nothing urgent right now."
          subtitle="Review queues below or get a head start on drafts."
          testId="start-here-panel"
        />
      )}

      {/* SUMMARY STATS — quiet, uniform tone dots */}
      <section
        className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
        data-testid="admin-today-metrics"
      >
        <SummaryStat
          tone="primary"
          label="Needs response"
          value={unreadFromClients.length}
          hint={
            unreadFromClients.length === 0
              ? "Inbox zero"
              : `From ${new Set(unreadFromClients.map((u) => u.authorName)).size} client${
                  new Set(unreadFromClients.map((u) => u.authorName)).size === 1 ? "" : "s"
                }`
          }
          testId="metric-needs-response"
        />
        <SummaryStat
          tone="info"
          label="Waiting on client"
          value={clientApprovalItems.length + filesNeedingClient.length}
          hint={
            clientApprovalItems.length + filesNeedingClient.length === 0
              ? "Nothing waiting on a client"
              : `${clientApprovalItems.length} task${clientApprovalItems.length === 1 ? "" : "s"} · ${filesNeedingClient.length} file${filesNeedingClient.length === 1 ? "" : "s"}`
          }
          testId="metric-awaiting"
        />
        <SummaryStat
          tone={riskCount > 0 ? "danger" : "neutral"}
          label="At risk"
          value={riskCount}
          hint={
            riskCount === 0
              ? "Nothing on fire"
              : `${failedPayments.length} payment · ${overdue.length} overdue · ${pastDueSubs.length} sub`
          }
          testId="metric-risk"
        />
        <SummaryStat
          tone="success"
          label="Access connected"
          value={accessRecentlyApproved.length}
          hint={
            accessOpen.length > 0
              ? `${accessOpen.length} pending`
              : "Every client onboarded"
          }
          testId="metric-access"
        />
      </section>

      {/* MAIN 12-COL GRID */}
      <div className="grid gap-6 xl:grid-cols-12">
        {/* LEFT — Priority queues (cols 1–8) */}
        <div className="space-y-5 xl:col-span-8" data-testid="admin-today-queue">
          <QueueLane
            tone="primary"
            icon={MessageSquareWarning}
            title="Reply first"
            subtitle="Unread client messages — they're waiting on you."
            count={unreadFromClients.length}
            emptyText="Inbox zero. Nothing waiting on you here."
            testId="queue-reply-first"
            rows={unreadFromClients.slice(0, 5).map((u) => ({
              id: `nr-${u.id}`,
              title: u.body.length > 90 ? u.body.slice(0, 90) + "\u2026" : u.body,
              sub: `${u.authorName} · ${boards.find((b) => b.id === u.boardId)?.name ?? ""}`,
              meta: timeAgo(u.postedAt),
              onClick: () => navigate("/admin/boards"),
            }))}
          />
          <QueueLane
            tone="info"
            icon={Clock}
            title="Client approvals — waiting on client"
            subtitle="You sent these for client sign-off. Approver: client."
            count={clientApprovalItems.length + filesNeedingClient.length}
            emptyText="Nothing currently waiting on a client."
            testId="queue-waiting-client"
            rows={[
              ...clientApprovalItems.slice(0, 4).map((i) => {
                const m = getItemApproval(i);
                const due = formatDue(i.due);
                return {
                  id: `wc-${i.id}`,
                  title: i.title,
                  sub: `${boards.find((b) => b.id === i.boardId)?.name ?? ""} · Approver: ${m.approverName}`,
                  meta: due,
                  onClick: () => navigate("/admin/boards"),
                };
              }),
              ...filesNeedingClient.slice(0, 3).map((f) => {
                const m = getFileApproval(f);
                return {
                  id: `wcf-${f.id}`,
                  title: `File: ${f.name}`,
                  sub: `${f.boardName ?? "Files"} · Approver: ${m.approverName}`,
                  meta: undefined,
                  onClick: () => navigate("/admin/files-approvals"),
                };
              }),
            ]}
          />
          <QueueLane
            tone="warning"
            icon={FileCheck2}
            title="Staff review — Oversee internal"
            subtitle="Items flagged for an Oversee reviewer before they go to the client."
            count={staffReviewItems.length + filesNeedingStaff.length}
            emptyText="Nothing waiting on internal staff review."
            testId="queue-staff-review"
            rows={[
              ...staffReviewItems.slice(0, 4).map((i) => {
                const m = getItemApproval(i);
                const due = formatDue(i.due);
                return {
                  id: `sr-${i.id}`,
                  title: i.title,
                  sub: `${boards.find((b) => b.id === i.boardId)?.name ?? ""} · Reviewer: ${m.approverName}`,
                  meta: due,
                  onClick: () => navigate("/admin/boards"),
                };
              }),
              ...filesNeedingStaff.slice(0, 3).map((f) => {
                const m = getFileApproval(f);
                return {
                  id: `srf-${f.id}`,
                  title: `File: ${f.name}`,
                  sub: `Uploaded by ${f.uploadedBy} · Reviewer: ${m.approverName}`,
                  meta: undefined,
                  onClick: () => navigate("/admin/files-approvals"),
                };
              }),
            ]}
          />
          <QueueLane
            tone="success"
            icon={Send}
            title="Oversee work — ready to send"
            subtitle="Drafts and deliverables that look ready — push them to the client."
            count={draftsReady.length}
            emptyText="No drafts queued for delivery."
            testId="queue-ready-send"
            rows={draftsReady.slice(0, 4).map((i) => ({
              id: `rs-${i.id}`,
              title: i.title,
              sub: `${boards.find((b) => b.id === i.boardId)?.name ?? ""} · Owner: ${i.assignee}`,
              meta: i.due ? `Due ${formatDate(i.due)}` : undefined,
              onClick: () => navigate("/admin/boards"),
            }))}
          />
          <QueueLane
            tone="danger"
            icon={AlertTriangle}
            title="Risk — payments & overdue"
            subtitle="Anything blocked, late, or failing. Resolve to keep momentum."
            count={riskCount}
            emptyText="Nothing on fire. Nice."
            testId="queue-risk"
            rows={[
              ...failedPayments.slice(0, 3).map((p) => ({
                id: `pay-${p.id}`,
                title: `Payment failed · $${p.amount.toLocaleString()}`,
                sub: `${p.customer} · ${p.service}`,
                meta: <CreditCard className="size-3.5 text-rose-500" />,
                onClick: () => navigate("/admin/clients"),
              })),
              ...onHoldOrders.slice(0, 2).map((o) => ({
                id: `oh-${o.id}`,
                title: `Order ${o.number} on hold`,
                sub: `${o.customer} · ${o.service}`,
                meta: <ShoppingCart className="size-3.5 text-rose-500" />,
                onClick: () => navigate("/admin/clients"),
              })),
              ...overdue.slice(0, 3).map((i) => ({
                id: `od-${i.id}`,
                title: `Overdue: ${i.title}`,
                sub: `${boards.find((b) => b.id === i.boardId)?.name ?? ""} · ${i.assignee}`,
                meta: i.due ? `${formatDate(i.due)}` : undefined,
                onClick: () => navigate("/admin/boards"),
              })),
            ]}
          />
        </div>

        {/* RIGHT — Focus / Access / Decisions / Health (cols 9–12) */}
        <DetailRail className="xl:col-span-4" testId="admin-today-rail">
          <FocusCard
            unreadFromClients={unreadFromClients.length}
            pendingPreviews={pendingPreviews.length}
            draftsReady={draftsReady.length}
            riskCount={riskCount}
          />
          <AccessOnboardingCard
            connected={leadsieSettings.connected}
            agencySlug={leadsieSettings.agencySlug}
            openSteps={accessOpen.slice(0, 4)}
            onOpenSettings={() => navigate("/admin/system/leadsie")}
          />
          <RecentDecisionsCard
            decisions={recentDecisions}
            onOpen={() => navigate("/admin/files-approvals")}
          />
          <ClientHealthCard
            clients={clients.slice(0, 4)}
            boards={boards}
            items={items}
            onAll={() => navigate("/admin/clients")}
          />
        </DetailRail>
      </div>

      {/* RECENT PREVIEWS — full width, image-first */}
      <ReadyForReviewSection
        role="admin"
        title="Recent client previews"
        subtitle="What clients have approved, requested changes on, or still owe a decision."
        approvalStates={["pending", "approved", "changes-requested"]}
        limit={8}
      />

      <CreateBoardWizard open={createOpen} onOpenChange={setCreateOpen} />
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────
function greeting() {
  const h = new Date().getHours();
  if (h < 12) return "morning";
  if (h < 18) return "afternoon";
  return "evening";
}

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString(undefined, { month: "short", day: "numeric" });
}

function timeAgo(iso: string) {
  const diff = Date.now() - new Date(iso).getTime();
  const m = Math.floor(diff / 60000);
  if (m < 1) return "just now";
  if (m < 60) return `${m}m ago`;
  const h = Math.floor(m / 60);
  if (h < 24) return `${h}h ago`;
  const d = Math.floor(h / 24);
  return `${d}d ago`;
}

// ─────────────────────────────────────────────────────────────────────────────
// FocusCard — quiet "today's focus" rollup using SectionCard.
// ─────────────────────────────────────────────────────────────────────────────
function FocusCard({
  unreadFromClients,
  pendingPreviews,
  draftsReady,
  riskCount,
}: {
  unreadFromClients: number;
  pendingPreviews: number;
  draftsReady: number;
  riskCount: number;
}) {
  const total = unreadFromClients + pendingPreviews + draftsReady + riskCount;
  return (
    <SectionCard
      title="Today's focus"
      hint={total === 0 ? "Nothing to do" : `${total} thing${total === 1 ? "" : "s"} on your list`}
      testId="focus-card"
    >
      <ul className="divide-y divide-border/60 px-1 py-1 text-[13px] ">
        <FocusRow label="Reply to clients" value={unreadFromClients} dot="bg-orange-500" />
        <FocusRow label="Move previews along" value={pendingPreviews} dot="bg-sky-500" />
        <FocusRow label="Send drafts you're sitting on" value={draftsReady} dot="bg-emerald-500" />
        <FocusRow label="Resolve risk" value={riskCount} dot="bg-rose-500" />
      </ul>
    </SectionCard>
  );
}

function FocusRow({ label, value, dot }: { label: string; value: number; dot: string }) {
  return (
    <li className="flex items-center justify-between gap-3 px-4 py-2.5">
      <span className="flex items-center gap-2">
        <span className={`size-1.5 rounded-full ${dot}`} aria-hidden />
        <span className="text-foreground/90">{label}</span>
      </span>
      <span
        className={`text-sm font-semibold tabular-nums ${
          value === 0 ? "text-muted-foreground" : "text-foreground"
        }`}
      >
        {value}
      </span>
    </li>
  );
}

function AccessOnboardingCard({
  connected,
  agencySlug,
  openSteps,
  onOpenSettings,
}: {
  connected: boolean;
  agencySlug: string;
  openSteps: {
    id: string;
    serviceName: string;
    customerName: string;
    step: OnboardingStep;
    // included so callers can keep using LeadsieRequestStatus checks via STATUS_TONE
    _leadsie?: LeadsieRequestStatus;
  }[];
  onOpenSettings: () => void;
}) {
  return (
    <SectionCard
      title="Access & onboarding"
      hint={
        connected
          ? `Powered by Leadsie · connected as ${agencySlug}`
          : "Powered by Leadsie · setup required"
      }
      tone={connected ? "success" : "warning"}
      trailing={
        <SectionLink
          label="Settings"
          onClick={onOpenSettings}
          testId="link-leadsie-settings"
        />
      }
      testId="access-onboarding-card"
    >
      {!connected && (
        <div className="border-b border-amber-200 bg-amber-50/60 px-5 py-2.5 text-[11px] text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
          Connect your Leadsie account in Settings to send real access requests. Until then, links
          are simulated for preview.
        </div>
      )}
      {openSteps.length === 0 ? (
        <div className="px-5 py-8 text-center">
          <CheckCircle2 className="mx-auto size-5 text-emerald-500" />
          <p className="mt-2 text-xs text-muted-foreground">
            Every active client has the access they need.
          </p>
        </div>
      ) : (
        <ul className="divide-y divide-border/60">
          {openSteps.map((s) => (
            <li
              key={s.id}
              className="flex items-center gap-3 px-5 py-3"
              data-testid={`access-step-${s.id}`}
            >
              {s.step.platform && (
                <PlatformIcon platform={s.step.platform} className="size-5 shrink-0" />
              )}
              <div className="min-w-0 flex-1">
                <p className="truncate text-[13px] font-medium leading-tight">
                  {s.customerName} — {s.step.title.replace(/^Grant\s/i, "")}
                </p>
                <p className="mt-0.5 truncate text-[11px] text-muted-foreground">
                  {s.serviceName}
                </p>
              </div>
              <Badge
                variant="outline"
                className={`shrink-0 text-[10px] uppercase tracking-wide ${STATUS_TONE[s.step.leadsieStatus ?? "not-started"]}`}
              >
                {STATUS_LABEL[s.step.leadsieStatus ?? "not-started"]}
              </Badge>
            </li>
          ))}
        </ul>
      )}
    </SectionCard>
  );
}

function RecentDecisionsCard({
  decisions,
  onOpen,
}: {
  decisions: { id: string; name: string; approval: string; uploadedAt: string; boardName?: string }[];
  onOpen: () => void;
}) {
  return (
    <SectionCard
      title="Recent decisions"
      tone="success"
      trailing={
        <SectionLink
          label="View all"
          onClick={onOpen}
          testId="link-all-decisions"
        />
      }
      testId="recent-decisions-card"
    >
      <DataList emptyText="No client decisions in the last week.">
        {decisions.map((d) => {
          const isApproved = d.approval === "approved";
          return (
            <div key={d.id} className="flex items-center gap-3 px-5 py-2.5" data-testid={`decision-${d.id}`}>
              <span
                className={`grid size-7 shrink-0 place-items-center rounded-md ${
                  isApproved
                    ? "bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"
                    : "bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300"
                }`}
              >
                {isApproved ? (
                  <CheckCircle2 className="size-3.5" />
                ) : (
                  <ArrowUpRight className="size-3.5" />
                )}
              </span>
              <div className="min-w-0 flex-1">
                <p className="truncate text-[13px] font-medium leading-tight">{d.name}</p>
                <p className="mt-0.5 truncate text-[11px] text-muted-foreground">
                  {isApproved ? "Approved" : "Changes requested"} · {d.boardName ?? ""}
                </p>
              </div>
              <span className="shrink-0 text-[10px] tabular-nums text-muted-foreground">
                {timeAgo(d.uploadedAt)}
              </span>
            </div>
          );
        })}
      </DataList>
    </SectionCard>
  );
}

function ClientHealthCard({
  clients,
  boards,
  items,
  onAll,
}: {
  clients: any[];
  boards: any[];
  items: any[];
  onAll: () => void;
}) {
  return (
    <SectionCard
      title="Client health"
      tone="info"
      trailing={
        <SectionLink
          label="View all"
          onClick={onAll}
          testId="link-all-clients"
        />
      }
      testId="client-health-card"
    >
      <ul className="divide-y divide-border/60">
        {clients.map((c) => {
          const cBoards = boards.filter((b) => b.clientEmail === c.email).length;
          const cOverdue = items.filter((i) => {
            const board = boards.find((b) => b.id === i.boardId);
            return (
              board?.clientEmail === c.email &&
              i.status !== "done" &&
              i.due &&
              new Date(i.due) < new Date()
            );
          }).length;
          const tone = cOverdue > 0 ? "rose" : "emerald";
          return (
            <li key={c.id} className="flex items-center gap-3 px-5 py-2.5">
              <span className="grid size-8 shrink-0 place-items-center rounded-full bg-muted text-xs font-semibold text-foreground/80">
                {c.name
                  .split(" ")
                  .map((n: string) => n[0])
                  .join("")
                  .slice(0, 2)}
              </span>
              <div className="min-w-0 flex-1">
                <p className="truncate text-[13px] font-medium leading-tight">{c.name}</p>
                <p className="mt-0.5 truncate text-[11px] text-muted-foreground">
                  {cBoards} board{cBoards === 1 ? "" : "s"}
                  {cOverdue > 0 ? ` · ${cOverdue} overdue` : ""}
                </p>
              </div>
              <span
                className={`size-1.5 shrink-0 rounded-full ${
                  tone === "rose" ? "bg-rose-500" : "bg-emerald-500"
                }`}
                aria-label={tone === "rose" ? "needs attention" : "healthy"}
              />
            </li>
          );
        })}
      </ul>
    </SectionCard>
  );
}
