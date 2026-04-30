import { useMemo, useState } from "react";
import {
  CheckCircle2,
  Clock3,
  FileIcon,
  ImageIcon,
  MessageSquare,
  RotateCcw,
  X,
  Download,
  Upload,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Sheet, SheetContent } from "@/components/ui/sheet";
import { Textarea } from "@/components/ui/textarea";
import { useToast } from "@/hooks/use-toast";
import type { Board, BoardItem, FileEntry } from "@/lib/demo-store";
import { useDemoStore } from "@/lib/demo-store";

type Props = {
  board: Board;
  items: BoardItem[];
  role: "client" | "admin";
};

function approvalBadge(state: FileEntry["approval"]) {
  if (state === "approved")
    return (
      <Badge className="bg-emerald-100 text-emerald-700 hover:bg-emerald-100 border-0">
        <CheckCircle2 className="w-3 h-3 mr-1" />
        Approved
      </Badge>
    );
  if (state === "changes-requested")
    return (
      <Badge className="bg-amber-100 text-amber-700 hover:bg-amber-100 border-0">
        <RotateCcw className="w-3 h-3 mr-1" />
        Changes requested
      </Badge>
    );
  if (state === "pending")
    return (
      <Badge className="bg-blue-100 text-blue-700 hover:bg-blue-100 border-0">
        <Clock3 className="w-3 h-3 mr-1" />
        Pending
      </Badge>
    );
  return (
    <Badge variant="outline" className="border-border text-zinc-600">
      —
    </Badge>
  );
}

export function BoardFilesView({ board, items, role }: Props) {
  const { files, setFileApproval } = useDemoStore();
  const [open, setOpen] = useState<FileEntry | null>(null);
  const [comment, setComment] = useState("");
  const { toast } = useToast();

  const boardFiles = useMemo(() => {
    return files.filter((f) => f.boardName === board.name);
  }, [files, board.name]);

  if (boardFiles.length === 0) {
    return (
      <div className="border border-dashed border-strong  rounded-lg p-12 text-center">
        <Upload className="w-8 h-8 mx-auto text-zinc-400 mb-3" />
        <h3 className="text-base font-semibold text-zinc-900 dark:text-zinc-100 mb-1">
          No files yet
        </h3>
        <p className="text-sm text-muted-foreground mb-4 max-w-sm mx-auto">
          Files attached to items on this board will appear here. Open an item and use{" "}
          <span className="font-medium">Files</span> to add one.
        </p>
      </div>
    );
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-4">
        <div>
          <h3 className="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
            All files on this board
          </h3>
          <p className="text-xs text-muted-foreground">
            {boardFiles.length} file{boardFiles.length === 1 ? "" : "s"}. Click a file to review.
          </p>
        </div>
      </div>

      <div className="border border-border rounded-lg overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-muted/50">
            <tr className="text-left text-xs uppercase tracking-wide text-muted-foreground">
              <th className="px-4 py-2 font-medium">File</th>
              <th className="px-4 py-2 font-medium">Uploaded by</th>
              <th className="px-4 py-2 font-medium">Version</th>
              <th className="px-4 py-2 font-medium">Approval</th>
              <th className="px-4 py-2 font-medium w-28 text-right">Action</th>
            </tr>
          </thead>
          <tbody>
            {boardFiles.map((f) => (
              <tr
                key={f.id}
                onClick={() => setOpen(f)}
                className="border-t border-border cursor-pointer hover:bg-muted/60 "
                data-testid={`file-row-${f.id}`}
              >
                <td className="px-4 py-3">
                  <div className="flex items-center gap-3">
                    <div className="w-8 h-8 rounded bg-muted flex items-center justify-center shrink-0">
                      {f.kind === "image" ? (
                        <ImageIcon className="w-4 h-4 text-zinc-500" />
                      ) : (
                        <FileIcon className="w-4 h-4 text-zinc-500" />
                      )}
                    </div>
                    <div className="min-w-0">
                      <div className="font-medium text-zinc-900 dark:text-zinc-100 truncate">
                        {f.name}
                      </div>
                      <div className="text-xs text-zinc-500">
                        {f.size} • {f.uploadedAt}
                      </div>
                    </div>
                  </div>
                </td>
                <td className="px-4 py-3 text-muted-foreground">{f.uploadedBy}</td>
                <td className="px-4 py-3 text-zinc-600">v{f.version}</td>
                <td className="px-4 py-3">{approvalBadge(f.approval)}</td>
                <td className="px-4 py-3 text-right">
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={(e) => {
                      e.stopPropagation();
                      setOpen(f);
                    }}
                    data-testid={`button-open-file-${f.id}`}
                  >
                    Open
                  </Button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <Sheet open={!!open} onOpenChange={(v) => !v && (setOpen(null), setComment(""))}>
        <SheetContent
          side="right"
          className="w-full sm:max-w-2xl flex flex-col p-0"
        >
          {open && (
            <>
              <div className="flex items-center justify-between px-6 py-4 border-b border-border">
                <div className="min-w-0">
                  <div className="text-xs text-zinc-500 uppercase tracking-wide">
                    File preview
                  </div>
                  <div className="text-base font-semibold text-zinc-900 dark:text-zinc-100 truncate">
                    {open.name}
                  </div>
                </div>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => {
                    setOpen(null);
                    setComment("");
                  }}
                  data-testid="button-close-file-preview"
                >
                  <X className="w-4 h-4 mr-1" />
                  Close
                </Button>
              </div>

              <div className="flex-1 overflow-y-auto p-6 bg-zinc-50 dark:bg-zinc-950">
                <div className="bg-white  border border-border rounded-lg p-4 mb-4">
                  {open.kind === "image" && open.src ? (
                    <img
                      src={open.src}
                      alt={open.name}
                      className="w-full max-h-[420px] object-contain rounded"
                    />
                  ) : (
                    <div className="h-64 flex flex-col items-center justify-center text-zinc-400">
                      <FileIcon className="w-12 h-12 mb-2" />
                      <p className="text-sm">{open.kind.toUpperCase()} preview</p>
                      <Button variant="outline" size="sm" className="mt-3">
                        <Download className="w-4 h-4 mr-1" />
                        Download
                      </Button>
                    </div>
                  )}
                  <div className="flex items-center justify-between mt-3 pt-3 border-t border-border text-xs text-zinc-500">
                    <span>
                      v{open.version} • {open.size}
                    </span>
                    <span>
                      Uploaded by {open.uploadedBy} • {open.uploadedAt}
                    </span>
                  </div>
                </div>

                <div className="bg-white  border border-border rounded-lg p-4">
                  <div className="flex items-center justify-between mb-2">
                    <h4 className="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                      Add a comment
                    </h4>
                    <span>{approvalBadge(open.approval)}</span>
                  </div>
                  <Textarea
                    value={comment}
                    onChange={(e) => setComment(e.target.value)}
                    placeholder="Leave a comment for the team..."
                    rows={3}
                    data-testid="textarea-file-comment"
                  />
                  <div className="flex justify-end mt-2">
                    <Button
                      size="sm"
                      variant="outline"
                      disabled={!comment.trim()}
                      onClick={() => {
                        toast({
                          title: "Comment added",
                          description: `Comment posted on ${open.name}.`,
                        });
                        setComment("");
                      }}
                      data-testid="button-add-comment"
                    >
                      <MessageSquare className="w-4 h-4 mr-1" />
                      Add comment
                    </Button>
                  </div>
                </div>
              </div>

              <div className="border-t border-border bg-white  px-6 py-3 flex items-center justify-between gap-2">
                <div className="text-xs text-zinc-500">
                  {role === "client"
                    ? "Approve to lock this version, or request changes."
                    : "As admin you can also approve on behalf of the client."}
                </div>
                <div className="flex items-center gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => {
                      setFileApproval(open.id, "changes-requested", comment);
                      toast({
                        title: "Changes requested",
                        description: `${open.name} sent back for revisions.`,
                      });
                      setOpen(null);
                      setComment("");
                    }}
                    data-testid="button-request-changes"
                  >
                    <RotateCcw className="w-4 h-4 mr-1" />
                    Request changes
                  </Button>
                  <Button
                    size="sm"
                    className="bg-primary text-primary-foreground hover:bg-primary/90"
                    onClick={() => {
                      setFileApproval(open.id, "approved", comment);
                      toast({
                        title: "Approved",
                        description: `${open.name} marked approved.`,
                      });
                      setOpen(null);
                      setComment("");
                    }}
                    data-testid="button-approve-file"
                  >
                    <CheckCircle2 className="w-4 h-4 mr-1" />
                    Approve
                  </Button>
                </div>
              </div>
            </>
          )}
        </SheetContent>
      </Sheet>
      {/* keep items reference to avoid lint warning when grouping later */}
      <div className="hidden">{items.length}</div>
    </div>
  );
}
