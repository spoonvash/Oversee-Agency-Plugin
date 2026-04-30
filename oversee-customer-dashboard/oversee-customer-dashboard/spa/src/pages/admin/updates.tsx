// Admin Updates — LEGACY ALIAS PAGE.
// The canonical surface for project conversations is now the top-right
// notification bell + Today / Boards. This page remains so old deep links keep
// working, but should not be linked from the IA.
// Left rail: filters + priority tabs. Right pane: compact rows + detail panel.
import { useMemo, useState } from "react";
import {
  AtSign,
  Bookmark,
  BookmarkCheck,
  Inbox,
  MessageSquare,
  Reply,
  AlertCircle,
  CheckCircle2,
  Clock,
  Filter,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { useDemoStore, type BoardItem } from "@/lib/demo-store";
import { ItemDetailSheet } from "@/components/item-detail-sheet";
import { shortDateTime } from "@/lib/format";
import { PageHeader, SoftCard } from "@/components/shared";

function timeAgo(iso: string) {
  const ms = Date.now() - new Date(iso).getTime();
  const m = Math.floor(ms / 60000);
  if (m < 1) return "just now";
  if (m < 60) return `${m}m ago`;
  const h = Math.floor(m / 60);
  if (h < 24) return `${h}h ago`;
  const d = Math.floor(h / 24);
  if (d < 7) return `${d}d ago`;
  return new Date(iso).toLocaleDateString();
}

type FilterKey = "all" | "needs-reply" | "mentions" | "bookmarked";

export default function AdminUpdates() {
  const { feed, items, boards, bookmarkUpdate, markUpdateRead, showInternalNotes } = useDemoStore();
  const [openItem, setOpenItem] = useState<BoardItem | null>(null);
  const [filter, setFilter] = useState<FilterKey>("all");
  const [boardFilter, setBoardFilter] = useState<string>("all");
  const [query, setQuery] = useState("");
  const [activeId, setActiveId] = useState<string | null>(null);

  const me = "Sasha Patel";

  const baseFeed = useMemo(
    () => feed.filter((u) => !u.internalOnly || showInternalNotes),
    [feed, showInternalNotes],
  );

  const counts = useMemo(() => {
    const needsReply = baseFeed.filter((u) => {
      if (!u.itemId) return false;
      const item = items.find((i) => i.id === u.itemId);
      return item && item.status === "review";
    }).length;
    const mentions = baseFeed.filter((u) => u.mentioned || u.body.toLowerCase().includes(me.toLowerCase())).length;
    const bookmarked = baseFeed.filter((u) => u.bookmarked).length;
    return { all: baseFeed.length, needsReply, mentions, bookmarked };
  }, [baseFeed, items]);

  const filtered = useMemo(() => {
    let list = baseFeed;
    if (filter === "needs-reply") {
      list = list.filter((u) => {
        if (!u.itemId) return false;
        const item = items.find((i) => i.id === u.itemId);
        return item && item.status === "review";
      });
    }
    if (filter === "mentions")
      list = list.filter((u) => u.mentioned || u.body.toLowerCase().includes(me.toLowerCase()));
    if (filter === "bookmarked") list = list.filter((u) => u.bookmarked);
    if (boardFilter !== "all") list = list.filter((u) => u.boardId === boardFilter);
    if (query.trim()) {
      const q = query.toLowerCase();
      list = list.filter((u) => u.body.toLowerCase().includes(q) || u.authorName.toLowerCase().includes(q));
    }
    return list;
  }, [baseFeed, filter, boardFilter, query, items]);

  const active = filtered.find((u) => u.id === activeId) || filtered[0] || null;
  const activeItem = active?.itemId ? items.find((i) => i.id === active.itemId) : null;
  const activeBoard = active ? boards.find((b) => b.id === active.boardId) : null;
  const liveActiveItem = activeItem ? items.find((i) => i.id === activeItem.id) || null : null;

  return (
    <div className="space-y-4">
      <PageHeader
        eyebrow="Legacy view"
        title="Updates"
        subtitle="Triage cross-board conversations. The unread marker only appears when an item truly needs your reply. (Notifications now live in the top-right bell.)"
        testId="heading-admin-updates"
      />

      <div className="grid gap-4 lg:grid-cols-[240px_minmax(0,1fr)_360px]">
        {/* LEFT: filters */}
        <SoftCard padded={false} testId="updates-filters" className="self-start p-3">
          <p className="px-2 pb-2 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
            Priority
          </p>
          <div className="space-y-0.5">
            <FilterButton label="All" count={counts.all} active={filter === "all"} onClick={() => setFilter("all")} icon={Inbox} testId="updates-filter-all" />
            <FilterButton label="Needs reply" count={counts.needsReply} active={filter === "needs-reply"} onClick={() => setFilter("needs-reply")} icon={Reply} testId="updates-filter-needs-reply" tone="primary" />
            <FilterButton label="Mentions" count={counts.mentions} active={filter === "mentions"} onClick={() => setFilter("mentions")} icon={AtSign} testId="updates-filter-mentions" />
            <FilterButton label="Bookmarked" count={counts.bookmarked} active={filter === "bookmarked"} onClick={() => setFilter("bookmarked")} icon={BookmarkCheck} testId="updates-filter-bookmarked" />
          </div>
          <p className="mt-4 px-2 pb-2 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
            Project
          </p>
          <div className="space-y-0.5 max-h-[260px] overflow-y-auto pr-1">
            <FilterButton label="All projects" count={baseFeed.length} active={boardFilter === "all"} onClick={() => setBoardFilter("all")} icon={Filter} testId="updates-board-filter-all" />
            {boards.map((b) => {
              const c = baseFeed.filter((u) => u.boardId === b.id).length;
              if (c === 0) return null;
              return (
                <FilterButton
                  key={b.id}
                  label={b.name}
                  count={c}
                  active={boardFilter === b.id}
                  onClick={() => setBoardFilter(b.id)}
                  testId={`updates-board-filter-${b.id}`}
                />
              );
            })}
          </div>
        </SoftCard>

        {/* MIDDLE: list */}
        <div className="flex flex-col gap-3">
          <div className="flex items-center gap-2">
            <Input
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Search updates"
              className="h-9 text-xs"
              data-testid="input-updates-search"
            />
          </div>

          {filtered.length === 0 ? (
            <SoftCard className="text-center" padded testId="updates-empty">
              <MessageSquare className="mx-auto mb-2 size-5 text-muted-foreground" />
              <p className="text-sm font-medium">Nothing here</p>
              <p className="mt-1 text-xs text-muted-foreground">Try a different filter.</p>
            </SoftCard>
          ) : (
            <SoftCard padded={false} className="overflow-hidden">
              <ul className="divide-y divide-border/70 max-h-[68vh] overflow-y-auto scrollbar-soft">
                {filtered.map((u) => {
                  const item = u.itemId ? items.find((i) => i.id === u.itemId) : null;
                  const board = boards.find((b) => b.id === u.boardId);
                  const needsReply = item && item.status === "review";
                  const isActive = active?.id === u.id;
                  return (
                    <li key={u.id}>
                      <button
                        type="button"
                        onClick={() => {
                          setActiveId(u.id);
                          if (u.unread) markUpdateRead(u.id);
                        }}
                        className={`flex w-full items-start gap-3 px-4 py-3 text-left transition hover:bg-muted/40 ${isActive ? "bg-muted/50" : ""} ${needsReply ? "border-l-2 border-l-primary pl-[14px]" : ""}`}
                        data-testid={`feed-update-${u.id}`}
                      >
                        <div className={`grid size-8 shrink-0 place-items-center rounded-full text-[11px] font-semibold ${u.authorRole === "admin" ? "bg-muted text-foreground " : "bg-primary/15 text-primary"}`}>
                          {u.authorName.split(" ").map((s) => s[0]).join("").toUpperCase()}
                        </div>
                        <div className="min-w-0 flex-1">
                          <div className="flex items-center gap-1.5">
                            <span className={`truncate text-sm ${u.unread ? "font-semibold" : "font-medium"}`}>{u.authorName}</span>
                            {board && <Badge variant="outline" className="h-4 text-[10px]">{board.name}</Badge>}
                            {u.mentioned && <AtSign className="size-3 text-primary" />}
                          </div>
                          <p className="mt-0.5 line-clamp-1 text-xs text-muted-foreground">{u.body}</p>
                          <p className="mt-1 text-[10px] text-muted-foreground">{timeAgo(u.postedAt)}</p>
                        </div>
                        {u.bookmarked && <Bookmark className="size-3.5 text-primary" />}
                      </button>
                    </li>
                  );
                })}
              </ul>
            </SoftCard>
          )}
        </div>

        {/* RIGHT: detail panel */}
        <SoftCard padded={false} testId="updates-detail" className="self-start min-h-[280px]">
          {!active ? (
            <div className="flex h-full flex-col items-center justify-center px-4 py-12 text-center text-xs text-muted-foreground">
              <Inbox className="mb-2 size-5 opacity-50" />
              Select an update to view it here.
            </div>
          ) : (
            <div className="flex flex-col">
              <div className="border-b border-border px-4 py-3 ">
                <div className="flex items-center gap-2">
                  <span className="text-sm font-semibold">{active.authorName}</span>
                  {activeBoard && <Badge variant="outline" className="text-[10px]">{activeBoard.name}</Badge>}
                </div>
                {activeItem && (
                  <button
                    type="button"
                    onClick={() => setOpenItem(activeItem)}
                    className="mt-1 truncate text-xs text-muted-foreground hover:text-foreground hover:underline"
                  >
                    › {activeItem.title}
                  </button>
                )}
                <p className="mt-1 text-[11px] text-muted-foreground">{shortDateTime(active.postedAt)}</p>
              </div>
              <div className="px-4 py-4">
                <p className="text-sm leading-relaxed">{active.body}</p>
                {active.attachment?.kind === "image" && active.attachment.src && (
                  <img src={active.attachment.src} alt="" className="mt-3 h-32 rounded-md border object-cover" />
                )}
              </div>
              <div className="flex items-center justify-between border-t border-border bg-muted/30 px-4 py-2.5 ">
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => bookmarkUpdate(active.id)}
                  className="h-7 gap-1 text-xs"
                  data-testid={`button-bookmark-${active.id}`}
                >
                  {active.bookmarked ? <BookmarkCheck className="size-3.5 text-primary" /> : <Bookmark className="size-3.5" />}
                  {active.bookmarked ? "Bookmarked" : "Bookmark"}
                </Button>
                <Button
                  size="sm"
                  onClick={() => activeItem && setOpenItem(activeItem)}
                  className="h-7 gap-1 text-xs"
                  data-testid={`button-reply-${active.id}`}
                  disabled={!activeItem}
                >
                  <Reply className="size-3.5" /> Reply in task
                </Button>
              </div>
            </div>
          )}
        </SoftCard>
      </div>

      <ItemDetailSheet item={liveActiveItem} onClose={() => setOpenItem(null)} role="admin" />
    </div>
  );
}

function FilterButton({
  label,
  count,
  active,
  onClick,
  icon: Icon,
  testId,
  tone = "neutral",
}: {
  label: string;
  count: number;
  active: boolean;
  onClick: () => void;
  icon?: typeof Inbox;
  testId?: string;
  tone?: "neutral" | "primary";
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      data-testid={testId}
      className={`flex w-full items-center justify-between rounded-md px-2.5 py-1.5 text-left text-xs transition ${
        active
          ? "bg-foreground text-background"
          : tone === "primary"
            ? "text-primary hover:bg-primary/10"
            : "text-foreground hover:bg-muted"
      }`}
    >
      <span className="flex items-center gap-2">
        {Icon && <Icon className="size-3.5 opacity-80" />}
        <span className="truncate font-medium">{label}</span>
      </span>
      <span
        className={`shrink-0 rounded px-1 text-[10px] tabular-nums ${
          active ? "bg-background/20" : "bg-muted text-muted-foreground"
        }`}
      >
        {count}
      </span>
    </button>
  );
}
