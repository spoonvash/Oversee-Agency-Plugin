// Monday-style item detail. Right-side sheet with a single Updates thread,
// header summary, sub-items checklist (rolling up to parent), seen-by, replies,
// reactions, pin, internal-only toggle (admin), approval action, lightbox.
import { useMemo, useState } from "react";
import {
  CheckCircle2,
  CloudUpload,
  Download,
  Eye,
  EyeOff,
  FileText,
  MessageCircle,
  Paperclip,
  Pin,
  Plus,
  RefreshCcw,
  Send,
  Smile,
  ThumbsUp,
  Video,
  X,
} from "lucide-react";
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Badge } from "@/components/ui/badge";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import {
  AttachmentChips,
  AttachmentTrigger,
  type DraftAttachment,
} from "@/components/attachment-composer";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  type BoardItem,
  type DiscussionPost,
  useDemoStore,
} from "@/lib/demo-store";
import { shortDateTime } from "@/lib/format";
import { BarFill } from "@/components/dynamic-styles";
import { UploadFileDialog } from "@/components/upload-dialog";
import { PreviewApprovalDialog } from "@/components/preview-approval-dialog";
import { ApprovalMetaRow, NextActionBanner } from "@/components/approval-clarity";
import { getItemApproval } from "@/lib/approval-clarity";
import type { FileEntry } from "@/lib/demo-store";

type Props = {
  item: BoardItem | null;
  onClose: () => void;
  role: "client" | "admin";
};

const STATUS_LABEL: Record<string, string> = {
 "not-started": "Backlog",
 "in-progress": "Working on it",
 "client-input": "Waiting on client",
  review: "In review",
  done: "Approved",
  stuck: "Stuck",
};

const STATUS_TINT: Record<string, string> = {
 "not-started": "bg-zinc-100 text-zinc-700 border-border",
 "in-progress": "bg-blue-50 text-blue-700 border-blue-200",
 "client-input": "bg-amber-50 text-amber-700 border-amber-200",
  review: "bg-orange-50 text-orange-700 border-orange-200",
  done: "bg-emerald-50 text-emerald-700 border-emerald-200",
  stuck: "bg-rose-50 text-rose-700 border-rose-200",
};

const QUICK_REACTIONS = ["👍", "🎉", "❤️", "👀", "🔥"];

export function ItemDetailSheet({ item, onClose, role }: Props) {
  const {
    addDiscussionPost,
    addReplyToPost,
    togglePostReaction,
    togglePostPin,
    setItemStatus,
    addSubitem,
    toggleSubitem,
    boards,
    showInternalNotes,
  } = useDemoStore();

  const [draft, setDraft] = useState("");
  const [draftAttachments, setDraftAttachments] = useState<DraftAttachment[]>([]);
  const [internalOnly, setInternalOnly] = useState(false);
  const [replyTo, setReplyTo] = useState<string | null>(null);
  const [replyText, setReplyText] = useState("");
  const [lightbox, setLightbox] = useState<string | null>(null);
  const [newSub, setNewSub] = useState("");

  const visiblePosts = useMemo(
    () => (item ? item.discussion.filter((p) => !p.internalOnly || (role === "admin" && showInternalNotes)) : []),
    [item, role, showInternalNotes],
  );

  if (!item) return null;
  const board = boards.find((b) => b.id === item.boardId);

  const me = role === "client" ? "Maya Lin" : "Sasha Patel";

  const approvalMeta = getItemApproval(item);
  // The banner appears whenever the item is in a state where someone needs to
  // act — not only on "approval"-tagged items.
  const isApprovalItem =
    approvalMeta.status === "needs_client_approval" ||
    approvalMeta.status === "needs_staff_review" ||
    approvalMeta.status === "changes_requested";
  const pinnedPosts = visiblePosts.filter((p) => p.pinned);
  const regularPosts = visiblePosts.filter((p) => !p.pinned);

  const subDone = item.subitems.filter((s) => s.done).length;
  const subTotal = item.subitems.length;
  const subPct = subTotal === 0 ? 0 : Math.round((subDone / subTotal) * 100);

  const send = () => {
    if (!draft.trim() && draftAttachments.length === 0) return;
    addDiscussionPost(item.id, {
      authorName: me,
      authorRole: role,
      body: draft,
      internalOnly: role === "admin" && internalOnly,
      seenBy: [me],
      attachments: draftAttachments.map((a) => ({
        id: a.id,
        kind: a.kind === "image" ? "image" : "file",
        src: a.src,
        caption: a.caption,
      })),
    });
    setDraft("");
    setDraftAttachments([]);
    setInternalOnly(false);
  };

  const submitReply = (postId: string) => {
    if (!replyText.trim()) return;
    addReplyToPost(item.id, postId, {
      authorName: me,
      authorRole: role,
      body: replyText,
      seenBy: [me],
    });
    setReplyText("");
    setReplyTo(null);
  };

  const submitApproval = (decision: "approved" | "changes-requested") => {
    // Carry any staged attachments into the approval/request-changes post so
    // a client doesn't lose context they already attached before clicking.
    const carried = draftAttachments.map((a) => ({
      id: a.id,
      kind: a.kind === "image" ? ("image" as const) : ("file" as const),
      src: a.src,
      caption: a.caption,
    }));
    const noteSuffix = draft.trim() ? `\n\n${draft.trim()}` : "";
    addDiscussionPost(item.id, {
      authorName: me,
      authorRole: role,
      body:
        (decision === "approved"
          ? "Approved this deliverable."
          : "Requested changes — see notes above.") + noteSuffix,
      systemKind: "approval",
      seenBy: [me],
      attachments: carried.length > 0 ? carried : undefined,
    });
    setDraft("");
    setDraftAttachments([]);
    if (decision === "approved") {
      setItemStatus(item.id, "Approved", me, role);
    } else {
      setItemStatus(item.id, "Working On It", me, role);
    }
  };

  return (
    <Sheet open={!!item} onOpenChange={(v) => !v && onClose()}>
      <SheetContent className="flex w-full flex-col gap-0 p-0 sm:max-w-2xl" data-testid="item-detail-sheet">
        {/* Header summary */}
        <SheetHeader className="space-y-3 border-b px-6 py-4">
          <div className="flex flex-wrap items-center gap-2">
            {role === "admin" ? (
              <Select value={item.workflowStage} onValueChange={(stage) => setItemStatus(item.id, stage, me, "admin")}>
                <SelectTrigger
                  className={`h-7 w-auto gap-1 border px-2 text-[11px] font-medium ${STATUS_TINT[item.status] || ""}`}
                  data-testid="select-status-detail"
                >
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {(board?.workflow ?? ["Backlog", "Working On It", "Waiting on Client", "In Review", "Approved", "Stuck"]).map((s) => (
                    <SelectItem key={s} value={s}>{s}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            ) : (
              <Badge variant="outline" className={`text-[10px] uppercase tracking-wide ${STATUS_TINT[item.status] || ""}`}>
                {STATUS_LABEL[item.status]}
              </Badge>
            )}
            {role === "admin" && (
              <Badge variant="outline" className="text-[10px] uppercase tracking-wide">
                Priority: {item.priority}
              </Badge>
            )}
            {board && <Badge variant="outline" className="text-[10px]">{board.name}</Badge>}
          </div>
          <SheetTitle className="text-lg leading-tight">{item.title}</SheetTitle>
          {item.description && (
            <p className="text-sm text-muted-foreground">{item.description}</p>
          )}
          <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
            {role === "admin" && (
              <span>Owner: <span className="text-foreground font-medium">{item.assignee}</span></span>
            )}
            {item.due && <span>Due: <span className="text-foreground">{new Date(item.due).toLocaleDateString()}</span></span>}
            {subTotal > 0 && role === "admin" && (
              <span className="inline-flex items-center gap-1.5">
                <span className="tabular-nums">Sub-items: {subDone}/{subTotal}</span>
                <span className="h-1 w-16 overflow-hidden rounded-full bg-muted">
                  <BarFill pct={subPct} />
                </span>
              </span>
            )}
          </div>
        </SheetHeader>

        {/* APPROVAL CLARITY BLOCK — single source of truth for who/what/next. */}
        <div
          className="space-y-3 border-b border-border bg-muted/40 px-6 py-4"
          data-testid="approval-clarity-block"
        >
          <ApprovalMetaRow
            meta={approvalMeta}
            viewer={role}
            testId="item-approval-meta"
          />
          {(isApprovalItem || approvalMeta.status === "approved") && (
            <NextActionBanner
              meta={approvalMeta}
              viewer={role}
              cta={
                approvalMeta.ctaForRole(role) === "approve"
                  ? {
                      label: "Approve",
                      onClick: () => submitApproval("approved"),
                      testId: "button-approve",
                    }
                  : undefined
              }
              secondaryCta={
                approvalMeta.ctaForRole(role) === "approve"
                  ? {
                      label: "Request changes",
                      onClick: () => submitApproval("changes-requested"),
                      testId: "button-request-changes",
                    }
                  : undefined
              }
              testId="item-next-action-banner"
            />
          )}
        </div>

        {/* Tabbed body. Client: Conversation / Files. Admin: Updates / Files / Subitems / Activity. */}
        <Tabs defaultValue="updates" className="flex flex-1 min-h-0 flex-col">
          <TabsList className="mx-6 mt-3 h-9 w-fit p-1">
            <TabsTrigger value="updates" className="text-xs px-3" data-testid="tab-updates">
              {role === "client" ? "Conversation" : "Updates"}
            </TabsTrigger>
            <TabsTrigger value="files" className="text-xs px-3" data-testid="tab-files">Files</TabsTrigger>
            {role === "admin" && (
              <TabsTrigger value="subitems" className="text-xs px-3" data-testid="tab-subitems">Subitems{subTotal > 0 ? ` (${subDone}/${subTotal})` : ""}</TabsTrigger>
            )}
            {role === "admin" && (
              <TabsTrigger value="activity" className="text-xs px-3" data-testid="tab-activity">Activity</TabsTrigger>
            )}
          </TabsList>

          <TabsContent value="subitems" className="mt-0 flex-1 overflow-y-auto px-6 py-3">
            {item.subitems.length === 0 && role === "client" && (
              <div className="rounded-md border border-dashed border-border py-8 text-center">
                <p className="text-sm text-zinc-500">No subitems yet.</p>
              </div>
            )}
            <ul className="space-y-1">
              {item.subitems.map((s) => (
                <li key={s.id} className="flex items-center gap-2 rounded px-2 py-1.5 hover:bg-muted/60 ">
                  <Checkbox
                    checked={s.done}
                    onCheckedChange={() => toggleSubitem(item.id, s.id)}
                    disabled={role === "client"}
                    data-testid={`subitem-detail-${s.id}`}
                  />
                  <span className={s.done ? "text-sm text-muted-foreground line-through" : "text-sm"}>{s.title}</span>
                  {s.assignee && <span className="ml-auto text-[11px] text-muted-foreground">{s.assignee}</span>}
                </li>
              ))}
            </ul>
            {role === "admin" && (
              <div className="mt-3 flex items-center gap-2">
                <Input
                  value={newSub}
                  onChange={(e) => setNewSub(e.target.value)}
                  placeholder="Add a sub-item…"
                  className="h-8 text-sm"
                  onKeyDown={(e) => {
                    if (e.key === "Enter" && newSub.trim()) {
                      addSubitem(item.id, newSub.trim(), item.assignee);
                      setNewSub("");
                    }
                  }}
                  data-testid="input-new-subitem"
                />
                <Button
                  size="sm"
                  variant="outline"
                  className="h-8 gap-1 text-xs"
                  onClick={() => {
                    if (newSub.trim()) {
                      addSubitem(item.id, newSub.trim(), item.assignee);
                      setNewSub("");
                    }
                  }}
                  data-testid="button-add-subitem"
                >
                  <Plus className="size-3.5" /> Add subitem
                </Button>
              </div>
            )}
          </TabsContent>

          <TabsContent value="files" className="mt-0 flex-1 overflow-y-auto px-6 py-3">
            <ItemFilesPanel itemId={item.id} role={role} boardName={board?.name} />
          </TabsContent>

          <TabsContent value="activity" className="mt-0 flex-1 overflow-y-auto px-6 py-3">
            <div className="space-y-2">
              {visiblePosts.filter((p) => p.systemKind || p.authorRole === "system").length === 0 && (
                <p className="text-sm text-zinc-500">No activity logged yet.</p>
              )}
              {visiblePosts.filter((p) => p.systemKind || p.authorRole === "system").map((p) => (
                <div key={p.id} className="flex items-start gap-2 text-xs text-muted-foreground">
                  <RefreshCcw className="mt-0.5 size-3 shrink-0" />
                  <div className="flex-1">
                    <span className="text-zinc-900 dark:text-zinc-100 font-medium">{p.authorName}</span> {p.body}
                  </div>
                  <span className="text-[11px] text-zinc-500 shrink-0">{shortDateTime(p.postedAt)}</span>
                </div>
              ))}
            </div>
          </TabsContent>

          <TabsContent value="updates" className="mt-0 flex flex-1 min-h-0 flex-col">
        {/* Updates thread */}
        <div className="flex-1 overflow-y-auto px-6 py-4" data-testid="updates-thread">
          {pinnedPosts.length > 0 && (
            <div className="mb-4 space-y-3 rounded-lg border border-primary/30 bg-primary/5 p-3">
              <p className="text-[10px] font-bold uppercase tracking-wider text-primary">Pinned</p>
              {pinnedPosts.map((p) => (
                <UpdatePost
                  key={p.id}
                  post={p}
                  itemId={item.id}
                  role={role}
                  me={me}
                  onLightbox={setLightbox}
                  onReply={(id) => setReplyTo(id)}
                  onReact={(id, emoji) => togglePostReaction(item.id, id, emoji)}
                  onPin={(id) => togglePostPin(item.id, id)}
                  replyOpenId={replyTo}
                  replyText={replyText}
                  setReplyText={setReplyText}
                  onSendReply={submitReply}
                />
              ))}
            </div>
          )}

          <div className="space-y-4">
            {regularPosts.length === 0 && pinnedPosts.length === 0 && (
              <div className="rounded-md border border-dashed py-10 text-center">
                <MessageCircle className="mx-auto mb-2 size-5 text-muted-foreground" />
                <p className="text-sm font-medium">No updates yet</p>
                <p className="mt-1 text-xs text-muted-foreground">
                  Start a conversation, share an image, or post an update.
                </p>
              </div>
            )}
            {regularPosts.map((p) => (
              <UpdatePost
                key={p.id}
                post={p}
                itemId={item.id}
                role={role}
                me={me}
                onLightbox={setLightbox}
                onReply={(id) => setReplyTo(id)}
                onReact={(id, emoji) => togglePostReaction(item.id, id, emoji)}
                onPin={(id) => togglePostPin(item.id, id)}
                replyOpenId={replyTo}
                replyText={replyText}
                setReplyText={setReplyText}
                onSendReply={submitReply}
              />
            ))}
          </div>
        </div>

        {/* Composer */}
        <div className="border-t bg-muted/40 px-6 py-3">
          {draftAttachments.length > 0 && (
            <div className="mb-2">
              <AttachmentChips
                attachments={draftAttachments}
                onChange={setDraftAttachments}
                testIdPrefix="composer-attach"
              />
            </div>
          )}
          <Textarea
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
            placeholder={role === "admin" ? "Write an update or post an internal note…" : "Write an update…"}
            className="min-h-[60px] resize-none text-sm"
            data-testid="textarea-update"
          />
          <div className="mt-2 flex flex-wrap items-center gap-2">
            <AttachmentTrigger
              attachments={draftAttachments}
              onChange={setDraftAttachments}
              testIdPrefix="composer-attach"
            />
            {role === "admin" && (
              <label
                className="ml-1 inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-warning/40 bg-warning/10 px-2 py-1 text-[11px] font-medium text-warning"
                title="Internal note — only Oversee can see this. The client never sees it on their portal."
              >
                <Checkbox
                  checked={internalOnly}
                  onCheckedChange={(v) => setInternalOnly(!!v)}
                  data-testid="checkbox-internal-only"
                  className="size-3.5 border-warning data-[state=checked]:bg-warning"
                />
                Internal note — only Oversee can see this
              </label>
            )}
            <Button
              onClick={send}
              size="sm"
              className="ml-auto h-7 gap-1 text-xs bg-primary text-primary-foreground hover:bg-primary/90"
              data-testid="button-post-update"
            >
              <Send className="size-3" /> Add update
            </Button>
          </div>
        </div>
          </TabsContent>
        </Tabs>

        {lightbox && (
          <div
            className="fixed inset-0 z-50 grid place-items-center bg-black/85 p-6"
            onClick={() => setLightbox(null)}
            data-testid="lightbox"
          >
            <img src={lightbox} alt="" className="max-h-[90vh] max-w-[90vw] rounded-lg" />
            <button
              onClick={() => setLightbox(null)}
              className="absolute right-6 top-6 grid size-9 place-items-center rounded-full bg-white/15 text-white hover:bg-white/30"
            >
              <X className="size-4" />
            </button>
          </div>
        )}
      </SheetContent>
    </Sheet>
  );
}

function ItemFilesPanel({
  itemId,
  role,
  boardName,
}: {
  itemId: string;
  role: "client" | "admin";
  boardName?: string;
}) {
  const { items, files, setFileApproval } = useDemoStore();
  const [uploadOpen, setUploadOpen] = useState(false);
  const [openFile, setOpenFile] = useState<FileEntry | null>(null);
  const item = items.find((i) => i.id === itemId);

  // Update-attachments — both images (thumbnail) and files (file card).
  const attachments = (item?.discussion ?? []).flatMap((p) =>
    (p.attachments ?? [])
      .filter((a) => a.kind === "image" || a.kind === "file")
      .map((a) => ({
        id: a.id,
        kind: a.kind as "image" | "file",
        name: a.caption || "Attachment",
        src: (a as { src?: string }).src ?? "",
        author: p.authorName,
      })),
  );

  // Files scoped to this board (or recent if no boardName tagging).
  const boardFiles = boardName
    ? files.filter((f) => f.boardName === boardName)
    : files.slice(0, 6);

  const empty = attachments.length === 0 && boardFiles.length === 0;

  const downloadFile = (f: FileEntry) => {
    // Preview-only. Open in new tab if a src exists, otherwise no-op visual.
    if (f.src) {
      window.open(f.src, "_blank", "noopener");
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
          Files for this item
        </p>
        {role === "client" && (
          <Button
            size="sm"
            variant="outline"
            className="h-7 gap-1 text-xs"
            onClick={() => setUploadOpen(true)}
            data-testid="button-upload-from-item"
          >
            <CloudUpload className="size-3.5" /> Upload
          </Button>
        )}
      </div>

      {empty && (
        <div className="rounded-md border border-dashed border-border py-8 text-center">
          <Paperclip className="mx-auto mb-2 size-5 text-muted-foreground" />
          <p className="text-sm font-medium">No files yet</p>
          <p className="mt-1 text-xs text-muted-foreground">
            {role === "client"
              ? "Use Upload above or attach images via the Updates tab."
              : "Attachments and approvals tied to this item will appear here."}
          </p>
        </div>
      )}

      {attachments.length > 0 && (
        <section>
          <p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">From updates</p>
          <div className="grid grid-cols-3 gap-2">
            {attachments.map((a) =>
              a.kind === "image" ? (
                <a
                  key={a.id}
                  href={a.src}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="overflow-hidden rounded-md border border-border transition hover:border-primary"
                  data-testid={`item-attachment-${a.id}`}
                >
                  <img src={a.src} alt={a.name} className="h-24 w-full object-cover" />
                  <div className="truncate px-2 py-1 text-[11px] text-muted-foreground">{a.author}</div>
                </a>
              ) : (
                <div
                  key={a.id}
                  className="flex flex-col rounded-md border border-border bg-card p-2"
                  data-testid={`item-attachment-${a.id}`}
                >
                  <div className="grid h-16 place-items-center rounded-sm bg-muted text-muted-foreground">
                    <FileText className="size-5" />
                  </div>
                  <p className="mt-1 truncate text-xs font-medium">{a.name}</p>
                  <p className="truncate text-[11px] text-muted-foreground">{a.author}</p>
                </div>
              ),
            )}
          </div>
        </section>
      )}

      {boardFiles.length > 0 && (
        <section>
          <p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Deliverables on this board</p>
          <ul className="space-y-2">
            {boardFiles.map((f) => (
              <li
                key={f.id}
                className="flex items-center gap-3 rounded-lg border border-border bg-card p-2"
                data-testid={`item-file-row-${f.id}`}
              >
                <div className="grid size-12 shrink-0 place-items-center overflow-hidden rounded-md border border-border bg-muted">
                  {f.kind === "image" && f.src ? (
                    <img src={f.src} alt={f.name} className="size-full object-cover" />
                  ) : (
                    <FileText className="size-5 text-muted-foreground" />
                  )}
                </div>
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-medium">{f.name}</p>
                  <p className="text-[11px] text-muted-foreground">
                    v{f.version} · {f.size} · {f.uploadedBy}
                  </p>
                </div>
                <FileApprovalBadge approval={f.approval} />
                <div className="flex items-center gap-1">
                  <Button
                    size="sm"
                    variant="ghost"
                    className="h-7 gap-1 text-[11px]"
                    onClick={() => setOpenFile(f)}
                    data-testid={`button-open-file-${f.id}`}
                  >
                    <Eye className="size-3.5" /> Open
                  </Button>
                  <Button
                    size="sm"
                    variant="ghost"
                    className="h-7 gap-1 text-[11px]"
                    onClick={() => downloadFile(f)}
                    data-testid={`button-download-file-${f.id}`}
                  >
                    <Download className="size-3.5" /> Download
                  </Button>
                  {role === "client" && f.approval === "pending" && (
                    <>
                      <Button
                        size="sm"
                        variant="outline"
                        className="h-7 gap-1 text-[11px]"
                        onClick={() => setFileApproval(f.id, "changes-requested")}
                        data-testid={`button-request-changes-${f.id}`}
                      >
                        <RefreshCcw className="size-3" /> Changes
                      </Button>
                      <Button
                        size="sm"
                        className="h-7 gap-1 text-[11px]"
                        onClick={() => setFileApproval(f.id, "approved")}
                        data-testid={`button-approve-file-${f.id}`}
                      >
                        <ThumbsUp className="size-3" /> Approve
                      </Button>
                    </>
                  )}
                </div>
              </li>
            ))}
          </ul>
        </section>
      )}

      <UploadFileDialog
        open={uploadOpen}
        onOpenChange={setUploadOpen}
        boardName={boardName}
      />
      <PreviewApprovalDialog
        file={openFile}
        onClose={() => setOpenFile(null)}
        role={role}
      />
    </div>
  );
}

function FileApprovalBadge({ approval }: { approval: FileEntry["approval"] }) {
  const map: Record<FileEntry["approval"], { label: string; cls: string }> = {
    approved: { label: "Approved", cls: "border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300" },
    pending: { label: "For Review", cls: "border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300" },
    "changes-requested": { label: "Changes", cls: "border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300" },
    "n/a": { label: "Draft", cls: "border-border bg-muted text-muted-foreground" },
  };
  const m = map[approval];
  return (
    <Badge variant="outline" className={`shrink-0 text-[10px] ${m.cls}`}>
      {m.label}
    </Badge>
  );
}

function UpdatePost({
  post,
  itemId: _itemId,
  role,
  me,
  onLightbox,
  onReply,
  onReact,
  onPin,
  replyOpenId,
  replyText,
  setReplyText,
  onSendReply,
}: {
  post: DiscussionPost;
  itemId: string;
  role: "client" | "admin";
  me: string;
  onLightbox: (src: string) => void;
  onReply: (id: string) => void;
  onReact: (id: string, emoji: string) => void;
  onPin: (id: string) => void;
  replyOpenId: string | null;
  replyText: string;
  setReplyText: (s: string) => void;
  onSendReply: (id: string) => void;
}) {
  if (post.authorRole === "system") {
    return (
      <div className="flex items-center gap-2 text-[11px] text-muted-foreground" data-testid={`system-post-${post.id}`}>
        <RefreshCcw className="size-3" />
        <span><span className="text-foreground">{post.authorName}</span> {post.body}</span>
        <span className="ml-auto">{shortDateTime(post.postedAt)}</span>
      </div>
    );
  }

  const initials = post.authorName.split(" ").map((s) => s[0]).join("").toUpperCase();
  const seen = post.seenBy && post.seenBy.length > 0;

  return (
    <div className={`flex gap-3 ${post.internalOnly ? "rounded-md border-l-4 border-amber-400 bg-amber-50/60 p-2 dark:bg-amber-950/20" : ""}`} data-testid={`update-post-${post.id}`}>
      <div className={`grid size-8 shrink-0 place-items-center rounded-full text-xs font-semibold ${post.authorRole === "admin" ? "bg-primary text-primary-foreground" : "bg-muted text-foreground "}`}>
        {initials}
      </div>
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-2">
          <span className="text-sm font-medium">{post.authorName}</span>
          <span className="text-[11px] text-muted-foreground">{shortDateTime(post.postedAt)}</span>
          {post.internalOnly && (
            <Badge variant="outline" className="border-warning/40 bg-warning/10 text-[10px] uppercase tracking-wide text-warning">
              <EyeOff className="mr-1 size-2.5" /> Internal
            </Badge>
          )}
          {post.systemKind === "approval" && (
            <Badge variant="outline" className="border-success/40 bg-success/10 text-[10px] text-success">
              <CheckCircle2 className="mr-1 size-2.5" /> Approval
            </Badge>
          )}
        </div>
        {post.body && <p className="mt-1 whitespace-pre-wrap text-sm leading-relaxed">{post.body}</p>}
        {post.attachments && post.attachments.length > 0 && (
          <div className="mt-2 flex flex-wrap gap-2">
            {post.attachments.map((a) => {
              if (a.kind === "image") {
                return (
                  <button
                    key={a.id}
                    onClick={() => onLightbox(a.src)}
                    className="size-32 overflow-hidden rounded-md border hover:border-primary"
                    data-testid={`attachment-image-${a.id}`}
                  >
                    <img src={a.src} alt={a.caption} className="size-full object-cover" />
                  </button>
                );
              }
              if (a.kind === "loom") {
                return (
                  <a
                    key={a.id}
                    href={a.loomUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="flex w-72 items-center gap-3 rounded-md border bg-muted/50 px-3 py-2 hover:border-primary"
                  >
                    <div className="grid size-10 place-items-center rounded-md bg-primary text-primary-foreground">
                      <Video className="size-4" />
                    </div>
                    <div className="min-w-0">
                      <p className="text-xs font-medium">{a.caption || "Loom recording"}</p>
                      <p className="truncate text-[11px] text-muted-foreground">{a.loomUrl}</p>
                    </div>
                  </a>
                );
              }
              if (a.kind === "file") {
                const name = a.caption || "Attachment";
                const ext = (name.split(".").pop() || "FILE").toUpperCase();
                return (
                  <div
                    key={a.id}
                    className="flex w-72 items-center gap-3 rounded-md border border-border bg-card px-3 py-2"
                    data-testid={`attachment-file-${a.id}`}
                  >
                    <div className="grid size-10 shrink-0 place-items-center rounded-md bg-muted text-muted-foreground">
                      <FileText className="size-4" />
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-xs font-medium">{name}</p>
                      <p className="text-[11px] text-muted-foreground">{ext} file</p>
                    </div>
                  </div>
                );
              }
              return null;
            })}
          </div>
        )}

        {/* Reactions row */}
        <div className="mt-2 flex flex-wrap items-center gap-1.5">
          {(post.reactions || []).map((r, i) => (
            <button
              key={i}
              onClick={() => onReact(post.id, r.emoji)}
              className={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs transition ${r.reacted ? "border-primary/40 bg-primary/10" : "bg-card hover:bg-muted"}`}
              data-testid={`reaction-${post.id}-${r.emoji}`}
            >
              <span>{r.emoji}</span>
              <span className="tabular-nums">{r.count}</span>
            </button>
          ))}
          <Popover>
            <PopoverTrigger asChild>
              <button
                className="inline-flex items-center gap-1 rounded-full border bg-card px-2 py-0.5 text-xs text-muted-foreground hover:bg-muted"
                data-testid={`button-add-reaction-${post.id}`}
              >
                <Smile className="size-3" />
              </button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-1" align="start">
              <div className="flex gap-0.5">
                {QUICK_REACTIONS.map((emoji) => (
                  <button
                    key={emoji}
                    onClick={() => onReact(post.id, emoji)}
                    className="rounded p-1 text-base hover:bg-muted"
                  >
                    {emoji}
                  </button>
                ))}
              </div>
            </PopoverContent>
          </Popover>
          <button
            onClick={() => onReply(post.id)}
            className="ml-1 text-[11px] text-muted-foreground hover:text-foreground"
            data-testid={`button-reply-${post.id}`}
          >
            Reply
          </button>
          <button
            onClick={() => onPin(post.id)}
            className={`text-[11px] ${post.pinned ? "text-primary" : "text-muted-foreground hover:text-foreground"}`}
            data-testid={`button-pin-${post.id}`}
          >
            <Pin className="size-3 inline" /> {post.pinned ? "Pinned" : "Pin"}
          </button>
          {seen && (
            <span className="ml-auto inline-flex items-center gap-1 text-[11px] text-muted-foreground">
              <Eye className="size-3" /> Seen by {post.seenBy!.length}
            </span>
          )}
        </div>

        {/* Replies */}
        {post.replies && post.replies.length > 0 && (
          <div className="mt-3 space-y-3 border-l-2 border-border/60 pl-3">
            {post.replies.map((r) => {
              const ri = r.authorName.split(" ").map((s) => s[0]).join("").toUpperCase();
              return (
                <div key={r.id} className="flex gap-2">
                  <div className={`grid size-6 shrink-0 place-items-center rounded-full text-[10px] font-semibold ${r.authorRole === "admin" ? "bg-primary text-primary-foreground" : "bg-muted text-foreground "}`}>
                    {ri}
                  </div>
                  <div className="min-w-0">
                    <p className="text-xs">
                      <span className="font-medium">{r.authorName}</span>
                      <span className="ml-2 text-muted-foreground">{shortDateTime(r.postedAt)}</span>
                    </p>
                    <p className="mt-0.5 whitespace-pre-wrap text-sm">{r.body}</p>
                  </div>
                </div>
              );
            })}
          </div>
        )}

        {/* Reply input */}
        {replyOpenId === post.id && (
          <div className="mt-2 flex items-end gap-2 rounded-md border bg-card p-2">
            <Textarea
              value={replyText}
              onChange={(e) => setReplyText(e.target.value)}
              placeholder={`Reply as ${me}…`}
              className="min-h-[40px] resize-none border-0 p-1 text-sm focus-visible:ring-0"
              autoFocus
              data-testid={`textarea-reply-${post.id}`}
            />
            <Button
              size="sm"
              onClick={() => onSendReply(post.id)}
              className="h-7 text-xs"
              data-testid={`button-send-reply-${post.id}`}
            >
              Send
            </Button>
          </div>
        )}
      </div>
    </div>
  );
}
