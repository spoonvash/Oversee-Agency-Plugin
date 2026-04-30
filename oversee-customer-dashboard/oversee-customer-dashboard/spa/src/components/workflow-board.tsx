// WorkflowBoard — agency-friendly project workflow surface.
// Replaces the previous MondayBoard. The default and primary view is the stage
// board ("Workflow"). Optional secondary tabs: Files (board-wide deliverables)
// and Activity (timeline). There is no "Main Table" view anymore.
//
// Workflow rules:
//   • Admins drag cards between stages OR use an explicit stage menu on each card.
//   • Clients see read-only stage badges and can open items to reply/approve;
//     they cannot change internal workflow stages.
//   • Empty columns get a small, calm hint instead of a giant blank panel.
//   • Stage labels are agency-language: Backlog / Working on it / Waiting on
//     client / In review / Approved / Stuck (matches store's WORKFLOW_DEFAULT).

import { useMemo, useState } from "react";
import {
  Activity as ActivityIcon,
  CalendarClock,
  ChevronDown,
  Folder,
  GripVertical,
  Image as ImageIcon,
  Info,
  LayoutGrid,
  MessageSquare,
  MoreHorizontal,
  Paperclip,
  Plus,
  Search,
  ThumbsUp,
} from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  type Board,
  type BoardItem,
  type ItemStatus,
  useDemoStore,
} from "@/lib/demo-store";
import { ItemDetailSheet } from "@/components/item-detail-sheet";
import { BoardFilesView } from "@/components/board-files-view";
import { BarFill } from "@/components/dynamic-styles";
import { useToast } from "@/hooks/use-toast";
import { getItemApproval } from "@/lib/approval-clarity";

type View = "workflow" | "files" | "activity";

// Stage tint — calm, semantic, low-orange. Used for column accent + card border.
const STAGE_TINT: Record<string, { dot: string; ring: string; soft: string }> = {
  Backlog: {
    dot: "bg-zinc-400 dark:bg-muted0",
    ring: "border-border",
    soft: "bg-surface-elevated",
  },
 "Working On It": {
    dot: "bg-blue-500",
    ring: "border-blue-200/70 dark:border-blue-900/60",
    soft: "bg-blue-50/40 dark:bg-blue-950/20",
  },
 "Waiting on Client": {
    dot: "bg-amber-500",
    ring: "border-amber-200/70 dark:border-amber-900/60",
    soft: "bg-amber-50/40 dark:bg-amber-950/20",
  },
 "In Review": {
    dot: "bg-orange-500",
    ring: "border-orange-200/70 dark:border-orange-900/60",
    soft: "bg-orange-50/40 dark:bg-orange-950/20",
  },
  Approved: {
    dot: "bg-emerald-500",
    ring: "border-emerald-200/70 dark:border-emerald-900/60",
    soft: "bg-emerald-50/40 dark:bg-emerald-950/20",
  },
  Done: {
    dot: "bg-emerald-500",
    ring: "border-emerald-200/70 dark:border-emerald-900/60",
    soft: "bg-emerald-50/40 dark:bg-emerald-950/20",
  },
  Stuck: {
    dot: "bg-rose-500",
    ring: "border-rose-200/70 dark:border-rose-900/60",
    soft: "bg-rose-50/40 dark:bg-rose-950/20",
  },
  Plan: {
    dot: "bg-violet-500",
    ring: "border-violet-200/70 dark:border-violet-900/60",
    soft: "bg-violet-50/40 dark:bg-violet-950/20",
  },
  Draft: {
    dot: "bg-blue-500",
    ring: "border-blue-200/70 dark:border-blue-900/60",
    soft: "bg-blue-50/40 dark:bg-blue-950/20",
  },
  Edit: {
    dot: "bg-amber-500",
    ring: "border-amber-200/70 dark:border-amber-900/60",
    soft: "bg-amber-50/40 dark:bg-amber-950/20",
  },
  Approve: {
    dot: "bg-orange-500",
    ring: "border-orange-200/70 dark:border-orange-900/60",
    soft: "bg-orange-50/40 dark:bg-orange-950/20",
  },
  Publish: {
    dot: "bg-emerald-500",
    ring: "border-emerald-200/70 dark:border-emerald-900/60",
    soft: "bg-emerald-50/40 dark:bg-emerald-950/20",
  },
};

const FALLBACK_TINT = STAGE_TINT.Backlog;
function stageTint(stage: string) {
  return STAGE_TINT[stage] || FALLBACK_TINT;
}

const STATUS_TINT: Record<ItemStatus, string> = {
 "not-started": "bg-zinc-100 text-zinc-700 border-border  dark:text-zinc-300 ",
 "in-progress": "bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-900",
 "client-input": "bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900",
  review: "bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-950/40 dark:text-orange-300 dark:border-orange-900",
  done: "bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900",
  stuck: "bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-900",
};

// Subitem progress: done / total. Returns 0..1
export function subitemProgress(item: BoardItem): { done: number; total: number; ratio: number } {
  const total = item.subitems.length;
  const done = item.subitems.filter((s) => s.done).length;
  return { done, total, ratio: total === 0 ? (item.status === "done" ? 1 : 0) : done / total };
}

// Empty-state copy keyed by stage. Compact, agency tone.
// Stage descriptions explain *what changes* when a card sits in this stage,
// shown as a tooltip on the column header so the workflow stays self-documenting.
function stageDescription(stage: string): string {
  const s = stage.toLowerCase();
  if (s.includes("backlog") || s.includes("plan"))
    return "Items not yet started. Sort by priority and pull the next item into Working when ready.";
  if (s.includes("working") || s.includes("draft") || s.includes("edit"))
    return "Active work in progress by an assignee. Move to In review when a draft is ready for the client.";
  if (s.includes("waiting on client"))
    return "Blocked on the client \u2014 waiting for content, decisions, or access. Nudge them if it has been more than 48h.";
  if (s.includes("approved") || s.includes("done") || s.includes("publish"))
    return "Client-approved and ready to publish or hand off. Items here count as completed.";
  if (s.includes("in review") || s.includes("approve"))
    return "Sent to the client for approval. They review the preview and either approve or request changes.";
  if (s.includes("stuck"))
    return "Flagged as blocked. Surface the blocker on the item and unblock or escalate.";
  return `Items in the ${stage} stage.`;
}

function emptyHint(stage: string, role: "client" | "admin"): string {
  const s = stage.toLowerCase();
  if (s.includes("backlog") || s.includes("plan"))
    return role === "admin" ? "Nothing queued. Add the next item." : "Nothing queued.";
  if (s.includes("working") || s.includes("draft") || s.includes("edit"))
    return role === "admin" ? "No active work in this stage." : "Nothing in progress.";
  if (s.includes("waiting on client"))
    return "No items waiting on the client right now.";
  if (s.includes("in review") || s.includes("approve"))
    return role === "admin" ? "Nothing waiting for client approval." : "Nothing here to review.";
  if (s.includes("approved") || s.includes("done") || s.includes("publish"))
    return "No completed items yet.";
  if (s.includes("stuck"))
    return "Nothing is stuck. Good.";
  return "No items in this stage.";
}

export function WorkflowBoard({
  board,
  role,
}: {
  board: Board;
  role: "client" | "admin";
}) {
  const { items, addItem, setItemStatus } = useDemoStore();
  const { toast } = useToast();
  const [view, setView] = useState<View>("workflow");
  const [openItem, setOpenItem] = useState<BoardItem | null>(null);
  const [query, setQuery] = useState("");
  const [addItemOpen, setAddItemOpen] = useState(false);
  const [newItemTitle, setNewItemTitle] = useState("");
  const [newItemStage, setNewItemStage] = useState(board.workflow[0] || "Backlog");

  const boardItems = useMemo(
    () =>
      items
        .filter((i) => i.boardId === board.id)
        .filter((i) => !query.trim() || i.title.toLowerCase().includes(query.toLowerCase())),
    [items, board.id, query],
  );

  const liveOpenItem = openItem ? items.find((i) => i.id === openItem.id) || null : null;

  const handleAddItem = () => {
    const title = newItemTitle.trim();
    if (!title) return;
    const id = addItem(board.id, title, { workflowStage: newItemStage });
    toast({ title: "Item added", description: `"${title}" → ${newItemStage}` });
    setNewItemTitle("");
    setAddItemOpen(false);
    // Open item detail for the new item
    setTimeout(() => {
      const fresh = items.find((i) => i.id === id);
      if (fresh) setOpenItem(fresh);
    }, 50);
  };

  const moveStage = (itemId: string, stage: string) => {
    setItemStatus(itemId, stage, role === "admin" ? "Sasha Patel" : "Maya Lin", role);
    toast({ title: "Stage updated", description: `Moved to ${stage}` });
  };

  return (
    <div className="space-y-3">
      {/* Header — single primary action: "New item" (admin only). Calmer outline menu. */}
      <div className="rounded-xl border border-border bg-card px-5 py-4 ">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div className="min-w-0">
            <h2 className="text-lg font-semibold tracking-tight" data-testid="board-name">
              {board.name}
            </h2>
            <p className="mt-0.5 text-xs text-muted-foreground">{board.description}</p>
          </div>
          <div className="flex items-center gap-1.5">
            {role === "admin" && (
              <Button
                size="sm"
                className="h-9 gap-1.5"
                onClick={() => setAddItemOpen(true)}
                data-testid="button-new-item"
              >
                <Plus className="size-4" /> New item
              </Button>
            )}
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button
                  variant="outline"
                  size="sm"
                  className="h-9 w-9 p-0"
                  data-testid="button-board-menu"
                  aria-label="Board menu"
                >
                  <MoreHorizontal className="size-4" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuItem
                  onClick={() =>
                    toast({
                      title: "Setup required",
                      description: "Board sharing is configured per workspace.",
                    })
                  }
                >
                  Share board…
                </DropdownMenuItem>
                <DropdownMenuItem
                  onClick={() =>
                    toast({
                      title: "Saved as template",
                      description: `${board.name} is now reusable.`,
                    })
                  }
                >
                  Save as template
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem onClick={() => setView("activity")}>
                  Open activity log
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
      </div>

      {/* How it works — agency language, no PM jargon. Replaces the old explainer. */}
      <div className="flex flex-wrap items-center gap-x-4 gap-y-1.5 rounded-lg border border-border bg-surface-elevated px-4 py-2.5 text-[11px] text-muted-foreground ">
        <span className="inline-flex items-center gap-1 font-medium text-foreground">
          <Info className="size-3.5" /> How this workflow works
        </span>
        <span>
          Move items through stages as Oversee works on them.
        </span>
        {role === "admin" ? (
          <span>
            Drag a card or use its <span className="font-medium text-foreground">menu</span> to change stage.
          </span>
        ) : (
          <span>
            Open any item to reply, send files, or approve.
          </span>
        )}
      </div>

      {/* View tabs (Workflow is default) + search */}
      <div className="flex flex-wrap items-center gap-2">
        <Tabs value={view} onValueChange={(v) => setView(v as View)}>
          <TabsList className="h-9 p-1">
            <TabsTrigger value="workflow" className="gap-1.5" data-testid="view-workflow">
              <LayoutGrid className="size-3.5" /> Workflow
            </TabsTrigger>
            <TabsTrigger value="files" className="gap-1.5" data-testid="view-files">
              <Folder className="size-3.5" /> Files
            </TabsTrigger>
            <TabsTrigger value="activity" className="gap-1.5" data-testid="view-activity">
              <ActivityIcon className="size-3.5" /> Activity
            </TabsTrigger>
          </TabsList>
        </Tabs>
        <div className="relative ml-auto">
          <Search className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
          <Input
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Search items"
            className="h-9 w-56 pl-8 text-xs"
            data-testid="input-search-items"
          />
        </div>
      </div>

      {view === "workflow" && (
        <WorkflowView
          board={board}
          items={boardItems}
          role={role}
          onOpen={setOpenItem}
          onMove={moveStage}
          onAddItemToStage={(stage) => {
            setNewItemStage(stage);
            setAddItemOpen(true);
          }}
        />
      )}
      {view === "files" && <BoardFilesView board={board} items={boardItems} role={role} />}
      {view === "activity" && <ActivityView items={boardItems} />}

      <ItemDetailSheet item={liveOpenItem} onClose={() => setOpenItem(null)} role={role} />

      {/* New item dialog */}
      <Dialog open={addItemOpen} onOpenChange={setAddItemOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>New item</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <Input
              autoFocus
              value={newItemTitle}
              onChange={(e) => setNewItemTitle(e.target.value)}
              placeholder="Item title"
              data-testid="input-new-item-title"
              onKeyDown={(e) => {
                if (e.key === "Enter") handleAddItem();
              }}
            />
            <Select value={newItemStage} onValueChange={setNewItemStage}>
              <SelectTrigger data-testid="select-new-item-stage">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {board.workflow.map((s) => (
                  <SelectItem key={s} value={s}>
                    {s}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setAddItemOpen(false)}>
              Cancel
            </Button>
            <Button onClick={handleAddItem} data-testid="button-confirm-add-item">
              Add item
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

// ============== Workflow view ==============

function WorkflowView({
  board,
  items,
  role,
  onOpen,
  onMove,
  onAddItemToStage,
}: {
  board: Board;
  items: BoardItem[];
  role: "client" | "admin";
  onOpen: (i: BoardItem) => void;
  onMove: (id: string, stage: string) => void;
  onAddItemToStage: (stage: string) => void;
}) {
  const [draggingId, setDraggingId] = useState<string | null>(null);
  const [overStage, setOverStage] = useState<string | null>(null);

  return (
    <div
      className="grid auto-cols-[300px] grid-flow-col gap-3 overflow-x-auto pb-2 scrollbar-soft"
      data-testid="workflow-columns"
    >
      {board.workflow.map((stage) => {
        const stageItems = items.filter((i) => i.workflowStage === stage);
        const isOver = overStage === stage;
        const tint = stageTint(stage);
        return (
          <section
            key={stage}
            onDragOver={(e) => {
              if (role !== "admin") return;
              e.preventDefault();
              setOverStage(stage);
            }}
            onDragLeave={() => setOverStage((s) => (s === stage ? null : s))}
            onDrop={(e) => {
              e.preventDefault();
              if (role === "admin" && draggingId) onMove(draggingId, stage);
              setDraggingId(null);
              setOverStage(null);
            }}
            className={`flex min-h-[180px] flex-col rounded-xl border bg-card transition ${tint.ring} ${
              isOver ? "border-primary/60 ring-2 ring-primary/20" : ""
            }`}
            data-testid={`workflow-col-${stage}`}
            aria-label={`${stage} column`}
          >
            {/* Column header */}
            <header
              className={`flex items-center gap-2 rounded-t-xl px-3 py-2 ${tint.soft}`}
              title={stageDescription(stage)}
            >
              <span className={`size-2 rounded-full ${tint.dot}`} aria-hidden />
              <span className="text-xs font-semibold tracking-tight">{stage}</span>
              <span className="rounded-full bg-card px-1.5 py-0.5 text-[10px] font-medium tabular-nums text-muted-foreground">
                {stageItems.length}
              </span>
              {role === "admin" && (
                <Button
                  variant="ghost"
                  size="sm"
                  className="ml-auto h-6 w-6 p-0 text-muted-foreground hover:text-foreground"
                  onClick={() => onAddItemToStage(stage)}
                  aria-label={`Add item to ${stage}`}
                  data-testid={`workflow-col-add-${stage}`}
                >
                  <Plus className="size-3.5" />
                </Button>
              )}
            </header>

            {/* Column body */}
            <div className="flex flex-1 flex-col gap-2 p-2">
              {stageItems.map((it) => (
                <WorkflowCard
                  key={it.id}
                  item={it}
                  workflow={board.workflow}
                  role={role}
                  onOpen={() => onOpen(it)}
                  onMove={onMove}
                  onDragStart={() => setDraggingId(it.id)}
                  onDragEnd={() => setDraggingId(null)}
                />
              ))}
              {stageItems.length === 0 && (
                <div
                  className="flex flex-1 flex-col items-center justify-center rounded-md border border-dashed border-border px-3 py-5 text-center text-[11px] text-muted-foreground "
                  data-testid={`workflow-empty-${stage}`}
                >
                  <p>{emptyHint(stage, role)}</p>
                  {role === "admin" && (
                    <button
                      type="button"
                      onClick={() => onAddItemToStage(stage)}
                      className="mt-2 inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-medium text-primary hover:bg-primary/10"
                      data-testid={`workflow-empty-add-${stage}`}
                    >
                      <Plus className="size-3" /> Add item
                    </button>
                  )}
                </div>
              )}
            </div>
          </section>
        );
      })}
    </div>
  );
}

function WorkflowCard({
  item,
  workflow,
  role,
  onOpen,
  onMove,
  onDragStart,
  onDragEnd,
}: {
  item: BoardItem;
  workflow: string[];
  role: "client" | "admin";
  onOpen: () => void;
  onMove: (id: string, stage: string) => void;
  onDragStart: () => void;
  onDragEnd: () => void;
}) {
  const sp = subitemProgress(item);
  const cover = item.deliverables.find((d) => d.kind === "image" && d.src);
  const fileCount = item.deliverables.length;
  const updateCount = item.discussion.length;
  const due = item.due ? new Date(item.due) : null;
  const dueLabel = due ? due.toLocaleDateString(undefined, { month: "short", day: "numeric" }) : null;
  const overdue = due && item.status !== "done" ? due.getTime() < Date.now() : false;
  // Approval derivation — only show "Approve" affordance to client when the item
  // truly needs a client decision (not when it's awaiting internal staff review).
  const approval = getItemApproval(item);
  const needsClientApproval = approval.status === "needs_client_approval";
  const needsClientReply = item.status === "client-input";
  const needsClient = needsClientApproval || needsClientReply;

  return (
    <article
      draggable={role === "admin"}
      onDragStart={(e) => {
        if (role !== "admin") return;
        // Show a transparent ghost so the cursor stays accurate
        try {
          e.dataTransfer.effectAllowed = "move";
          e.dataTransfer.setData("text/plain", item.id);
        } catch {
          /* noop */
        }
        onDragStart();
      }}
      onDragEnd={onDragEnd}
      onClick={onOpen}
      onKeyDown={(e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          onOpen();
        }
      }}
      tabIndex={0}
      role="button"
      aria-label={`Open ${item.title}`}
      className="group relative flex cursor-pointer flex-col overflow-hidden rounded-lg border border-border bg-card text-left text-foreground transition hover:border-strong hover:shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-primary "
      data-testid={`workflow-card-${item.id}`}
    >
      {/* Drag affordance — visible to admins on hover; aria-hidden because the card is keyboard-actionable. */}
      {role === "admin" && (
        <span
          className="pointer-events-none absolute right-2 top-2 inline-flex items-center rounded bg-card/90 px-1 py-0.5 text-[9px] font-medium uppercase tracking-wider text-muted-foreground opacity-0 transition group-hover:opacity-100"
          aria-hidden
        >
          <GripVertical className="size-3" /> drag
        </span>
      )}

      {cover && (
        <div className="aspect-[16/9] overflow-hidden border-b border-border bg-muted ">
          <img src={cover.src} alt="" className="size-full object-cover" />
        </div>
      )}

      <div className="flex flex-col gap-2 p-3">
        {/* Title + (client) needs-action chip */}
        <div className="flex items-start gap-2">
          <p className="flex-1 text-sm font-semibold leading-snug">{item.title}</p>
          {needsClient && role === "client" && (
            <Badge
              variant="outline"
              className="shrink-0 border-orange-200 bg-orange-50 text-[10px] font-medium text-orange-700 dark:border-orange-900 dark:bg-orange-950/40 dark:text-orange-300"
              data-testid={`badge-needs-${needsClientApproval ? "approve" : "reply"}-${item.id}`}
            >
              {needsClientApproval ? (
                <span className="inline-flex items-center gap-1">
                  <ThumbsUp className="size-3" /> Approve
                </span>
              ) : (
                <span className="inline-flex items-center gap-1">
                  <MessageSquare className="size-3" /> Reply
                </span>
              )}
            </Badge>
          )}
          {/* Admin sees a small marker showing whether review is by client or internal staff. */}
          {role === "admin" && approval.status === "needs_client_approval" && (
            <Badge
              variant="outline"
              className="shrink-0 border-orange-200 bg-orange-50 text-[10px] font-medium text-orange-700 dark:border-orange-900 dark:bg-orange-950/40 dark:text-orange-300"
              data-testid={`badge-waiting-client-${item.id}`}
            >
              Waiting on client
            </Badge>
          )}
          {role === "admin" && approval.status === "needs_staff_review" && (
            <Badge
              variant="outline"
              className="shrink-0 border-amber-200 bg-amber-50 text-[10px] font-medium text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300"
              data-testid={`badge-staff-review-${item.id}`}
            >
              Staff review
            </Badge>
          )}
          {role === "admin" && approval.status === "changes_requested" && (
            <Badge
              variant="outline"
              className="shrink-0 border-rose-200 bg-rose-50 text-[10px] font-medium text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300"
              data-testid={`badge-changes-requested-${item.id}`}
            >
              Changes requested
            </Badge>
          )}
        </div>

        {/* Meta line: owner · due */}
        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted-foreground">
          {item.assignee && <span className="truncate">{item.assignee}</span>}
          {dueLabel && (
            <span
              className={`inline-flex items-center gap-1 ${
                overdue ? "font-semibold text-rose-700 dark:text-rose-300" : ""
              }`}
            >
              <CalendarClock className="size-3" />
              {dueLabel}
            </span>
          )}
        </div>

        {/* Counters: subitems / files / updates */}
        {(sp.total > 0 || fileCount > 0 || updateCount > 0) && (
          <div className="flex flex-wrap items-center gap-3 text-[10px] text-muted-foreground">
            {sp.total > 0 && (
              <Tooltip>
                <TooltipTrigger asChild>
                  <span className="inline-flex items-center gap-1 tabular-nums">
                    <span
                      className="inline-block h-1 w-10 overflow-hidden rounded-full bg-muted "
                      aria-hidden
                    >
                      <BarFill
                        pct={sp.ratio * 100}
                        className="block h-full rounded-full bg-primary"
                      />
                    </span>
                    {sp.done}/{sp.total}
                  </span>
                </TooltipTrigger>
                <TooltipContent side="top" className="text-[11px]">
                  Sub-items completed
                </TooltipContent>
              </Tooltip>
            )}
            {fileCount > 0 && (
              <Tooltip>
                <TooltipTrigger asChild>
                  <span className="inline-flex items-center gap-1">
                    <Paperclip className="size-3" />
                    <span className="tabular-nums">{fileCount}</span>
                  </span>
                </TooltipTrigger>
                <TooltipContent side="top" className="text-[11px]">
                  {fileCount} file{fileCount === 1 ? "" : "s"}
                </TooltipContent>
              </Tooltip>
            )}
            {updateCount > 0 && (
              <Tooltip>
                <TooltipTrigger asChild>
                  <span className="inline-flex items-center gap-1">
                    <ImageIcon className="size-3" />
                    <span className="tabular-nums">{updateCount}</span>
                  </span>
                </TooltipTrigger>
                <TooltipContent side="top" className="text-[11px]">
                  {updateCount} update{updateCount === 1 ? "" : "s"}
                </TooltipContent>
              </Tooltip>
            )}
          </div>
        )}

        {/* Footer: status badge + admin stage menu */}
        <div className="mt-1 flex items-center justify-between gap-2">
          <span
            className={`inline-flex w-fit items-center rounded border px-2 py-0.5 text-[10px] font-medium ${STATUS_TINT[item.status]}`}
          >
            {item.workflowStage}
          </span>
          {role === "admin" && (
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button
                  variant="ghost"
                  size="sm"
                  className="h-6 gap-0.5 px-1.5 text-[10px] text-muted-foreground hover:text-foreground"
                  onClick={(e) => e.stopPropagation()}
                  data-testid={`workflow-card-menu-${item.id}`}
                  aria-label={`Change stage for ${item.title}`}
                >
                  Stage <ChevronDown className="size-3" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" onClick={(e) => e.stopPropagation()}>
                <DropdownMenuLabel className="text-[10px] uppercase tracking-wider text-muted-foreground">
                  Move to stage
                </DropdownMenuLabel>
                {workflow.map((s) => (
                  <DropdownMenuItem
                    key={s}
                    onClick={(e) => {
                      e.stopPropagation();
                      if (s === item.workflowStage) return;
                      onMove(item.id, s);
                    }}
                    className={s === item.workflowStage ? "font-medium text-primary" : ""}
                    data-testid={`workflow-card-stage-${item.id}-${s}`}
                  >
                    <span className={`mr-2 size-2 rounded-full ${stageTint(s).dot}`} aria-hidden />
                    {s}
                  </DropdownMenuItem>
                ))}
              </DropdownMenuContent>
            </DropdownMenu>
          )}
        </div>
      </div>
    </article>
  );
}

// ============== Activity view ==============

function ActivityView({ items }: { items: BoardItem[] }) {
  const all = items
    .flatMap((i) => i.activity.map((a) => ({ ...a, itemTitle: i.title })))
    .sort((a, b) => +new Date(b.at) - +new Date(a.at));
  if (all.length === 0) {
    return (
      <div
        className="rounded-xl border border-border bg-card py-12 text-center text-sm text-muted-foreground "
        data-testid="activity-empty"
      >
        No activity yet.
      </div>
    );
  }
  return (
    <div className="rounded-xl border border-border bg-card p-5 ">
      <ol className="relative space-y-4 border-l-2 border-border pl-5 ">
        {all.map((a) => (
          <li key={a.id} className="relative">
            <span className="absolute -left-[27px] top-1.5 size-2.5 rounded-full border-2 border-card bg-primary" />
            <p className="text-sm">
              <span className="font-medium">{a.authorName}</span>{" "}
              <span className="text-muted-foreground">{a.text}</span>{" "}
              <span className="text-muted-foreground">on</span>{" "}
              <span className="font-medium">{a.itemTitle}</span>
            </p>
            <p className="text-xs text-muted-foreground">
              {new Date(a.at).toLocaleDateString(undefined, { month: "short", day: "numeric" })}
              {" · "}
              {new Date(a.at).toLocaleTimeString(undefined, { hour: "numeric", minute: "2-digit" })}
            </p>
          </li>
        ))}
      </ol>
    </div>
  );
}
