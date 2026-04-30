// Admin Clients — refactored.
// Single 3-pane layout (list | detail | rail). All cards use shared SectionCard
// or DetailRail. KpiSmall removed (use SummaryStat). Boards/files use
// DataListRow. Client activity surfaces ConnectedActivityLine.
//
// Comms model:
//   • Header "Message" button   → opens ClientCommsComposer dialog (CRM/Slack/Email tabs).
//   • Header "Email" button     → opens the same composer locked to email mode.
//   • Messages tab              → MessageThread + filter strip + inline composer.
//   • Right rail Internal notes → unchanged (project-record annotations).
//
// In preview mode every send mutates store state via addClientMessage so the
// thread shows what was posted. Production routes are documented inside
// `client-comms.tsx`.
import { useMemo, useState } from "react";
import {
  Building2,
  ClipboardList,
  ExternalLink,
  FolderKanban,
  Info,
  Mail,
  MessageSquare,
  Phone,
  Plus,
  Search,
  Send,
  FileText,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import {
  useDemoStore,
  type ClientMessageChannel,
  type ClientRecord,
} from "@/lib/demo-store";
import { shortDate, shortDateTime, relativeTime } from "@/lib/format";
import { useToast } from "@/hooks/use-toast";
import { getItemApproval, getFileApproval } from "@/lib/approval-clarity";
import {
  PageHeader,
  SectionCard,
  SummaryStat,
  DataList,
  DataListRow,
  DetailRail,
  ConnectedActivityLine,
} from "@/components/shared";
import {
  CHANNEL_META,
  ChannelBadge,
  ClientCommsComposer,
  MessageFilterTabs,
  MessageThread,
  type MessageFilter,
} from "@/components/client-comms";

type DetailTab = "overview" | "work" | "messages" | "account";

export default function AdminClients() {
  const {
    clients,
    boards,
    items,
    invoices,
    files,
    feed,
    payments,
    onboardingPlans,
    addClientNote,
    addClient,
    addClientCustomField,
    clientMessages,
    addClientMessage,
  } = useDemoStore();
  const [activeId, setActiveId] = useState<string>(clients[0]?.id ?? "");
  const [detailTab, setDetailTab] = useState<DetailTab>("overview");
  const [statusFilter, setStatusFilter] = useState<"all" | ClientRecord["status"]>("all");
  const [query, setQuery] = useState("");
  const [note, setNote] = useState("");
  const [messageFilter, setMessageFilter] = useState<MessageFilter>("all");
  // Dialog state for Add Client / Add Custom Field / Comms composer dialog.
  const [addClientOpen, setAddClientOpen] = useState(false);
  const [newClient, setNewClient] = useState({ name: "", company: "", email: "" });
  const [addFieldOpen, setAddFieldOpen] = useState(false);
  const [newField, setNewField] = useState({ label: "", value: "" });
  // When non-null, the comms dialog is open with the given default channel.
  const [commsDialog, setCommsDialog] = useState<
    null | { channel: ClientMessageChannel }
  >(null);
  const { toast } = useToast();

  const filtered = useMemo(() => {
    let list = clients;
    if (statusFilter !== "all") list = list.filter((c) => c.status === statusFilter);
    if (query.trim()) {
      const q = query.toLowerCase();
      list = list.filter(
        (c) =>
          c.name.toLowerCase().includes(q) ||
          c.company.toLowerCase().includes(q) ||
          c.email.toLowerCase().includes(q),
      );
    }
    return list;
  }, [clients, statusFilter, query]);

  // Attention indicators per client
  const attention = useMemo(() => {
    const map: Record<
      string,
      {
        unread: number;
        clientApprovals: number;
        staffReview: number;
        blocked: boolean;
        payRisk: boolean;
      }
    > = {};
    clients.forEach((c) => {
      const cBoards = boards.filter((b) => b.clientEmail === c.email);
      const boardIds = new Set(cBoards.map((b) => b.id));
      const cItems = items.filter((i) => boardIds.has(i.boardId));
      const cFiles = files.filter((f) => cBoards.some((b) => b.name === f.boardName));
      const unread = feed.filter(
        (u) => u.unread && cBoards.some((b) => b.id === u.boardId) && u.authorRole !== "admin",
      ).length;
      // Split approvals into the two real buckets (client decides vs staff decides).
      const reviewItems = cItems.filter((i) => i.status === "review");
      const clientItemApprovals = reviewItems.filter(
        (i) => getItemApproval(i).status === "needs_client_approval",
      ).length;
      const staffItemReview = reviewItems.filter(
        (i) => getItemApproval(i).status === "needs_staff_review",
      ).length;
      const pendingFiles = cFiles.filter((f) => f.approval === "pending");
      const clientFileApprovals = pendingFiles.filter(
        (f) => getFileApproval(f).status === "needs_client_approval",
      ).length;
      const staffFileReview = pendingFiles.filter(
        (f) => getFileApproval(f).status === "needs_staff_review",
      ).length;
      const plan = onboardingPlans?.find((p: any) => p.customerEmail === c.email);
      const blocked =
        !!plan && Array.isArray(plan.steps) && plan.steps.some((s: any) => s.status === "blocked");
      const payRisk = !!payments?.find(
        (p: any) => p.status === "failed" && p.customer === c.name,
      );
      map[c.id] = {
        unread,
        clientApprovals: clientItemApprovals + clientFileApprovals,
        staffReview: staffItemReview + staffFileReview,
        blocked,
        payRisk,
      };
    });
    return map;
  }, [clients, boards, items, feed, files, onboardingPlans, payments]);

  const active = clients.find((c) => c.id === activeId) || filtered[0];
  const clientBoards = boards.filter((b) => b.clientEmail === active?.email);
  const clientItems = items.filter((i) => clientBoards.some((b) => b.id === i.boardId));
  const clientInvoices = invoices.filter(() => active?.name.split(" ")[0] && true); // demo: all
  const clientFiles = files.filter((f) => clientBoards.some((b) => b.name === f.boardName));
  const clientFeed = feed.filter((u) => clientBoards.some((b) => b.id === u.boardId));

  // Cross-channel client comms (CRM / Slack / Email) for the active client.
  const clientMessageList = useMemo(
    () => clientMessages.filter((m) => m.clientId === active?.id),
    [clientMessages, active?.id],
  );
  const messageFilterCounts = useMemo(
    () => ({
      all: clientMessageList.length,
      crm: clientMessageList.filter((m) => m.channel === "crm").length,
      slack: clientMessageList.filter((m) => m.channel === "slack").length,
      email: clientMessageList.filter((m) => m.channel === "email").length,
      internal: clientMessageList.filter((m) => m.internal).length,
    }),
    [clientMessageList],
  );
  const visibleClientMessages = useMemo(() => {
    if (messageFilter === "all") return clientMessageList;
    if (messageFilter === "internal")
      return clientMessageList.filter((m) => m.internal);
    return clientMessageList.filter((m) => m.channel === messageFilter);
  }, [clientMessageList, messageFilter]);

  // Single send handler used by both the inline composer and the dialog
  // composer. Mutates store state, surfaces a preview-only toast, and ensures
  // the user lands on the Messages tab so they can see the result.
  function handleSendClientMessage(
    clientId: string,
    input: {
      channel: ClientMessageChannel;
      body: string;
      internal: boolean;
      email?: { subject: string; to: string };
    },
  ) {
    addClientMessage({
      clientId,
      channel: input.channel,
      direction: "out",
      authorName: "Sasha Patel",
      body: input.body,
      internal: input.internal,
      email: input.email,
    });
    const meta = CHANNEL_META[input.channel];
    toast({
      title: `Posted to ${meta.label} (preview)`,
      description: meta.productionRoute,
    });
    setDetailTab("messages");
    setCommsDialog(null);
  }

  // Recent connected activity across this client's boards (work record summary).
  const clientActivity = useMemo(() => {
    const events: { at: string; role: "client" | "admin" | "system"; verb: string; context: string }[] = [];
    clientFeed.forEach((u) => {
      events.push({
        at: u.postedAt,
        role: u.authorRole === "client" ? "client" : "admin",
        verb: u.authorRole === "client" ? "replied" : "posted",
        context: boards.find((b) => b.id === u.boardId)?.name ?? "",
      });
    });
    clientFiles.forEach((f) => {
      if (f.approval === "approved")
        events.push({ at: f.uploadedAt, role: "client", verb: "approved a file", context: f.name });
      if (f.approval === "changes-requested")
        events.push({
          at: f.uploadedAt,
          role: "client",
          verb: "requested changes",
          context: f.name,
        });
    });
    return events.sort((a, b) => new Date(b.at).getTime() - new Date(a.at).getTime()).slice(0, 6);
  }, [clientFeed, clientFiles, boards]);

  return (
    <div className="space-y-5">
      <PageHeader
        eyebrow="CRM"
        title="Clients"
        subtitle={`${clients.length} ${clients.length === 1 ? "client" : "clients"} · custom fields, internal notes, and project rollups. Billing and subscriptions live in WooCommerce.`}
        actions={
          <Button
            className="gap-2"
            variant="outline"
            size="sm"
            data-testid="button-new-client"
            onClick={() => setAddClientOpen(true)}
          >
            <Plus className="size-4" /> Add manual client
          </Button>
        }
      />

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-3">
        <Tabs value={statusFilter} onValueChange={(v) => setStatusFilter(v as any)}>
          <TabsList className="h-9 bg-muted/40 p-0.5">
            <TabsTrigger value="all" className="h-8 px-2.5 text-xs">All ({clients.length})</TabsTrigger>
            <TabsTrigger value="active" className="h-8 px-2.5 text-xs">Active</TabsTrigger>
            <TabsTrigger value="paused" className="h-8 px-2.5 text-xs">Paused</TabsTrigger>
            <TabsTrigger value="prospect" className="h-8 px-2.5 text-xs">Prospects</TabsTrigger>
            <TabsTrigger value="churned" className="h-8 px-2.5 text-xs">Churned</TabsTrigger>
          </TabsList>
        </Tabs>
        <div className="relative ml-auto">
          <Search className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
          <Input
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Search clients"
            className="h-9 w-56 pl-8 text-xs"
            data-testid="input-client-search"
          />
        </div>
      </div>

      {/* 3-pane: list / detail / contextual sidebar */}
      <div className="grid gap-4 lg:grid-cols-[300px_minmax(0,1fr)_320px]">
        {/* List */}
        <SectionCard testId="card-clients-list">
          <div className="max-h-[72vh] divide-y divide-border/60 overflow-y-auto scrollbar-soft ">
            {filtered.map((c) => {
              const att = attention[c.id] ?? { unread: 0, clientApprovals: 0, staffReview: 0, blocked: false, payRisk: false };
              const hasAttention = att.unread > 0 || att.clientApprovals > 0 || att.staffReview > 0 || att.blocked || att.payRisk;
              return (
                <button
                  key={c.id}
                  onClick={() => setActiveId(c.id)}
                  className={`flex w-full items-start gap-2.5 px-4 py-3 text-left transition hover:bg-muted/40 ${active?.id === c.id ? "bg-muted/40" : ""}`}
                  data-testid={`client-row-${c.id}`}
                >
                  <span
                    className={`grid size-8 shrink-0 place-items-center rounded-md text-[11px] font-semibold text-white ${c.avatarColor}`}
                  >
                    {c.name.split(" ").map((s) => s[0]).join("")}
                  </span>
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-1.5">
                      <p className="truncate text-sm font-medium">{c.name}</p>
                      {att.unread > 0 && (
                        <span className="size-1.5 rounded-full bg-primary" title={`${att.unread} unread`} />
                      )}
                    </div>
                    <p className="truncate text-[11px] text-muted-foreground">{c.company}</p>
                    {hasAttention && (
                      <div className="mt-1 flex flex-wrap gap-1">
                        {att.clientApprovals > 0 && (
                          <span
                            className="inline-flex items-center rounded bg-orange-50 px-1 text-[10px] font-medium text-orange-700 dark:bg-orange-500/10 dark:text-orange-300"
                            data-testid={`attention-awaiting-${c.id}`}
                            title="Items waiting on client to approve"
                          >
                            {att.clientApprovals} waiting on client
                          </span>
                        )}
                        {att.staffReview > 0 && (
                          <span
                            className="inline-flex items-center rounded bg-amber-50 px-1 text-[10px] font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-300"
                            data-testid={`attention-staff-${c.id}`}
                            title="Items in internal Oversee staff review"
                          >
                            {att.staffReview} staff review
                          </span>
                        )}
                        {att.blocked && (
                          <span
                            className="inline-flex items-center rounded bg-amber-50 px-1 text-[10px] font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-300"
                            data-testid={`attention-blocked-${c.id}`}
                          >
                            Onboarding blocked
                          </span>
                        )}
                        {att.payRisk && (
                          <span
                            className="inline-flex items-center rounded bg-rose-50 px-1 text-[10px] font-medium text-rose-700 dark:bg-rose-500/10 dark:text-rose-300"
                            data-testid={`attention-pay-${c.id}`}
                          >
                            Payment risk
                          </span>
                        )}
                      </div>
                    )}
                  </div>
                  <StatusDot status={c.status} />
                </button>
              );
            })}
            {filtered.length === 0 && (
              <p className="px-4 py-12 text-center text-xs text-muted-foreground">No clients match.</p>
            )}
          </div>
        </SectionCard>

        {/* Detail */}
        {active ? (
          <div className="space-y-4">
            {/* Detail header */}
            <SectionCard testId="card-client-detail">
              <div className="flex flex-wrap items-center justify-between gap-3 p-5">
                <div className="flex items-center gap-3">
                  <span
                    className={`grid size-12 shrink-0 place-items-center rounded-md text-sm font-semibold text-white ${active.avatarColor}`}
                  >
                    {active.name.split(" ").map((s) => s[0]).join("")}
                  </span>
                  <div>
                    <p className="text-base font-semibold">{active.name}</p>
                    <p className="text-xs text-muted-foreground">{active.company}</p>
                    <div className="mt-1 flex flex-wrap gap-1">
                      {active.tags.map((t) => (
                        <Badge key={t} variant="outline" className="h-5 text-[10px]">{t}</Badge>
                      ))}
                    </div>
                  </div>
                </div>
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    className="gap-1.5"
                    onClick={() => setCommsDialog({ channel: "crm" })}
                    data-testid="button-message-client"
                    title="Open client communication composer (CRM / Slack / Email)"
                  >
                    <MessageSquare className="size-3.5" /> Message
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    className="gap-1.5"
                    onClick={() => setCommsDialog({ channel: "email" })}
                    data-testid="button-email-client"
                    title="Compose email draft (mailto handoff)"
                  >
                    <Mail className="size-3.5" /> Email
                  </Button>
                </div>
              </div>

              <div className="border-t border-border px-5 pt-3 ">
                <Tabs value={detailTab} onValueChange={(v) => setDetailTab(v as DetailTab)}>
                  <TabsList className="h-9 bg-muted/40 p-0.5">
                    <TabsTrigger value="overview" className="h-8 px-3 text-xs" data-testid="tab-client-overview">Overview</TabsTrigger>
                    <TabsTrigger value="work" className="h-8 px-3 text-xs" data-testid="tab-client-work">
                      Work ({clientBoards.length})
                    </TabsTrigger>
                    <TabsTrigger value="messages" className="h-8 px-3 text-xs" data-testid="tab-client-messages">Messages</TabsTrigger>
                    <TabsTrigger value="account" className="h-8 px-3 text-xs" data-testid="tab-client-account">Account status</TabsTrigger>
                  </TabsList>
                </Tabs>
              </div>

              <div className="max-h-[60vh] overflow-y-auto p-5 scrollbar-soft">
                {detailTab === "overview" && (
                  <div className="space-y-5">
                    {/* Connected work record summary */}
                    <div className="grid gap-3 md:grid-cols-3">
                      <SummaryStat
                        label="Lifetime value"
                        value={`$${active.lifetimeValue.toLocaleString()}`}
                        tone="neutral"
                      />
                      <SummaryStat
                        label="Monthly recurring"
                        value={`$${active.mrr.toLocaleString()}`}
                        tone="neutral"
                      />
                      <SummaryStat
                        label="Joined"
                        value={shortDate(active.joined)}
                        tone="neutral"
                      />
                    </div>

                    {/* Contact card */}
                    <div>
                      <p className="mb-2 text-[11px] uppercase tracking-wide text-muted-foreground">Contact</p>
                      <div className="grid gap-2 rounded-xl border border-border bg-card p-4 text-sm  md:grid-cols-2">
                        <div className="flex items-center gap-2">
                          <Mail className="size-4 text-muted-foreground" /> {active.email}
                        </div>
                        <div className="flex items-center gap-2">
                          <Phone className="size-4 text-muted-foreground" /> {active.phone}
                        </div>
                        <div className="flex items-center gap-2">
                          <Building2 className="size-4 text-muted-foreground" /> {active.company}
                        </div>
                        <div className="flex items-center gap-2">
                          <ClipboardList className="size-4 text-muted-foreground" /> Lifecycle:{" "}
                          <span className="font-medium capitalize">{active.lifecycleStage}</span>
                        </div>
                      </div>
                    </div>

                    {/* Recent activity using ConnectedActivityLine */}
                    <div>
                      <p className="mb-2 text-[11px] uppercase tracking-wide text-muted-foreground">
                        Recent activity
                      </p>
                      {clientActivity.length === 0 ? (
                        <p className="rounded-md border border-dashed border-border px-3 py-6 text-center text-[11px] text-muted-foreground">
                          No recent activity from {active.name.split(" ")[0]}.
                        </p>
                      ) : (
                        <ul className="divide-y divide-border/60 rounded-xl border border-border bg-card  ">
                          {clientActivity.map((e, i) => (
                            <li key={i} className="px-4 py-2.5">
                              <ConnectedActivityLine
                                role={e.role}
                                verb={`${e.verb} on ${e.context}`}
                                ago={relativeTime(e.at)}
                              />
                            </li>
                          ))}
                        </ul>
                      )}
                    </div>

                    {/* Linked boards quick rollup */}
                    <div>
                      <p className="mb-2 text-[11px] uppercase tracking-wide text-muted-foreground">
                        Linked boards ({clientBoards.length})
                      </p>
                      {clientBoards.length === 0 ? (
                        <p className="rounded-md border border-dashed border-border px-3 py-6 text-center text-[11px] text-muted-foreground">
                          No boards linked yet.
                        </p>
                      ) : (
                        <DataList>
                          {clientBoards.slice(0, 4).map((b) => (
                            <DataListRow
                              key={b.id}
                              icon={FolderKanban}
                              iconTone="neutral"
                              title={b.name}
                              description={b.description}
                              meta={`${Math.round(b.progress * 100)}% complete`}
                              testId={`client-board-${b.id}`}
                            />
                          ))}
                        </DataList>
                      )}
                    </div>
                  </div>
                )}

                {detailTab === "work" && (
                  <div className="space-y-4">
                    {clientBoards.length === 0 ? (
                      <p className="rounded-md border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                        No active boards yet.
                      </p>
                    ) : (
                      <DataList>
                        {clientBoards.map((b) => (
                          <DataListRow
                            key={b.id}
                            icon={FolderKanban}
                            title={b.name}
                            description={b.description}
                            meta={`${Math.round(b.progress * 100)}%`}
                            testId={`row-client-board-${b.id}`}
                          />
                        ))}
                      </DataList>
                    )}
                    {clientFiles.length > 0 && (
                      <div className="pt-2">
                        <p className="mb-2 text-[11px] uppercase tracking-wide text-muted-foreground">
                          Recent files
                        </p>
                        <DataList>
                          {clientFiles.slice(0, 4).map((f) => (
                            <DataListRow
                              key={f.id}
                              icon={FileText}
                              iconTone="info"
                              title={f.name}
                              description={`${f.size} · ${shortDate(f.uploadedAt)}`}
                              meta={f.kind.toUpperCase()}
                              testId={`row-client-file-${f.id}`}
                            />
                          ))}
                        </DataList>
                      </div>
                    )}
                  </div>
                )}

                {detailTab === "messages" && (
                  <div className="space-y-4" data-testid="client-messages-tab">
                    <div
                      className="flex items-start gap-2 rounded-md border border-border bg-muted/30 px-3 py-2 text-[11px] leading-relaxed text-muted-foreground"
                      data-testid="messages-context-banner"
                    >
                      <Info className="mt-0.5 size-3.5 shrink-0" />
                      <span>
                        Cross-channel comms with {active.name}. CRM posts route to OverseeCRM
                        (HighLevel) in production. Slack notes are staff-only. Email opens a
                        draft in your mail client. Project-specific replies still happen inside
                        boards.
                      </span>
                    </div>

                    <MessageFilterTabs
                      value={messageFilter}
                      onChange={setMessageFilter}
                      counts={messageFilterCounts}
                    />

                    <MessageThread
                      messages={visibleClientMessages}
                      emptyHint={
                        clientMessageList.length === 0
                          ? `No messages with ${active.name} yet. Use the composer below to start.`
                          : `No messages match this filter for ${active.name}.`
                      }
                      testId="client-message-thread"
                    />

                    <div className="rounded-xl border border-border bg-card p-3">
                      <ClientCommsComposer
                        client={active}
                        onSendMessage={(input) => {
                          handleSendClientMessage(active.id, input);
                        }}
                        compact
                      />
                    </div>
                  </div>
                )}

                {detailTab === "account" && (
                  <div className="space-y-4" data-testid="client-account-readonly">
                    <div className="rounded-xl border border-border bg-muted/30 p-4 text-xs text-muted-foreground">
                      Account, billing, and subscriptions are managed in{" "}
                      <span className="font-medium text-foreground">WooCommerce</span>. This is a
                      read-only summary.
                    </div>

                    <div className="grid gap-3 md:grid-cols-3">
                      <SummaryStat
                        label="Lifetime value"
                        value={`$${active.lifetimeValue.toLocaleString()}`}
                      />
                      <SummaryStat
                        label="Monthly recurring"
                        value={`$${active.mrr.toLocaleString()}`}
                      />
                      <SummaryStat
                        label="Status"
                        value={active.status}
                        tone={
                          active.status === "active"
                            ? "success"
                            : active.status === "paused"
                            ? "warning"
                            : "neutral"
                        }
                      />
                    </div>

                    <SectionCard title="Recent invoices">
                      <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/40 text-[11px] uppercase tracking-wide text-muted-foreground ">
                          <tr>
                            <th className="px-3 py-2 text-left font-medium">Invoice</th>
                            <th className="px-3 py-2 text-left font-medium">Service</th>
                            <th className="px-3 py-2 text-right font-medium">Amount</th>
                            <th className="px-3 py-2 text-left font-medium">Status</th>
                          </tr>
                        </thead>
                        <tbody>
                          {clientInvoices.slice(0, 5).map((inv) => (
                            <tr key={inv.id} className="border-b border-border/60 last:border-0 /60">
                              <td className="px-3 py-2 font-mono text-xs">{inv.number}</td>
                              <td className="px-3 py-2">{inv.service}</td>
                              <td className="px-3 py-2 text-right font-medium tabular-nums">
                                ${inv.amount.toLocaleString()}
                              </td>
                              <td className="px-3 py-2">
                                <Badge variant="outline" className="text-[10px]">{inv.status}</Badge>
                              </td>
                            </tr>
                          ))}
                          {clientInvoices.length === 0 && (
                            <tr>
                              <td colSpan={4} className="px-3 py-6 text-center text-xs text-muted-foreground">
                                No invoices.
                              </td>
                            </tr>
                          )}
                        </tbody>
                      </table>
                    </SectionCard>

                    <div className="flex flex-wrap gap-2 pt-1">
                      <a
                        href={`https://example.com/wp-admin/user-edit.php?user_login=${encodeURIComponent(active.email)}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-border bg-card px-3 text-xs font-medium transition hover:border-primary/40 hover:bg-muted/40"
                        data-testid="button-open-in-wp"
                      >
                        <ExternalLink className="size-3.5" /> Open in WordPress
                      </a>
                      <a
                        href={`https://example.com/wp-admin/admin.php?page=wc-orders&_customer_user=${encodeURIComponent(active.email)}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-3 text-xs font-medium text-primary-foreground shadow-sm transition hover:bg-primary/90"
                        data-testid="button-open-in-woocommerce"
                      >
                        <ExternalLink className="size-3.5" /> Open in WooCommerce
                      </a>
                    </div>
                  </div>
                )}
              </div>
            </SectionCard>
          </div>
        ) : (
          <SectionCard>
            <div className="grid place-items-center p-10 text-sm text-muted-foreground">
              Select a client to view details.
            </div>
          </SectionCard>
        )}

        {/* Contextual right rail */}
        {active && (
          <DetailRail className="hidden lg:block">
            <SectionCard title="Custom fields" testId="card-client-custom-fields">
              <div className="space-y-2 p-4">
                {active.customFields.map((cf) => (
                  <div
                    key={cf.label}
                    className="flex justify-between gap-3 rounded-md border border-border bg-card px-3 py-2 text-xs "
                  >
                    <span className="text-muted-foreground">{cf.label}</span>
                    <span className="font-medium text-foreground">{cf.value}</span>
                  </div>
                ))}
                <Button
                  variant="outline"
                  size="sm"
                  className="w-full justify-center border-dashed text-muted-foreground hover:text-foreground"
                  onClick={() => {
                    setNewField({ label: "", value: "" });
                    setAddFieldOpen(true);
                  }}
                  data-testid="button-add-custom-field"
                >
                  <Plus className="size-3" /> Add field
                </Button>
              </div>
            </SectionCard>

            <SectionCard title="Internal notes" testId="card-client-notes">
              <div className="space-y-2 p-4">
                {active.internalNotes.map((n) => (
                  <div
                    key={n.id}
                    className="rounded-md border border-amber-200 bg-amber-50/40 px-3 py-2 text-xs dark:border-amber-900/40 dark:bg-amber-950/20"
                  >
                    <div className="flex items-center justify-between">
                      <span className="font-semibold text-foreground">{n.author}</span>
                      <span className="text-[10px] text-muted-foreground">
                        {shortDateTime(n.at)}
                      </span>
                    </div>
                    <p className="mt-1 leading-relaxed text-muted-foreground">{n.body}</p>
                  </div>
                ))}
                {active.internalNotes.length === 0 && (
                  <p className="rounded-md border border-dashed px-3 py-4 text-center text-[11px] text-muted-foreground">
                    No notes yet.
                  </p>
                )}
                <Textarea
                  value={note}
                  onChange={(e) => setNote(e.target.value)}
                  placeholder="Add an internal note (clients can't see this)…"
                  rows={3}
                  className="text-xs"
                  data-testid="textarea-internal-note"
                />
                <Button
                  size="sm"
                  className="w-full gap-1.5"
                  disabled={!note.trim() || !active}
                  onClick={() => {
                    if (!active) return;
                    addClientNote(active.id, note);
                    setNote("");
                    toast({
                      title: "Note added",
                      description: `Internal note saved to ${active.name}'s record.`,
                    });
                  }}
                  data-testid="button-add-note"
                >
                  <Send className="size-3" /> Add note
                </Button>
              </div>
            </SectionCard>
          </DetailRail>
        )}
      </div>

      {/* Cross-channel comms composer dialog (header Message / Email triggers) */}
      <Dialog
        open={!!commsDialog}
        onOpenChange={(open) => {
          if (!open) setCommsDialog(null);
        }}
      >
        <DialogContent className="max-w-xl" data-testid="dialog-comms-composer">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              {active ? `Message ${active.name}` : "Message client"}
              {commsDialog && (
                <ChannelBadge
                  channel={commsDialog.channel}
                  testId="dialog-comms-channel-badge"
                />
              )}
            </DialogTitle>
            <DialogDescription>
              {commsDialog ? CHANNEL_META[commsDialog.channel].productionRoute : null}
              {" "}Preview build — nothing leaves this app. The message is recorded
              in the thread so you can see what would be sent.
            </DialogDescription>
          </DialogHeader>
          {active && commsDialog && (
            <ClientCommsComposer
              client={active}
              defaultChannel={commsDialog.channel}
              onSendMessage={(input) => {
                handleSendClientMessage(active.id, input);
              }}
            />
          )}
        </DialogContent>
      </Dialog>

      {/* Add manual client dialog */}
      <Dialog open={addClientOpen} onOpenChange={setAddClientOpen}>
        <DialogContent data-testid="dialog-add-client">
          <DialogHeader>
            <DialogTitle>Add manual client</DialogTitle>
            <DialogDescription>
              Most clients are added automatically when they purchase a service. Use this for prospects or referrals.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-3 py-2">
            <div className="space-y-1.5">
              <Label htmlFor="new-client-name">Full name</Label>
              <Input
                id="new-client-name"
                value={newClient.name}
                onChange={(e) => setNewClient((p) => ({ ...p, name: e.target.value }))}
                placeholder="Jordan Reyes"
                data-testid="input-new-client-name"
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="new-client-company">Company</Label>
              <Input
                id="new-client-company"
                value={newClient.company}
                onChange={(e) => setNewClient((p) => ({ ...p, company: e.target.value }))}
                placeholder="Reyes Studio"
                data-testid="input-new-client-company"
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="new-client-email">Email</Label>
              <Input
                id="new-client-email"
                type="email"
                value={newClient.email}
                onChange={(e) => setNewClient((p) => ({ ...p, email: e.target.value }))}
                placeholder="jordan@reyesstudio.com"
                data-testid="input-new-client-email"
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="ghost" onClick={() => setAddClientOpen(false)} data-testid="button-cancel-add-client">
              Cancel
            </Button>
            <Button
              onClick={() => {
                if (!newClient.name.trim() || !newClient.company.trim() || !newClient.email.trim()) {
                  toast({ title: "Fill in all fields", description: "Name, company, and email are required." });
                  return;
                }
                const id = addClient(newClient);
                setActiveId(id);
                setNewClient({ name: "", company: "", email: "" });
                setAddClientOpen(false);
                toast({
                  title: "Client added",
                  description: `${newClient.name} (${newClient.company}) is now in your CRM.`,
                });
              }}
              data-testid="button-confirm-add-client"
            >
              Add client
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Add custom field dialog */}
      <Dialog open={addFieldOpen} onOpenChange={setAddFieldOpen}>
        <DialogContent data-testid="dialog-add-custom-field">
          <DialogHeader>
            <DialogTitle>Add custom field</DialogTitle>
            <DialogDescription>
              Stored on this client record only. Use for things like Stripe ID, referral source, or contract end date.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-3 py-2">
            <div className="space-y-1.5">
              <Label htmlFor="new-field-label">Label</Label>
              <Input
                id="new-field-label"
                value={newField.label}
                onChange={(e) => setNewField((p) => ({ ...p, label: e.target.value }))}
                placeholder="Referral source"
                data-testid="input-new-field-label"
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="new-field-value">Value</Label>
              <Input
                id="new-field-value"
                value={newField.value}
                onChange={(e) => setNewField((p) => ({ ...p, value: e.target.value }))}
                placeholder="Conference 2026"
                data-testid="input-new-field-value"
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="ghost" onClick={() => setAddFieldOpen(false)} data-testid="button-cancel-add-field">
              Cancel
            </Button>
            <Button
              onClick={() => {
                if (!active) return;
                if (!newField.label.trim() || !newField.value.trim()) {
                  toast({ title: "Fill in label and value", description: "Both fields are required." });
                  return;
                }
                addClientCustomField(active.id, newField.label, newField.value);
                setNewField({ label: "", value: "" });
                setAddFieldOpen(false);
                toast({ title: "Field added", description: `${newField.label} saved on ${active.name}.` });
              }}
              data-testid="button-confirm-add-field"
            >
              Add field
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

function StatusDot({ status }: { status: ClientRecord["status"] }) {
  const cls =
    status === "active"
      ? "bg-emerald-500"
      : status === "paused"
        ? "bg-amber-500"
        : status === "prospect"
          ? "bg-sky-500"
          : "bg-muted-foreground/40";
  return <span className={`mt-1.5 size-1.5 shrink-0 rounded-full ${cls}`} title={status} />;
}
