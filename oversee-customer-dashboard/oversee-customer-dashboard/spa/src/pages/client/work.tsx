// Client "My Work" — single page combining Action list (default) and Workflow toggle.
// Sections: Needs your attention, In progress with Oversee, Waiting for Oversee, Completed.
import { useMemo, useState } from "react";
import {
  ChevronRight,
  Clock,
  KanbanSquare,
  ListChecks,
  MessageSquare,
  ThumbsUp,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useDemoStore, type Board, type BoardItem } from "@/lib/demo-store";
import { ItemDetailSheet } from "@/components/item-detail-sheet";
import { WorkflowBoard, subitemProgress } from "@/components/workflow-board";
import { PageHeader, SectionCard } from "@/components/shared";
import { ApprovalMetaRow } from "@/components/approval-clarity";
import { getItemApproval } from "@/lib/approval-clarity";

type View = "action" | "board";

const SECTION_DEFS: {
  id: string;
  label: string;
  match: (i: BoardItem) => boolean;
  hint: string;
  cta: (i: BoardItem) => string;
  tone: "primary" | "info" | "warning" | "success" | "neutral";
}[] = [
  {
    id: "attention",
    label: "Needs client approval",
    match: (i) => {
      if (i.status === "client-input") return true;
      if (i.status === "review") {
        // Only show items where the client is the approver. Staff-only review
        // items belong on the admin side.
        return getItemApproval(i).status === "needs_client_approval";
      }
      return false;
    },
    hint: "Your team is blocked on you. Tap to act.",
    cta: (i) => (i.status === "review" ? "Review & approve" : "Open task"),
    tone: "primary",
  },
  {
    id: "in-progress",
    label: "Oversee working on it",
    match: (i) => i.status === "in-progress",
    hint: "Your team is actively delivering. No action required from you.",
    cta: () => "View progress",
    tone: "info",
  },
  {
    id: "waiting",
    label: "Waiting on Oversee",
    match: (i) =>
      i.status === "not-started" ||
      i.status === "stuck" ||
      (i.status === "review" && getItemApproval(i).status === "needs_staff_review"),
    hint: "Queued or in internal review with your team.",
    cta: () => "View details",
    tone: "warning",
  },
  {
    id: "done",
    label: "Completed",
    match: (i) => i.status === "done",
    hint: "Approved and shipped.",
    cta: () => "View details",
    tone: "success",
  },
];

export default function ClientWork() {
  const { items, boards } = useDemoStore();
  const [view, setView] = useState<View>("action");
  const [openItem, setOpenItem] = useState<BoardItem | null>(null);
  const [activeBoard, setActiveBoard] = useState<Board | null>(null);

  const sections = useMemo(
    () =>
      SECTION_DEFS.map((s) => ({
        ...s,
        items: items.filter(s.match),
      })),
    [items],
  );

  const liveOpenItem = openItem ? items.find((i) => i.id === openItem.id) || null : null;

  return (
    <div className="space-y-5">
      <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <PageHeader
          eyebrow="Workspace"
          title="My Work"
          subtitle="Everything Oversee is working on for you. Action list shows what matters first."
          testId="heading-my-work"
        />
        <Tabs value={view} onValueChange={(v) => setView(v as View)}>
          <TabsList className="h-9 p-1" data-testid="my-work-view-toggle">
            <TabsTrigger value="action" className="gap-1.5 text-xs" data-testid="tab-action-list">
              <ListChecks className="size-3.5" /> Action list
            </TabsTrigger>
            <TabsTrigger value="board" className="gap-1.5 text-xs" data-testid="tab-board-view">
              <KanbanSquare className="size-3.5" /> Workflow
            </TabsTrigger>
          </TabsList>
        </Tabs>
      </div>

      {view === "action" ? (
        <div className="space-y-4" data-testid="action-list-view">
          {sections.map((section) => (
            <SectionCard
              key={section.id}
              title={section.label}
              hint={section.hint}
              tone={section.tone}
              trailing={
                <span className="text-[11px] tabular-nums text-muted-foreground">
                  {section.items.length}
                </span>
              }
              testId={`section-${section.id}`}
            >
              {section.items.length === 0 ? (
                <EmptyRow
                  text={
                    section.id === "attention"
                      ? "Nothing waiting on you."
                      : "Nothing here right now."
                  }
                />
              ) : (
                <ul className="divide-y divide-border/60">
                  {section.items.map((item) => (
                    <WorkRow
                      key={item.id}
                      item={item}
                      buttonLabel={section.cta(item)}
                      board={boards.find((b) => b.id === item.boardId)}
                      onOpen={() => setOpenItem(item)}
                    />
                  ))}
                </ul>
              )}
            </SectionCard>
          ))}
        </div>
      ) : (
        <BoardView
          boards={boards}
          activeBoard={activeBoard}
          onSelectBoard={setActiveBoard}
          onBack={() => setActiveBoard(null)}
        />
      )}

      <ItemDetailSheet item={liveOpenItem} onClose={() => setOpenItem(null)} role="client" />
    </div>
  );
}

function WorkRow({
  item,
  board,
  buttonLabel,
  onOpen,
}: {
  item: BoardItem;
  board?: Board;
  buttonLabel: string;
  onOpen: () => void;
}) {
  const sp = subitemProgress(item);
  const meta = getItemApproval(item);
  const Icon =
    item.status === "review"
      ? ThumbsUp
      : item.status === "client-input"
        ? MessageSquare
        : KanbanSquare;

  return (
    <li
      className="flex flex-col gap-3 px-5 py-4 md:flex-row md:items-center md:justify-between"
      data-testid={`work-row-${item.id}`}
    >
      <button
        type="button"
        onClick={onOpen}
        className="flex min-w-0 flex-1 items-start gap-3 text-left"
        data-testid={`work-row-open-${item.id}`}
      >
        <span className="mt-0.5 grid size-8 shrink-0 place-items-center rounded-md border border-border bg-muted text-muted-foreground">
          <Icon className="size-4" />
        </span>
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-semibold leading-snug">{item.title}</p>
          {board && (
            <p className="mt-0.5 truncate text-[11px] uppercase tracking-[0.14em] text-muted-foreground">
              {board.name}
            </p>
          )}
          <div className="mt-1.5">
            <ApprovalMetaRow
              meta={meta}
              viewer="client"
              compact
              testId={`work-row-approval-${item.id}`}
            />
          </div>
          {sp.total > 0 && (
            <p className="mt-1 text-[11px] tabular-nums text-muted-foreground">
              {sp.done}/{sp.total} sub-items
            </p>
          )}
        </div>
      </button>
      <div className="md:shrink-0">
        <Button
          size="sm"
          className="h-9 w-full gap-1 md:w-auto"
          onClick={onOpen}
          data-testid={`button-work-action-${item.id}`}
        >
          {buttonLabel} <ChevronRight className="size-4" />
        </Button>
      </div>
    </li>
  );
}

function EmptyRow({ text }: { text: string }) {
  return (
    <p className="px-5 py-6 text-center text-xs text-muted-foreground" data-testid="empty-row">
      {text}
    </p>
  );
}

function BoardView({
  boards,
  activeBoard,
  onSelectBoard,
  onBack,
}: {
  boards: Board[];
  activeBoard: Board | null;
  onSelectBoard: (b: Board) => void;
  onBack: () => void;
}) {
  if (activeBoard) {
    return (
      <div className="space-y-3" data-testid="board-active">
        <Button
          variant="ghost"
          size="sm"
          onClick={onBack}
          className="gap-1 text-xs"
          data-testid="button-back-to-boards"
        >
          ← All boards
        </Button>
        <WorkflowBoard board={activeBoard} role="client" />
      </div>
    );
  }
  return (
    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3" data-testid="boards-grid">
      {boards.map((b) => (
        <SectionCard
          key={b.id}
          title={b.name}
          hint={b.description}
          testId={`board-card-${b.id}`}
        >
          <button
            type="button"
            onClick={() => onSelectBoard(b)}
            className="block w-full px-5 py-4 text-left text-xs text-primary hover:underline"
            data-testid={`board-open-${b.id}`}
          >
            Open workflow →
          </button>
        </SectionCard>
      ))}
    </div>
  );
}
