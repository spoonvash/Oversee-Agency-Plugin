// Client Updates — simple inbox: Needs reply / Mentions / All.
// Refactored to use PageHeader + SummaryStat strip + SectionCard wrapper for the
// inbox list. Custom row rendering preserved (avatar, attachment thumbnail,
// reply / open-item buttons) since DataListRow doesn't carry an avatar.
import { useMemo, useState } from "react";
import { AtSign, MessageSquare, Reply, ArrowUpRight } from "lucide-react";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Button } from "@/components/ui/button";
import { useDemoStore, type BoardItem } from "@/lib/demo-store";
import { ItemDetailSheet } from "@/components/item-detail-sheet";
import { shortDateTime } from "@/lib/format";
import { PageHeader, SectionCard, SummaryStat } from "@/components/shared";

type Tab = "needs-reply" | "mentions" | "all";

export default function ClientUpdates() {
  const { feed, items, boards, markUpdateRead } = useDemoStore();
  const [tab, setTab] = useState<Tab>("needs-reply");
  const [openItem, setOpenItem] = useState<BoardItem | null>(null);

  const me = "Maya Lin";

  const visibleFeed = useMemo(
    () => feed.filter((u) => !u.internalOnly),
    [feed],
  );

  const clientInputIds = useMemo(
    () => new Set(items.filter((i) => i.status === "client-input" || i.status === "review").map((i) => i.id)),
    [items],
  );

  const filtered = useMemo(() => {
    if (tab === "mentions") {
      return visibleFeed.filter((u) => u.mentioned || u.body.toLowerCase().includes(me.toLowerCase()));
    }
    if (tab === "needs-reply") {
      return visibleFeed.filter(
        (u) =>
          u.unread ||
          u.mentioned ||
          (u.itemId && clientInputIds.has(u.itemId)) ||
          u.authorRole === "admin",
      );
    }
    return visibleFeed;
  }, [visibleFeed, tab, clientInputIds]);

  const counts = {
    "needs-reply": visibleFeed.filter(
      (u) =>
        u.unread ||
        u.mentioned ||
        (u.itemId && clientInputIds.has(u.itemId)) ||
        u.authorRole === "admin",
    ).length,
    mentions: visibleFeed.filter(
      (u) => u.mentioned || u.body.toLowerCase().includes(me.toLowerCase()),
    ).length,
    all: visibleFeed.length,
  };

  const liveOpenItem = openItem ? items.find((i) => i.id === openItem.id) || null : null;

  const openUpdate = (itemId?: string, updateId?: string) => {
    if (updateId) markUpdateRead(updateId);
    if (itemId) {
      const item = items.find((i) => i.id === itemId);
      if (item) setOpenItem(item);
    }
  };

  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow="Legacy view"
        title="Updates"
        subtitle="Messages from your Oversee team. Tap reply to respond."
        testId="updates-header"
      />

      <div className="grid grid-cols-3 gap-3" data-testid="updates-stats">
        <SummaryStat
          label="Needs reply"
          value={counts["needs-reply"]}
          tone={counts["needs-reply"] > 0 ? "primary" : "neutral"}
          testId="stat-needs-reply"
        />
        <SummaryStat
          label="Mentions"
          value={counts.mentions}
          tone={counts.mentions > 0 ? "info" : "neutral"}
          testId="stat-mentions"
        />
        <SummaryStat
          label="All updates"
          value={counts.all}
          tone="neutral"
          testId="stat-all-updates"
        />
      </div>

      <Tabs value={tab} onValueChange={(v) => setTab(v as Tab)}>
        <TabsList className="h-10 p-1">
          <TabsTrigger value="needs-reply" className="gap-1.5 text-xs" data-testid="tab-needs-reply">
            <Reply className="size-3.5" /> Needs reply{" "}
            <span className="ml-1 rounded bg-muted px-1.5 text-[10px] tabular-nums">
              {counts["needs-reply"]}
            </span>
          </TabsTrigger>
          <TabsTrigger value="mentions" className="gap-1.5 text-xs" data-testid="tab-mentions">
            <AtSign className="size-3.5" /> Mentions{" "}
            <span className="ml-1 rounded bg-muted px-1.5 text-[10px] tabular-nums">
              {counts.mentions}
            </span>
          </TabsTrigger>
          <TabsTrigger value="all" className="gap-1.5 text-xs" data-testid="tab-all">
            <MessageSquare className="size-3.5" /> All{" "}
            <span className="ml-1 rounded bg-muted px-1.5 text-[10px] tabular-nums">{counts.all}</span>
          </TabsTrigger>
        </TabsList>
      </Tabs>

      {filtered.length === 0 ? (
        <SectionCard testId="updates-empty">
          <div className="flex flex-col items-center px-4 py-12 text-center">
            <MessageSquare className="size-5 text-muted-foreground" />
            <p className="mt-2 text-sm font-medium">You're all caught up.</p>
            <p className="mt-1 text-xs text-muted-foreground">
              We'll notify you when we need anything.
            </p>
          </div>
        </SectionCard>
      ) : (
        <SectionCard
          title={
            tab === "needs-reply"
              ? "Needs your reply"
              : tab === "mentions"
                ? "You were mentioned"
                : "All updates"
          }
          hint={`${filtered.length} update${filtered.length === 1 ? "" : "s"}`}
          bodyClassName="p-0"
          testId="updates-section"
        >
          <ul className="divide-y divide-border" data-testid="updates-list">
            {filtered.map((u) => {
              const board = boards.find((b) => b.id === u.boardId);
              const item = u.itemId ? items.find((i) => i.id === u.itemId) : null;
              return (
                <li
                  key={u.id}
                  className={`px-5 py-4 transition ${u.unread ? "bg-orange-50/40 dark:bg-orange-950/10" : ""}`}
                  data-testid={`update-${u.id}`}
                >
                  <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div className="flex min-w-0 gap-3">
                      <div
                        className={`grid size-9 shrink-0 place-items-center rounded-full text-xs font-semibold ${
                          u.authorRole === "admin"
                            ? "bg-primary text-primary-foreground"
                            : "bg-muted text-foreground"
                        }`}
                      >
                        {u.authorName
                          .split(" ")
                          .map((s) => s[0])
                          .join("")
                          .toUpperCase()}
                      </div>
                      <div className="min-w-0 flex-1">
                        <p className="text-sm">
                          <span className="font-semibold">{u.authorName}</span>
                          {item && <span className="text-muted-foreground"> · {item.title}</span>}
                        </p>
                        <p className="mt-1 line-clamp-2 text-sm leading-relaxed">{u.body}</p>
                        {u.attachment?.kind === "image" && u.attachment.src && (
                          <img
                            src={u.attachment.src}
                            alt=""
                            className="mt-2 h-24 w-40 rounded-md border object-cover"
                          />
                        )}
                        <p className="mt-2 text-[11px] text-muted-foreground">
                          {board ? `${board.name} · ` : ""}
                          {shortDateTime(u.postedAt)}
                          {u.mentioned ? " · Mentioned you" : ""}
                        </p>
                      </div>
                    </div>
                    <div className="flex items-center gap-2 md:shrink-0">
                      <Button
                        size="sm"
                        className="gap-1.5"
                        onClick={() => openUpdate(u.itemId, u.id)}
                        disabled={!u.itemId}
                        data-testid={`button-reply-${u.id}`}
                      >
                        <Reply className="size-3.5" /> Reply
                      </Button>
                      <Button
                        size="sm"
                        variant="ghost"
                        className="gap-1.5"
                        onClick={() => openUpdate(u.itemId, u.id)}
                        disabled={!u.itemId}
                        data-testid={`button-open-item-${u.id}`}
                      >
                        <ArrowUpRight className="size-3.5" /> Open item
                      </Button>
                    </div>
                  </div>
                </li>
              );
            })}
          </ul>
        </SectionCard>
      )}

      <ItemDetailSheet item={liveOpenItem} onClose={() => setOpenItem(null)} role="client" />
    </div>
  );
}
