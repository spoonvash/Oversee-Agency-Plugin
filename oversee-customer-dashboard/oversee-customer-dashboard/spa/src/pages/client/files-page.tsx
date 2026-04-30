// Client Files page (route /client/files).
// Refactored to consume PageHeader + SummaryStat strip + SectionCard.
import { useMemo, useState } from "react";
import { useLocation } from "wouter";
import { ArrowLeft, CloudUpload, FolderOpen, Sparkles } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useDemoStore, type FileEntry } from "@/lib/demo-store";
import { PreviewBigCard } from "@/components/document-preview";
import { PreviewApprovalDialog } from "@/components/preview-approval-dialog";
import { UploadFileDialog } from "@/components/upload-dialog";
import { PageHeader, SectionCard, SummaryStat } from "@/components/shared";
import { getFileApproval } from "@/lib/approval-clarity";

type TabKey = "needs-you" | "with-staff" | "approved" | "shared" | "uploaded";

export default function ClientFilesPage() {
  const [, navigate] = useLocation();
  const { files } = useDemoStore();
  const [tab, setTab] = useState<TabKey>("needs-you");
  const [openFile, setOpenFile] = useState<FileEntry | null>(null);
  const [uploadOpen, setUploadOpen] = useState(false);

  const buckets = useMemo(() => {
    const sortNewest = (a: FileEntry, b: FileEntry) =>
      new Date(b.uploadedAt).getTime() - new Date(a.uploadedAt).getTime();
    // Split pending into the two real buckets (client-decideable vs staff-decideable).
    const pending = files.filter((f) => f.approval === "pending");
    const needsYou = pending
      .filter((f) => getFileApproval(f).status === "needs_client_approval")
      .sort(sortNewest);
    const withStaff = pending
      .filter((f) => getFileApproval(f).status === "needs_staff_review")
      .sort(sortNewest);
    const approved = files.filter((f) => f.approval === "approved").sort(sortNewest);
    const uploaded = files
      .filter((f) => /you|client|maya/i.test(f.uploadedBy))
      .sort(sortNewest);
    const sharedIds = new Set(uploaded.map((f) => f.id));
    const shared = files.filter((f) => !sharedIds.has(f.id)).sort(sortNewest);
    return { needsYou, withStaff, approved, shared, uploaded };
  }, [files]);

  return (
    <div className="space-y-5" data-testid="client-files-page">
      <Button
        variant="ghost"
        size="sm"
        onClick={() => navigate("/client/work")}
        className="-ml-2 gap-1 text-xs text-muted-foreground"
        data-testid="link-back-to-work"
      >
        <ArrowLeft className="size-3.5" /> Back to My Work
      </Button>

      <PageHeader
        eyebrow="Workspace"
        title="Files & previews"
        subtitle="Everything Oversee has shipped to you. Click any thumbnail to open the full preview, leave comments, and approve or request changes."
        testId="heading-files"
        actions={
          <Button
            size="sm"
            className="gap-1.5"
            onClick={() => setUploadOpen(true)}
            data-testid="button-upload-files"
          >
            <CloudUpload className="size-4" /> Upload files
          </Button>
        }
      />

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <SummaryStat
          tone={buckets.needsYou.length > 0 ? "primary" : "neutral"}
          label="Needs your approval"
          value={buckets.needsYou.length}
          hint="waiting on you"
          testId="stat-review"
        />
        <SummaryStat
          tone={buckets.withStaff.length > 0 ? "warning" : "neutral"}
          label="With Oversee staff"
          value={buckets.withStaff.length}
          hint="staff reviewing"
          testId="stat-with-staff"
        />
        <SummaryStat
          tone="success"
          label="Approved"
          value={buckets.approved.length}
          hint="signed off"
          testId="stat-approved"
        />
        <SummaryStat
          tone="info"
          label="Shared by Oversee"
          value={buckets.shared.length}
          hint="recent deliverables"
          testId="stat-shared"
        />
      </div>

      <Tabs value={tab} onValueChange={(v) => setTab(v as TabKey)} data-testid="files-tabs">
        <TabsList className="flex w-full flex-wrap justify-start gap-1 bg-muted/40 p-1 lg:w-auto">
          <TabsTrigger value="needs-you" data-testid="tab-needs-review" className="gap-1.5 text-xs">
            Needs your approval
            {buckets.needsYou.length > 0 && (
              <span className="ml-0.5 rounded bg-orange-100 px-1.5 py-0.5 text-[10px] font-semibold text-orange-700 dark:bg-orange-950/60 dark:text-orange-300">
                {buckets.needsYou.length}
              </span>
            )}
          </TabsTrigger>
          <TabsTrigger value="with-staff" data-testid="tab-with-staff" className="gap-1.5 text-xs">
            With Oversee staff
            {buckets.withStaff.length > 0 && (
              <span className="ml-0.5 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                {buckets.withStaff.length}
              </span>
            )}
          </TabsTrigger>
          <TabsTrigger value="approved" data-testid="tab-approved" className="gap-1.5 text-xs">
            Recently approved
            {buckets.approved.length > 0 && (
              <span className="ml-0.5 rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
                {buckets.approved.length}
              </span>
            )}
          </TabsTrigger>
          <TabsTrigger value="shared" data-testid="tab-shared" className="gap-1.5 text-xs">
            Shared by Oversee
            <span className="ml-0.5 rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
              {buckets.shared.length}
            </span>
          </TabsTrigger>
          <TabsTrigger value="uploaded" data-testid="tab-uploaded" className="gap-1.5 text-xs">
            Uploaded by you
            <span className="ml-0.5 rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
              {buckets.uploaded.length}
            </span>
          </TabsTrigger>
        </TabsList>

        <TabsContent value="needs-you" className="mt-4">
          <Grid
            files={buckets.needsYou}
            onOpen={setOpenFile}
            emptyTitle="Nothing waiting on you"
            emptyMessage="When your team ships new deliverables they'll show up here."
          />
        </TabsContent>
        <TabsContent value="with-staff" className="mt-4">
          <Grid
            files={buckets.withStaff}
            onOpen={setOpenFile}
            emptyTitle="Nothing in staff review"
            emptyMessage="Files you upload that need an Oversee reviewer will show up here."
          />
        </TabsContent>
        <TabsContent value="approved" className="mt-4">
          <Grid
            files={buckets.approved}
            onOpen={setOpenFile}
            emptyTitle="No approvals yet"
            emptyMessage="Approved deliverables will appear here."
          />
        </TabsContent>
        <TabsContent value="shared" className="mt-4">
          <Grid
            files={buckets.shared}
            onOpen={setOpenFile}
            emptyTitle="Nothing shared yet"
            emptyMessage="Files your Oversee team shares will appear here."
          />
        </TabsContent>
        <TabsContent value="uploaded" className="mt-4">
          {buckets.uploaded.length === 0 ? (
            <div className="rounded-xl border border-dashed border-border bg-card px-6 py-14 text-center">
              <CloudUpload className="mx-auto mb-2 size-6 text-muted-foreground" />
              <p className="text-sm font-semibold">You haven't uploaded any files yet</p>
              <p className="mt-1 text-xs text-muted-foreground">
                Click <span className="font-medium text-foreground">Upload files</span> above to share assets with your team.
              </p>
              <Button
                size="sm"
                className="mt-4 gap-1.5"
                onClick={() => setUploadOpen(true)}
                data-testid="button-upload-files-empty"
              >
                <CloudUpload className="size-3.5" /> Upload files
              </Button>
            </div>
          ) : (
            <Grid
              files={buckets.uploaded}
              onOpen={setOpenFile}
              emptyTitle=""
              emptyMessage=""
            />
          )}
        </TabsContent>
      </Tabs>

      <SectionCard
        title="Looking for project files inside boards?"
        hint="Each board on My Work has its own Files tab with everything organised by item."
        testId="files-helper-card"
      >
        <div className="flex items-start gap-3 px-5 py-4">
          <span className="grid size-9 shrink-0 place-items-center rounded-md bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300">
            <Sparkles className="size-4" />
          </span>
          <div className="flex-1 text-xs text-muted-foreground">
            Open any project on{" "}
            <button
              type="button"
              onClick={() => navigate("/client/work")}
              className="font-medium text-primary hover:underline"
              data-testid="link-go-mywork"
            >
              My Work
            </button>{" "}
            to see its dedicated file list.
          </div>
          <FolderOpen className="ml-auto hidden size-4 text-muted-foreground md:block" />
        </div>
      </SectionCard>

      <PreviewApprovalDialog file={openFile} onClose={() => setOpenFile(null)} role="client" />
      <UploadFileDialog open={uploadOpen} onOpenChange={setUploadOpen} />
    </div>
  );
}

function Grid({
  files,
  onOpen,
  emptyTitle,
  emptyMessage,
}: {
  files: FileEntry[];
  onOpen: (f: FileEntry) => void;
  emptyTitle: string;
  emptyMessage: string;
}) {
  if (files.length === 0) {
    return (
      <div className="rounded-xl border border-dashed border-border bg-card px-6 py-14 text-center">
        <p className="text-sm font-semibold">{emptyTitle}</p>
        <p className="mt-1 text-xs text-muted-foreground">{emptyMessage}</p>
      </div>
    );
  }
  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
      {files.map((f) => (
        <PreviewBigCard key={f.id} file={f} role="client" onOpen={() => onOpen(f)} />
      ))}
    </div>
  );
}
