// Full-screen approval dialog — large preview viewer with comments, attachments,
// and Approve / Request changes actions. Drives BOTH file approval state AND the
// linked board item (if found) so admin sees the change in their feed.
import { useEffect, useMemo, useRef, useState } from "react";
import {
  CheckCircle2,
  ImageIcon,
  Maximize2,
  MessageSquare,
  Minimize2,
  Paperclip,
  RefreshCcw,
  ThumbsUp,
  X,
  ZoomIn,
  ZoomOut,
} from "lucide-react";
import { Dialog, DialogContent, DialogTitle } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Textarea } from "@/components/ui/textarea";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { useDemoStore, type FileEntry, type BoardItem } from "@/lib/demo-store";
import { useToast } from "@/hooks/use-toast";
import { DocumentMockPage } from "@/components/document-preview";
import { PanZoomImage } from "@/components/dynamic-styles";
import { ApprovalMetaRow, NextActionBanner } from "@/components/approval-clarity";
import { getFileApproval } from "@/lib/approval-clarity";

type Props = {
  file: FileEntry | null;
  onClose: () => void;
  role: "client" | "admin";
};

// Status badge tones use semantic Tailwind palettes (warning / success / destructive)
// instead of arbitrary colors — these still need light/dark variants because the
// design tokens don't expose pre-built semantic surfaces.
const APPROVAL_TONE: Record<FileEntry["approval"], string> = {
  pending:
    "border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200",
  approved:
    "border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200",
  "changes-requested":
    "border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200",
  "n/a": "border-border bg-muted text-muted-foreground",
};

const APPROVAL_LABEL: Record<FileEntry["approval"], string> = {
  pending: "Awaiting your review",
  approved: "Approved",
 "changes-requested": "Changes requested",
 "n/a": "Reference only",
};

export function PreviewApprovalDialog({ file, onClose, role }: Props) {
  const { items, boards, setFileApproval, setItemStatus, addDiscussionPost } = useDemoStore();
  const { toast } = useToast();
  const [comment, setComment] = useState("");
  const [zoom, setZoom] = useState(1);
  const [pan, setPan] = useState({ x: 0, y: 0 });
  const [draftAttachments, setDraftAttachments] = useState<{ id: string; src: string; caption: string }[]>([]);
  const fileInputRef = useRef<HTMLInputElement | null>(null);

  // Reset state when file changes.
  useEffect(() => {
    setComment("");
    setZoom(1);
    setPan({ x: 0, y: 0 });
    setDraftAttachments([]);
  }, [file?.id]);

  // Find the linked item — items in the same board, in review/client-input.
  const { board, item } = useMemo(() => {
    if (!file) return { board: undefined, item: undefined };
    const board = boards.find((b) => b.name === file.boardName);
    if (!board) return { board: undefined, item: undefined };
    // Prefer items in review status; else first item with approval-related state.
    const candidates = items.filter((i) => i.boardId === board.id);
    const item =
      candidates.find((i) => i.status === "review") ??
      candidates.find((i) => i.tags?.includes("approval")) ??
      candidates[0];
    return { board, item };
  }, [file, items, boards]);

  if (!file) return null;

  const me = role === "client" ? "Maya Lin" : "Sasha Patel";
  const isPending = file.approval === "pending";
  const canSubmit = comment.trim().length > 0;
  const fileMeta = getFileApproval(file);
  // Only the *correct* approver should be able to decide. A staff-uploaded
  // file is for the client to approve; a client-uploaded file is for staff to
  // review. Anyone else viewing sees the file but cannot decide.
  const canDecide = isPending && fileMeta.ctaForRole(role) === "approve";

  const onApprove = () => {
    setFileApproval(file.id, "approved", comment.trim() || undefined);
    if (item) {
      addDiscussionPost(item.id, {
        authorName: me,
        authorRole: role,
        body: comment.trim()
          ? `Approved “${file.name}”. ${comment.trim()}`
          : `Approved “${file.name}”.`,
        systemKind: "approval",
        seenBy: [me],
        attachments: draftAttachments.map((a) => ({
          id: a.id,
          kind: "image" as const,
          src: a.src,
          caption: a.caption,
        })),
      });
      setItemStatus(item.id, "Approved", me, role);
    }
    toast({
      title: "Approved",
      description: `“${file.name}” marked approved. Your team has been notified.`,
    });
    onClose();
  };

  const onRequestChanges = () => {
    if (!canSubmit) return;
    setFileApproval(file.id, "changes-requested", comment.trim());
    if (item) {
      addDiscussionPost(item.id, {
        authorName: me,
        authorRole: role,
        body: `Requested changes on “${file.name}”: ${comment.trim()}`,
        systemKind: "approval",
        seenBy: [me],
        attachments: draftAttachments.map((a) => ({
          id: a.id,
          kind: "image" as const,
          src: a.src,
          caption: a.caption,
        })),
      });
      setItemStatus(item.id, "Working On It", me, role);
    }
    toast({
      title: "Changes requested",
      description: "Your team has been notified and will revise.",
    });
    onClose();
  };

  const handleAttach = (e: React.ChangeEvent<HTMLInputElement>) => {
    const fileList = e.target.files;
    if (!fileList) return;
    const reads: Promise<{ id: string; src: string; caption: string }>[] = [];
    Array.from(fileList).forEach((f) => {
      const reader = new FileReader();
      const p = new Promise<{ id: string; src: string; caption: string }>((resolve) => {
        reader.onload = () =>
          resolve({
            id: `att_${Date.now()}_${Math.random().toString(36).slice(2, 6)}`,
            src: String(reader.result || ""),
            caption: f.name,
          });
      });
      reader.readAsDataURL(f);
      reads.push(p);
    });
    Promise.all(reads).then((attached) => {
      setDraftAttachments((prev) => [...prev, ...attached]);
    });
    // reset input to allow same file selection again
    e.target.value = "";
  };

  const isImage = file.kind === "image" && file.src;

  return (
    <Dialog open={!!file} onOpenChange={(v) => !v && onClose()}>
      <DialogContent
        className="grid max-h-[95vh] w-[98vw] max-w-[1600px] grid-rows-[auto_minmax(0,1fr)] gap-0 overflow-hidden p-0 sm:rounded-xl"
        data-testid="preview-approval-dialog"
        hideClose
      >
        <DialogTitle className="sr-only">Review and approve preview: {file.name}</DialogTitle>
        {/* Header */}
        <div className="flex items-start justify-between gap-4 border-b border-border bg-card px-5 py-3.5">
          <div className="min-w-0">
            <div className="flex items-center gap-2">
              <Badge
                variant="outline"
                className={`text-[10px] uppercase tracking-wide ${APPROVAL_TONE[file.approval]}`}
                data-testid="preview-status"
              >
                {fileMeta.statusLabel}
              </Badge>
              <span className="text-[11px] text-muted-foreground">v{file.version} · {file.size}</span>
            </div>
            <h2 className="mt-1 truncate text-base font-semibold tracking-tight md:text-lg" data-testid="preview-name">
              {file.name}
            </h2>
            <p className="mt-0.5 truncate text-xs text-muted-foreground">
              {file.boardName ? `${file.boardName} · ` : ""}Uploaded {new Date(file.uploadedAt).toLocaleDateString()} by {file.uploadedBy}
            </p>
          </div>
          <div className="flex shrink-0 items-center gap-1">
            {isImage && (
              <TooltipProvider>
                <Tooltip>
                  <TooltipTrigger asChild>
                    <Button
                      size="icon"
                      variant="outline"
                      className="size-8"
                      onClick={() => setZoom((z) => Math.max(0.5, z - 0.25))}
                      data-testid="button-zoom-out"
                      aria-label="Zoom out"
                    >
                      <ZoomOut className="size-4" />
                    </Button>
                  </TooltipTrigger>
                  <TooltipContent>Zoom out</TooltipContent>
                </Tooltip>
                <Tooltip>
                  <TooltipTrigger asChild>
                    <Button
                      size="icon"
                      variant="outline"
                      className="size-8"
                      onClick={() => {
                        setZoom(1);
                        setPan({ x: 0, y: 0 });
                      }}
                      data-testid="button-zoom-reset"
                      aria-label="Reset zoom"
                    >
                      {zoom === 1 ? <Maximize2 className="size-4" /> : <Minimize2 className="size-4" />}
                    </Button>
                  </TooltipTrigger>
                  <TooltipContent>Reset view</TooltipContent>
                </Tooltip>
                <Tooltip>
                  <TooltipTrigger asChild>
                    <Button
                      size="icon"
                      variant="outline"
                      className="size-8"
                      onClick={() => setZoom((z) => Math.min(3, z + 0.25))}
                      data-testid="button-zoom-in"
                      aria-label="Zoom in"
                    >
                      <ZoomIn className="size-4" />
                    </Button>
                  </TooltipTrigger>
                  <TooltipContent>Zoom in</TooltipContent>
                </Tooltip>
              </TooltipProvider>
            )}
            <Button
              size="icon"
              variant="ghost"
              className="size-8"
              onClick={onClose}
              data-testid="button-close-preview"
              aria-label="Close preview"
            >
              <X className="size-4" />
            </Button>
          </div>
        </div>

        {/* Body — split */}
        <div className="grid min-h-0 grid-cols-1 lg:grid-cols-[minmax(0,1fr)_420px]">
          {/* Left: large preview */}
          <div
            className="relative flex min-h-[60vh] items-center justify-center overflow-auto bg-zinc-950 p-4"
            data-testid="preview-canvas"
          >
            {isImage ? (
              <PanZoomImage
                src={file.src!}
                alt={file.name}
                panX={pan.x}
                panY={pan.y}
                zoom={zoom}
                className="max-h-[80vh] max-w-full select-none rounded-md object-contain shadow-2xl"
              />
            ) : (
              <div className="flex w-full max-w-2xl flex-col items-center gap-4">
                <div className="aspect-[4/5] w-full max-w-md overflow-hidden rounded-md border border-zinc-700 bg-zinc-100 shadow-2xl ">
                  <DocumentMockPage file={file} />
                </div>
                <div className="text-center text-zinc-300">
                  <p className="text-sm font-medium">{file.name}</p>
                  <p className="mt-1 text-xs text-zinc-500">
                    {file.kind.toUpperCase()} · {file.size} · v{file.version}
                  </p>
                  <p className="mt-2 text-[11px] text-zinc-500">
                    Mock page preview shown for demo. Approve or request changes on the right.
                  </p>
                </div>
              </div>
            )}
          </div>

          {/* Right: review panel */}
          <aside className="flex min-h-0 flex-col border-t border-border bg-background lg:border-l lg:border-t-0">
            {/* Context */}
            <div className="border-b border-border px-5 py-4">
              {board && (
                <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                  {board.name}
                </p>
              )}
              {item && (
                <p className="mt-1 text-sm font-medium" data-testid="preview-item-title">
                  {item.title}
                </p>
              )}
              {item?.description && (
                <p className="mt-1 line-clamp-3 text-xs text-muted-foreground">{item.description}</p>
              )}
            </div>

            {/* Approval clarity — single source of truth for who/what/next */}
            <div className="flex min-h-0 flex-1 flex-col">
              <div className="space-y-3 border-b border-border bg-muted/40 px-5 py-4">
                <ApprovalMetaRow
                  meta={fileMeta}
                  viewer={role}
                  testId="file-approval-meta"
                />
                <NextActionBanner
                  meta={fileMeta}
                  viewer={role}
                  testId="file-next-action-banner"
                />
              </div>

              <div className="flex min-h-0 flex-1 flex-col gap-3 px-5 py-4">
                <label className="text-xs font-medium text-foreground" htmlFor="approval-comment">
                  <MessageSquare className="mr-1 inline size-3" />
                  Comment {role === "client" && "(required for changes)"}
                </label>
                <Textarea
                  id="approval-comment"
                  value={comment}
                  onChange={(e) => setComment(e.target.value)}
                  placeholder={role === "client" ? "Optional praise, or specifics on what to change…" : "Add a note for the client…"}
                  className="min-h-[120px] resize-none text-sm"
                  data-testid="textarea-approval-comment"
                />

                {/* Attachments */}
                {draftAttachments.length > 0 && (
                  <div className="flex flex-wrap gap-2">
                    {draftAttachments.map((a) => (
                      <div key={a.id} className="group relative">
                        <img
                          src={a.src}
                          alt={a.caption}
                          className="size-16 rounded-md border border-border object-cover"
                        />
                        <button
                          type="button"
                          onClick={() => setDraftAttachments((prev) => prev.filter((x) => x.id !== a.id))}
                          className="absolute -right-1.5 -top-1.5 rounded-full bg-zinc-900 p-0.5 text-white opacity-0 group-hover:opacity-100"
                          aria-label="Remove attachment"
                        >
                          <X className="size-3" />
                        </button>
                      </div>
                    ))}
                  </div>
                )}

                <div className="flex items-center justify-between">
                  <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/*"
                    multiple
                    className="hidden"
                    onChange={handleAttach}
                    data-testid="input-attach-image"
                  />
                  <Button
                    size="sm"
                    variant="ghost"
                    className="gap-1.5 px-2 text-xs"
                    onClick={() => fileInputRef.current?.click()}
                    data-testid="button-attach-image"
                  >
                    <Paperclip className="size-3.5" />
                    Attach screenshot
                  </Button>
                </div>
              </div>

              {/* Action footer */}
              <div className="flex flex-col gap-2 border-t border-border bg-card px-5 py-4 sm:flex-row">
                <TooltipProvider>
                  <Tooltip>
                    <TooltipTrigger asChild>
                      <span className="flex-1">
                        <Button
                          variant="outline"
                          className="w-full gap-1.5 border-rose-300 text-rose-700 hover:bg-rose-50 dark:border-rose-900/60 dark:text-rose-300 dark:hover:bg-rose-950/40"
                          onClick={onRequestChanges}
                          disabled={!canSubmit || !canDecide}
                          data-testid="button-request-changes"
                        >
                          <RefreshCcw className="size-4" />
                          Request changes
                        </Button>
                      </span>
                    </TooltipTrigger>
                    {!canDecide && isPending && (
                      <TooltipContent side="top">
                        {role === "client"
                          ? "Oversee staff is reviewing this internally before it comes to you."
                          : "Client is the approver — they decide here."}
                      </TooltipContent>
                    )}
                    {canDecide && !canSubmit && (
                      <TooltipContent side="top">
                        Add a comment to describe what should change.
                      </TooltipContent>
                    )}
                    {!isPending && <TooltipContent side="top">Already decided.</TooltipContent>}
                  </Tooltip>
                </TooltipProvider>

                <Button
                  className="flex-1 gap-1.5"
                  onClick={onApprove}
                  disabled={!canDecide}
                  data-testid="button-approve"
                >
                  {file.approval === "approved" ? (
                    <>
                      <CheckCircle2 className="size-4" /> Approved
                    </>
                  ) : (
                    <>
                      <ThumbsUp className="size-4" /> Approve
                    </>
                  )}
                </Button>
              </div>
            </div>
          </aside>
        </div>
      </DialogContent>
    </Dialog>
  );
}

// Lightweight helper used by other components to pop the dialog imperatively
// without prop drilling — kept simple via a hook + state.
export type PreviewTarget = FileEntry | null;
export type PreviewItemContext = BoardItem | null;
