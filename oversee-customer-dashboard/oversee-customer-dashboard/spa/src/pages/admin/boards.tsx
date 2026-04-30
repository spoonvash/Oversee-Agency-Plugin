// Admin Boards — refactored to use shared primitives.
// Heavy table replaced with SectionCard + DataListRow. Local SummaryStat
// removed in favor of shared SummaryStat. Light card treatment, one accent.
import { useEffect, useMemo, useState } from "react";
import {
  AlertTriangle,
  CalendarClock,
  ChevronRight,
  Eye,
  KanbanSquare,
  MoreHorizontal,
  Pencil,
  Plus,
  Trash2,
  UserCircle2,
  CheckCircle2,
} from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useToast } from "@/hooks/use-toast";
import { useDemoStore, type Board, type FileEntry } from "@/lib/demo-store";
import { relativeTime } from "@/lib/format";
import { getItemApproval, getFileApproval } from "@/lib/approval-clarity";
import { WorkflowBoard, subitemProgress } from "@/components/workflow-board";
import { CreateBoardWizard } from "@/components/create-board-wizard";
import { PreviewListCard } from "@/components/document-preview";
import { PreviewApprovalDialog } from "@/components/preview-approval-dialog";
import { BarFill } from "@/components/dynamic-styles";
import {
  PageHeader,
  SectionCard,
  SummaryStat,
  ConnectedActivityLine,
} from "@/components/shared";

type BoardStatus = "active" | "on-hold" | "completed" | "archived";

const STATUS_LABEL: Record<BoardStatus, string> = {
  active: "Active",
 "on-hold": "On hold",
  completed: "Completed",
  archived: "Archived",
};

const STATUS_DESC: Record<BoardStatus, string> = {
  active: "Work is moving. Items are flowing through stages.",
 "on-hold": "Paused. Likely blocked on the client or budget.",
  completed: "All items done and signed off. Reference only.",
  archived: "Hidden from default views. Kept for history.",
};

const STATUS_DOT: Record<BoardStatus, string> = {
  active: "bg-emerald-500",
 "on-hold": "bg-amber-500",
  completed: "bg-sky-500",
  archived: "bg-zinc-400",
};

const STATUS_TEXT: Record<BoardStatus, string> = {
  active: "text-emerald-700 dark:text-emerald-300",
 "on-hold": "text-amber-700 dark:text-amber-300",
  completed: "text-sky-700 dark:text-sky-300",
  archived: "text-muted-foreground",
};

const SERVICE_TYPES = [
 "Brand strategy",
 "Web design",
 "Web development",
 "SEO",
 "Content",
 "Paid ads",
 "Video",
 "Other",
];

function fmtDue(iso?: string) {
  if (!iso) return null;
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return null;
  return d.toLocaleDateString(undefined, { month: "short", day: "numeric" });
}

function isoToInput(iso?: string) {
  if (!iso) return "";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "";
  return d.toISOString().slice(0, 10);
}

function dueRisk(iso?: string): "due-soon" | "overdue" | "ok" | "none" {
  if (!iso) return "none";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "none";
  const now = Date.now();
  const diff = d.getTime() - now;
  if (diff < 0) return "overdue";
  if (diff < 7 * 24 * 60 * 60 * 1000) return "due-soon";
  return "ok";
}

export default function AdminBoards() {
  const { boards, items, team, updateBoard, deleteBoard, files } = useDemoStore();
  const { toast } = useToast();

  const [openBoard, setOpenBoard] = useState<Board | null>(null);
  const [createOpen, setCreateOpen] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [deletingId, setDeletingId] = useState<string | null>(null);
  const [previewFile, setPreviewFile] = useState<FileEntry | null>(null);

  const [form, setForm] = useState({
    name: "",
    clientEmail: "",
    serviceType: "",
    status: "active" as BoardStatus,
    due: "",
    owner: "",
    description: "",
    slackChannel: "",
  });

  const editing = editingId ? boards.find((b) => b.id === editingId) : null;
  const deleting = deletingId ? boards.find((b) => b.id === deletingId) : null;

  useEffect(() => {
    if (!editing) return;
    setForm({
      name: editing.name ?? "",
      clientEmail: editing.clientEmail ?? "",
      serviceType: editing.serviceType ?? "",
      status: (editing.status as BoardStatus) ?? "active",
      due: isoToInput(editing.due),
      owner: editing.owner ?? "",
      description: editing.description ?? "",
      slackChannel: editing.slackChannel ?? "",
    });
  }, [editingId]); // eslint-disable-line react-hooks/exhaustive-deps

  const rows = useMemo(() => {
    return boards.map((b) => {
      const myItems = items.filter((i) => i.boardId === b.id);
      const ratios = myItems.map((i) => subitemProgress(i).ratio);
      const pct =
        ratios.length === 0
          ? 0
          : Math.round((ratios.reduce((a, c) => a + c, 0) / ratios.length) * 100);
      const stuck = myItems.filter((i) => i.status === "stuck").length;
      const reviewItems = myItems.filter((i) => i.status === "review");
      const clientReview = reviewItems.filter(
        (i) => getItemApproval(i).status === "needs_client_approval",
      ).length;
      const staffReview = reviewItems.filter(
        (i) => getItemApproval(i).status === "needs_staff_review",
      ).length;
      const boardPendingFiles = files.filter(
        (f) => f.boardName === b.name && f.approval === "pending",
      );
      const boardClientFiles = boardPendingFiles.filter(
        (f) => getFileApproval(f).status === "needs_client_approval",
      ).length;
      const boardStaffFiles = boardPendingFiles.filter(
        (f) => getFileApproval(f).status === "needs_staff_review",
      ).length;
      const status = (b.status as BoardStatus) ?? "active";
      const risk = dueRisk(b.due);
      const allActivity: { at: string; role: "client" | "admin" | "system"; verb: string }[] = [];
      myItems.forEach((it) => {
        it.discussion.forEach((d) => {
          allActivity.push({
            at: d.postedAt,
            role: d.authorRole as any,
            verb: d.authorRole === "client" ? "replied" : d.authorRole === "system" ? "updated" : "posted",
          });
        });
        it.activity.forEach((a) => {
          allActivity.push({ at: a.at, role: "admin", verb: a.text.split(" ").slice(0, 2).join(" ") });
        });
      });
      files.filter((f) => f.boardName === b.name).forEach((f) => {
        if (f.approval === "approved") allActivity.push({ at: f.uploadedAt, role: "client", verb: "approved a file" });
        if (f.approval === "changes-requested") allActivity.push({ at: f.uploadedAt, role: "client", verb: "requested changes" });
      });
      const lastActivity = allActivity.sort((a, c) => new Date(c.at).getTime() - new Date(a.at).getTime())[0];
      return {
        b,
        myItems,
        pct,
        stuck,
        clientReview,
        staffReview,
        boardClientFiles,
        boardStaffFiles,
        status,
        risk,
        lastActivity,
      };
    });
  }, [boards, items, files]);

  const summary = useMemo(() => {
    const active = rows.filter((r) => r.status === "active").length;
    const pending = files.filter((f) => f.approval === "pending");
    const awaitingClient = pending.filter(
      (f) => getFileApproval(f).status === "needs_client_approval",
    ).length;
    const awaitingStaff = pending.filter(
      (f) => getFileApproval(f).status === "needs_staff_review",
    ).length;
    const atRisk = rows.filter((r) => r.risk === "overdue" || r.stuck > 0).length;
    const dueWeek = rows.filter((r) => r.risk === "due-soon" || r.risk === "overdue").length;
    return { active, awaitingClient, awaitingStaff, atRisk, dueWeek };
  }, [rows, files]);

  // Split into the two real groups so the rail can show ownership.
  const needsClientApproval = useMemo(
    () =>
      files
        .filter((f) => f.approval === "pending")
        .filter((f) => f.kind === "image" || f.kind === "pdf" || f.kind === "doc" || f.kind === "video")
        .filter((f) => getFileApproval(f).status === "needs_client_approval")
        .sort((a, b) => new Date(b.uploadedAt).getTime() - new Date(a.uploadedAt).getTime())
        .slice(0, 6),
    [files],
  );
  const needsStaffReview = useMemo(
    () =>
      files
        .filter((f) => f.approval === "pending")
        .filter((f) => f.kind === "image" || f.kind === "pdf" || f.kind === "doc" || f.kind === "video")
        .filter((f) => getFileApproval(f).status === "needs_staff_review")
        .sort((a, b) => new Date(b.uploadedAt).getTime() - new Date(a.uploadedAt).getTime())
        .slice(0, 4),
    [files],
  );

  const recentlyDecided = useMemo(
    () =>
      files
        .filter((f) => f.approval === "approved" || f.approval === "changes-requested")
        .sort((a, b) => new Date(b.uploadedAt).getTime() - new Date(a.uploadedAt).getTime())
        .slice(0, 5),
    [files],
  );

  if (openBoard) {
    const fresh = boards.find((b) => b.id === openBoard.id);
    if (!fresh) {
      setOpenBoard(null);
      return null;
    }
    return (
      <div className="space-y-3">
        <Button
          variant="ghost"
          size="sm"
          onClick={() => setOpenBoard(null)}
          className="gap-1 text-xs"
          data-testid="button-back-to-boards"
        >
          ← All boards
        </Button>
        <WorkflowBoard board={fresh} role="admin" />
      </div>
    );
  }

  const handleSave = () => {
    if (!editingId) return;
    if (!form.name.trim()) {
      toast({
        title: "Project name required",
        description: "Give the board a name your team and the client will recognize.",
        variant: "destructive",
      });
      return;
    }
    updateBoard(editingId, {
      name: form.name.trim(),
      clientEmail: form.clientEmail.trim(),
      description: form.description.trim(),
      serviceType: form.serviceType || undefined,
      status: form.status,
      due: form.due ? new Date(form.due).toISOString() : undefined,
      owner: form.owner || undefined,
      slackChannel: form.slackChannel.trim() || undefined,
    });
    toast({
      title: "Project updated",
      description: `${form.name.trim()} saved.`,
    });
    setEditingId(null);
  };

  const handleDelete = () => {
    if (!deletingId || !deleting) return;
    const name = deleting.name;
    deleteBoard(deletingId);
    toast({
      title: "Project deleted",
      description: `${name} and its items were removed.`,
    });
    setDeletingId(null);
  };

  return (
    <div className="space-y-6" data-testid="admin-boards-page">
      <PageHeader
        title="Boards"
        subtitle="Manage client projects, approvals, and active work."
        actions={
          <Button
            size="sm"
            className="h-9 gap-1.5"
            onClick={() => setCreateOpen(true)}
            data-testid="button-create-board"
          >
            <Plus className="size-4" /> Create board
          </Button>
        }
      />

      {/* Summary strip — shared SummaryStat */}
      <div className="grid grid-cols-2 gap-3 md:grid-cols-5">
        <SummaryStat label="Active boards" value={summary.active} tone="success" testId="stat-active" />
        <SummaryStat label="Waiting on client" value={summary.awaitingClient} tone={summary.awaitingClient > 0 ? "primary" : "neutral"} testId="stat-awaiting" hint="client must approve" />
        <SummaryStat label="Staff review" value={summary.awaitingStaff} tone={summary.awaitingStaff > 0 ? "warning" : "neutral"} testId="stat-staff-review" hint="Oversee internal" />
        <SummaryStat label="At risk" value={summary.atRisk} tone={summary.atRisk > 0 ? "danger" : "neutral"} testId="stat-at-risk" />
        <SummaryStat label="Due this week" value={summary.dueWeek} tone={summary.dueWeek > 0 ? "warning" : "neutral"} testId="stat-due-week" />
      </div>

      {/* Main grid */}
      <div className="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <SectionCard
          className="xl:col-span-8"
          title="Active boards"
          hint={`${rows.length} project${rows.length === 1 ? "" : "s"} · click Open to manage items.`}
          testId="card-active-boards"
        >
          {rows.length === 0 ? (
            <div className="grid place-items-center px-6 py-16 text-center">
              <div className="mx-auto grid size-12 place-items-center rounded-full bg-muted">
                <KanbanSquare className="size-5 text-muted-foreground" />
              </div>
              <p className="mt-3 text-sm font-semibold">No boards yet</p>
              <p className="mt-1 max-w-sm text-xs text-muted-foreground">
                Create your first project board to start managing client work.
              </p>
              <Button size="sm" className="mt-4 gap-1.5" onClick={() => setCreateOpen(true)}>
                <Plus className="size-3.5" /> Create board
              </Button>
            </div>
          ) : (
            <ul className="divide-y divide-border/60">
              {rows.map(({ b, myItems, pct, stuck, clientReview, staffReview, boardClientFiles, boardStaffFiles, status, risk, lastActivity }) => {
                const due = fmtDue(b.due);
                return (
                  <li key={b.id} data-testid={`row-board-${b.id}`}>
                    <div className="px-5 py-4 transition hover:bg-muted/40">
                      {/* Top row: identity + actions */}
                      <div className="flex items-start gap-3">
                        <button
                          type="button"
                          onClick={() => setOpenBoard(b)}
                          className="flex min-w-0 flex-1 items-start gap-3 text-left"
                          data-testid={`link-open-board-${b.id}`}
                        >
                          <div className="grid size-9 shrink-0 place-items-center rounded-lg border border-border bg-muted text-foreground/70">
                            <KanbanSquare className="size-4" />
                          </div>
                          <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                              <p
                                className="truncate text-sm font-semibold leading-tight"
                                data-testid={`text-board-name-${b.id}`}
                              >
                                {b.name}
                              </p>
                              <span
                                className={`inline-flex items-center gap-1.5 text-[11px] font-medium ${STATUS_TEXT[status]}`}
                                data-testid={`status-board-${b.id}`}
                                title={STATUS_DESC[status]}
                              >
                                <span className={`size-1.5 rounded-full ${STATUS_DOT[status]}`} />
                                {STATUS_LABEL[status]}
                              </span>
                            </div>
                            <p className="mt-0.5 line-clamp-1 text-[11px] text-muted-foreground">
                              {myItems.length} item{myItems.length === 1 ? "" : "s"}
                              {b.serviceType && ` · ${b.serviceType}`}
                              {b.clientEmail && ` · ${b.clientEmail}`}
                            </p>
                          </div>
                        </button>
                        <div className="flex shrink-0 items-center gap-1">
                        <Button
                          size="sm"
                          variant="default"
                          className="h-8 gap-1 px-2.5 text-[12px]"
                          onClick={() => setOpenBoard(b)}
                          data-testid={`button-open-board-${b.id}`}
                        >
                          Open <ChevronRight className="size-3" />
                        </Button>
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button
                              size="sm"
                              variant="ghost"
                              className="h-8 w-8 p-0"
                              data-testid={`button-board-menu-${b.id}`}
                              aria-label={`More actions for ${b.name}`}
                            >
                              <MoreHorizontal className="size-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end" className="w-44">
                            <DropdownMenuItem
                              onClick={() => setEditingId(b.id)}
                              data-testid={`button-edit-board-${b.id}`}
                            >
                              <Pencil className="mr-2 size-3.5" /> Edit details
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                              onClick={() => setDeletingId(b.id)}
                              data-testid={`button-delete-board-${b.id}`}
                              className="text-rose-600 focus:bg-rose-50 focus:text-rose-700 dark:focus:bg-rose-950/40"
                            >
                              <Trash2 className="mr-2 size-3.5" /> Delete project
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                        </div>
                      </div>

                      {/* Bottom strip: progress, due, owner, counts, last activity */}
                      <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 pl-12">
                        <div className="flex items-center gap-2" title={`${pct}% of items complete`}>
                          <div className="h-1.5 w-24 overflow-hidden rounded-full bg-muted">
                            <BarFill pct={pct} className="block h-full rounded-full bg-foreground/70" />
                          </div>
                          <span className="text-[11px] tabular-nums text-muted-foreground">{pct}%</span>
                        </div>

                        {due && (
                          <span
                            className={`inline-flex items-center gap-1 text-[12px] ${
                              risk === "overdue"
                                ? "font-semibold text-rose-700 dark:text-rose-300"
                                : risk === "due-soon"
                                ? "font-semibold text-amber-700 dark:text-amber-300"
                                : "text-muted-foreground"
                            }`}
                            title={risk === "overdue" ? "Overdue" : risk === "due-soon" ? "Due soon" : "On track"}
                          >
                            <CalendarClock className="size-3" />
                            {due}
                          </span>
                        )}
                        {b.owner && (
                          <span className="inline-flex items-center gap-1.5 text-[12px] text-muted-foreground">
                            <UserCircle2 className="size-3.5" />
                            {b.owner}
                          </span>
                        )}
                        {clientReview + boardClientFiles > 0 && (
                          <Badge
                            variant="outline"
                            className="border-orange-300 text-[10px] font-semibold text-orange-700 dark:border-orange-900/60 dark:text-orange-300"
                            data-testid={`badge-client-approval-${b.id}`}
                            title="Items awaiting client decision"
                          >
                            {clientReview + boardClientFiles} waiting on client
                          </Badge>
                        )}
                        {staffReview + boardStaffFiles > 0 && (
                          <Badge
                            variant="outline"
                            className="border-amber-300 text-[10px] font-semibold text-amber-800 dark:border-amber-900/60 dark:text-amber-300"
                            data-testid={`badge-staff-review-${b.id}`}
                            title="Items in internal Oversee staff review"
                          >
                            {staffReview + boardStaffFiles} staff review
                          </Badge>
                        )}
                        {stuck > 0 && (
                          <Badge variant="outline" className="border-rose-200 text-[10px] text-rose-700 dark:border-rose-900/50 dark:text-rose-300">
                            {stuck} stuck
                          </Badge>
                        )}
                        {lastActivity && (
                          <span className="ml-auto inline-flex shrink-0">
                            <ConnectedActivityLine
                              role={lastActivity.role}
                              verb={lastActivity.verb}
                              ago={relativeTime(lastActivity.at)}
                              testId={`text-board-activity-${b.id}`}
                            />
                          </span>
                        )}
                      </div>
                    </div>
                  </li>
                );
              })}
            </ul>
          )}
        </SectionCard>

        {/* Needs review rail — split into who decides */}
        <div className="space-y-4 xl:col-span-4">
          <SectionCard
            title="Waiting on client — to approve"
            hint={
              needsClientApproval.length === 0
                ? "Nothing waiting on a client."
                : `${needsClientApproval.length} preview${needsClientApproval.length === 1 ? "" : "s"} · approver: client.`
            }
            tone={needsClientApproval.length > 0 ? "primary" : "success"}
            trailing={
              needsClientApproval.length > 0 && (
                <Badge
                  variant="outline"
                  className="border-orange-300 bg-orange-50 text-[10px] font-semibold text-orange-800 dark:border-orange-900/60 dark:bg-orange-950/40 dark:text-orange-200"
                  data-testid="badge-needs-review-count"
                >
                  {needsClientApproval.length}
                </Badge>
              )
            }
            testId="card-needs-review"
          >
            <div className="grid grid-cols-1 gap-2 p-3 sm:grid-cols-2 xl:grid-cols-1">
              {needsClientApproval.length === 0 ? (
                <div className="col-span-full grid place-items-center px-2 py-10 text-center">
                  <div className="mx-auto grid size-10 place-items-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400">
                    <CheckCircle2 className="size-5" />
                  </div>
                  <p className="mt-3 text-[13px] font-semibold">Nothing waiting</p>
                  <p className="mt-0.5 text-[11px] text-muted-foreground">
                    New deliverables will appear here.
                  </p>
                </div>
              ) : (
                needsClientApproval.map((f) => (
                  <PreviewListCard key={f.id} file={f} role="admin" onOpen={() => setPreviewFile(f)} />
                ))
              )}
            </div>
          </SectionCard>

          {needsStaffReview.length > 0 && (
            <SectionCard
              title="Staff review — Oversee internal"
              hint={`${needsStaffReview.length} file${needsStaffReview.length === 1 ? "" : "s"} for internal review before going to a client.`}
              tone="warning"
              trailing={
                <Badge
                  variant="outline"
                  className="border-amber-300 bg-amber-50 text-[10px] font-semibold text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200"
                  data-testid="badge-staff-review-count"
                >
                  {needsStaffReview.length}
                </Badge>
              }
              testId="card-staff-review"
            >
              <div className="grid grid-cols-1 gap-2 p-3 sm:grid-cols-2 xl:grid-cols-1">
                {needsStaffReview.map((f) => (
                  <PreviewListCard key={f.id} file={f} role="admin" onOpen={() => setPreviewFile(f)} />
                ))}
              </div>
            </SectionCard>
          )}
        </div>
      </div>

      {/* Recently decided footer list */}
      {recentlyDecided.length > 0 && (
        <SectionCard
          title="Recently decided"
          hint="Last 5 client decisions across all boards."
          testId="card-recently-decided"
        >
          <ul className="divide-y divide-border/60">
            {recentlyDecided.map((f) => (
              <li key={f.id}>
                <button
                  type="button"
                  onClick={() => setPreviewFile(f)}
                  className="flex w-full items-center gap-3 px-5 py-3 text-left transition hover:bg-muted/40"
                  data-testid={`row-decided-${f.id}`}
                >
                  <span
                    className={`grid size-8 shrink-0 place-items-center rounded-md ${
                      f.approval === "approved"
                        ? "bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"
                        : "bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300"
                    }`}
                  >
                    {f.approval === "approved" ? <CheckCircle2 className="size-4" /> : <AlertTriangle className="size-4" />}
                  </span>
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-[13px] font-medium">{f.name}</p>
                    <p className="truncate text-[11px] text-muted-foreground">
                      {f.boardName || "—"} · {f.approval === "approved" ? "Approved" : "Changes requested"} ·{" "}
                      {new Date(f.uploadedAt).toLocaleDateString(undefined, { month: "short", day: "numeric" })}
                    </p>
                  </div>
                  <Eye className="size-4 shrink-0 text-muted-foreground" />
                </button>
              </li>
            ))}
          </ul>
        </SectionCard>
      )}

      <CreateBoardWizard
        open={createOpen}
        onOpenChange={setCreateOpen}
        onCreated={(id) => {
          const created = boards.find((b) => b.id === id);
          if (created) setOpenBoard(created);
        }}
      />

      <PreviewApprovalDialog file={previewFile} onClose={() => setPreviewFile(null)} role="admin" />

      {/* Edit project sheet */}
      <Sheet open={!!editingId} onOpenChange={(o) => !o && setEditingId(null)}>
        <SheetContent
          side="right"
          className="flex w-full flex-col gap-0 p-0 sm:max-w-lg"
          data-testid="sheet-edit-board"
        >
          <SheetHeader className="border-b px-6 py-4 ">
            <SheetTitle className="text-base">Edit project</SheetTitle>
            <SheetDescription className="text-xs">
              Update project details. Changes save to this preview workspace immediately.
            </SheetDescription>
          </SheetHeader>

          <div className="flex-1 space-y-4 overflow-y-auto px-6 py-5">
            <div className="space-y-1.5">
              <Label htmlFor="edit-board-name" className="text-xs">Project name</Label>
              <Input
                id="edit-board-name"
                value={form.name}
                onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                data-testid="input-edit-board-name"
              />
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label htmlFor="edit-board-client" className="text-xs">Client email</Label>
                <Input
                  id="edit-board-client"
                  type="email"
                  value={form.clientEmail}
                  onChange={(e) => setForm((f) => ({ ...f, clientEmail: e.target.value }))}
                  data-testid="input-edit-board-client"
                />
              </div>
              <div className="space-y-1.5">
                <Label className="text-xs">Service type</Label>
                <Select
                  value={form.serviceType || "none"}
                  onValueChange={(v) => setForm((f) => ({ ...f, serviceType: v === "none" ? "" : v }))}
                >
                  <SelectTrigger data-testid="select-edit-board-service">
                    <SelectValue placeholder="Choose…" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">— None —</SelectItem>
                    {SERVICE_TYPES.map((s) => (
                      <SelectItem key={s} value={s}>{s}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label className="text-xs">Status</Label>
                <Select
                  value={form.status}
                  onValueChange={(v) => setForm((f) => ({ ...f, status: v as BoardStatus }))}
                >
                  <SelectTrigger data-testid="select-edit-board-status">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {(Object.keys(STATUS_LABEL) as BoardStatus[]).map((s) => (
                      <SelectItem key={s} value={s}>{STATUS_LABEL[s]}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="edit-board-due" className="text-xs">Due date</Label>
                <Input
                  id="edit-board-due"
                  type="date"
                  value={form.due}
                  onChange={(e) => setForm((f) => ({ ...f, due: e.target.value }))}
                  data-testid="input-edit-board-due"
                />
              </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label className="text-xs">Owner</Label>
                <Select
                  value={form.owner || "none"}
                  onValueChange={(v) => setForm((f) => ({ ...f, owner: v === "none" ? "" : v }))}
                >
                  <SelectTrigger data-testid="select-edit-board-owner">
                    <SelectValue placeholder="Choose…" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">— Unassigned —</SelectItem>
                    {team.map((t) => (
                      <SelectItem key={t.id} value={t.name}>{t.name} · {t.role}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="edit-board-slack" className="text-xs">
                  Slack channel <span className="text-muted-foreground">(optional)</span>
                </Label>
                <Input
                  id="edit-board-slack"
                  placeholder="#brand-northstar"
                  value={form.slackChannel}
                  onChange={(e) => setForm((f) => ({ ...f, slackChannel: e.target.value }))}
                  data-testid="input-edit-board-slack"
                />
              </div>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="edit-board-desc" className="text-xs">Description</Label>
              <Textarea
                id="edit-board-desc"
                rows={4}
                value={form.description}
                onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                data-testid="input-edit-board-description"
              />
              <p className="text-[11px] text-muted-foreground">
                Visible to the client on their Work board.
              </p>
            </div>
          </div>

          <SheetFooter className="border-t px-6 py-4 ">
            <Button variant="ghost" size="sm" onClick={() => setEditingId(null)} data-testid="button-cancel-edit-board">
              Cancel
            </Button>
            <Button
              size="sm"
              onClick={handleSave}
              data-testid="button-save-edit-board"
              className="bg-primary text-primary-foreground hover:bg-primary/90"
            >
              Save changes
            </Button>
          </SheetFooter>
        </SheetContent>
      </Sheet>

      {/* Delete confirm */}
      <AlertDialog open={!!deletingId} onOpenChange={(o) => !o && setDeletingId(null)}>
        <AlertDialogContent data-testid="dialog-delete-board">
          <AlertDialogHeader>
            <AlertDialogTitle className="flex items-center gap-2 text-base">
              <AlertTriangle className="size-4 text-rose-600" />
              Delete this project?
            </AlertDialogTitle>
            <AlertDialogDescription className="text-sm">
              <span className="font-medium text-foreground">{deleting?.name}</span> and all of its
              items, updates, and activity will be removed. This cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel data-testid="button-cancel-delete-board">Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDelete}
              className="bg-rose-600 text-white hover:bg-rose-700 focus:ring-rose-600"
              data-testid="button-confirm-delete-board"
            >
              Yes, delete project
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
