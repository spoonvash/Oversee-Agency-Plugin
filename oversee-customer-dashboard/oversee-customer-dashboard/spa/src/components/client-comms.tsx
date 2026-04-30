// Client communication primitives — ChannelBadge, MessageThread, ClientCommsComposer.
// Used by Admin Clients (Messages tab + header Message/Email buttons).
//
// Production routing (described inline so handoff is unambiguous):
//   • CRM   → OverseeCRM / HighLevel Conversations API for the mapped contact.
//   • Slack → Internal staff-only note pushed to the team channel for the client.
//   • Email → Server-side mailer or local mailto handoff (a draft is recorded).
//
// In preview mode none of these production routes are executed — the composer
// only mutates local store state via addClientMessage so flows can be exercised
// without sending anything outbound.
import { useEffect, useMemo, useRef, useState } from "react";
import {
  AtSign,
  ExternalLink,
  Hash,
  Lock,
  Mail,
  MessageCircle,
  MessageSquare,
  Send,
  Slack,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  type ClientMessage,
  type ClientMessageChannel,
  type ClientRecord,
} from "@/lib/demo-store";
import { shortDateTime } from "@/lib/format";
import { cn } from "@/lib/utils";
import { StatusPill, type StatusTone } from "@/components/shared";

// ─────────────────────────────────────────────────────────────────────────────
// Channel metadata — single source of truth for icons/labels/tones/handoff
// description used by the badge, the composer, and tooltips.
// ─────────────────────────────────────────────────────────────────────────────
type ChannelMeta = {
  label: string;
  /** Short tag visible in pills/inline. */
  shortLabel: string;
  icon: LucideIcon;
  tone: StatusTone;
  /** Where the message goes in production. */
  productionRoute: string;
  /** True when the channel is staff-only by definition. */
  internalByDefault: boolean;
};

export const CHANNEL_META: Record<ClientMessageChannel, ChannelMeta> = {
  crm: {
    label: "OverseeCRM (HighLevel)",
    shortLabel: "CRM",
    icon: MessageCircle,
    tone: "primary",
    productionRoute:
      "Posts to HighLevel Conversations for the mapped contact (SMS/web chat).",
    internalByDefault: false,
  },
  slack: {
    label: "Internal Slack note",
    shortLabel: "Slack",
    icon: Slack,
    tone: "info",
    productionRoute:
      "Sends to the client's internal Slack channel — staff-only, never visible to the client.",
    internalByDefault: true,
  },
  email: {
    label: "Email draft",
    shortLabel: "Email",
    icon: Mail,
    tone: "neutral",
    productionRoute:
      "Creates a draft via the server mailer or a mailto handoff. Nothing is sent until you confirm.",
    internalByDefault: false,
  },
};

// ─────────────────────────────────────────────────────────────────────────────
// ChannelBadge — pill that labels the channel a message used.
// ─────────────────────────────────────────────────────────────────────────────
export function ChannelBadge({
  channel,
  internal,
  className,
  testId,
}: {
  channel: ClientMessageChannel;
  /** When true, append a lock glyph to make the staff-only nature unmistakable. */
  internal?: boolean;
  className?: string;
  testId?: string;
}) {
  const meta = CHANNEL_META[channel];
  const Icon = meta.icon;
  return (
    <StatusPill
      tone={meta.tone}
      uppercase={false}
      className={className}
      description={meta.productionRoute}
      testId={testId ?? `channel-badge-${channel}`}
    >
      <Icon className="size-3" />
      <span className="font-medium">{meta.shortLabel}</span>
      {internal && <Lock className="size-2.5" />}
    </StatusPill>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// MessageThread — chronological feed of ClientMessage entries with
// channel labels, internal/public distinction, and direction styling.
// ─────────────────────────────────────────────────────────────────────────────
export function MessageThread({
  messages,
  emptyHint,
  testId,
}: {
  messages: ClientMessage[];
  emptyHint?: string;
  testId?: string;
}) {
  const ordered = useMemo(
    () =>
      [...messages].sort(
        (a, b) => new Date(a.at).getTime() - new Date(b.at).getTime(),
      ),
    [messages],
  );

  // Auto-scroll the thread to the bottom whenever new messages are appended.
  const bottomRef = useRef<HTMLDivElement | null>(null);
  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth", block: "end" });
  }, [ordered.length]);

  if (ordered.length === 0) {
    return (
      <div
        className="rounded-xl border border-dashed border-border bg-muted/20 p-6 text-center text-xs text-muted-foreground"
        data-testid={testId ?? "message-thread-empty"}
      >
        {emptyHint ??
          "No messages yet. Start a conversation using the composer below."}
      </div>
    );
  }

  return (
    <div className="space-y-3" data-testid={testId ?? "message-thread"}>
      {ordered.map((m) => (
        <MessageBubble key={m.id} message={m} />
      ))}
      <div ref={bottomRef} />
    </div>
  );
}

function MessageBubble({ message }: { message: ClientMessage }) {
  const meta = CHANNEL_META[message.channel];
  const isOut = message.direction === "out";
  return (
    <div
      className={cn("flex flex-col gap-1", isOut ? "items-end" : "items-start")}
      data-testid={`message-${message.id}`}
    >
      <div className="flex items-center gap-1.5 text-[10px] text-muted-foreground">
        <span className="font-medium text-foreground">
          {message.authorName}
        </span>
        <span>·</span>
        <span>{shortDateTime(message.at)}</span>
        <ChannelBadge
          channel={message.channel}
          internal={message.internal}
          className="ml-1"
        />
      </div>
      <div
        className={cn(
          "max-w-[85%] rounded-lg border px-3 py-2 text-sm leading-relaxed",
          isOut
            ? "border-primary/30 bg-primary/10 text-foreground"
            : "border-border bg-card text-foreground",
          message.internal &&
            "border-amber-200 bg-amber-50/50 dark:border-amber-900/40 dark:bg-amber-950/20",
        )}
      >
        {message.channel === "email" && message.email && (
          <p className="mb-1 text-[11px] font-semibold text-muted-foreground">
            <span className="uppercase tracking-wide">Subject:</span>{" "}
            {message.email.subject}
          </p>
        )}
        <p className="whitespace-pre-wrap">{message.body}</p>
      </div>
      {message.internal && (
        <p className="text-[10px] uppercase tracking-wide text-amber-700 dark:text-amber-300">
          Internal — not visible to client
        </p>
      )}
      {!isOut && (
        <p className="text-[10px] text-muted-foreground">
          via {meta.label}
        </p>
      )}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// ClientCommsComposer — three-channel composer used both inline (Messages tab)
// and inside dialogs (header Message/Email buttons).
//
//   • CRM mode    → posts to HighLevel Conversations (preview: state-only).
//   • Slack mode  → internal-only staff note (preview: state-only).
//   • Email mode  → records a draft entry AND opens a mailto: window so the
//     user's email client picks it up. No real send happens.
// ─────────────────────────────────────────────────────────────────────────────
export function ClientCommsComposer({
  client,
  defaultChannel = "crm",
  showChannelTabs = true,
  authorName = "Sasha Patel",
  onSent,
  onSendMessage,
  inputId,
  compact = false,
}: {
  client: ClientRecord;
  defaultChannel?: ClientMessageChannel;
  /** Hide the channel tab strip when the composer is locked to a single channel. */
  showChannelTabs?: boolean;
  authorName?: string;
  /** Callback after a successful send (channel badge already added). */
  onSent?: (channel: ClientMessageChannel) => void;
  /** Required parent-supplied send function — wires to addClientMessage. */
  onSendMessage: (input: {
    channel: ClientMessageChannel;
    body: string;
    internal: boolean;
    email?: { subject: string; to: string };
  }) => void;
  /** Optional explicit id for the textarea — used for label association. */
  inputId?: string;
  /** Compact mode shrinks padding for narrow rails. */
  compact?: boolean;
}) {
  const [channel, setChannel] = useState<ClientMessageChannel>(defaultChannel);
  const [body, setBody] = useState("");
  const [emailSubject, setEmailSubject] = useState(
    `Re: ${client.company || client.name}`,
  );

  const meta = CHANNEL_META[channel];
  const canSubmit = body.trim().length > 0;
  const isInternal = meta.internalByDefault;

  const send = () => {
    if (!canSubmit) return;
    if (channel === "email") {
      // Open a mailto: handoff so the staff member's email client picks it up.
      // We still record the draft locally so the thread shows the outbound.
      const mailto =
        `mailto:${encodeURIComponent(client.email)}` +
        `?subject=${encodeURIComponent(emailSubject)}` +
        `&body=${encodeURIComponent(body)}`;
      // Use window.location to avoid popup blockers.
      window.location.href = mailto;
      onSendMessage({
        channel,
        body: body.trim(),
        internal: false,
        email: { subject: emailSubject.trim(), to: client.email },
      });
    } else {
      onSendMessage({
        channel,
        body: body.trim(),
        internal: isInternal,
      });
    }
    setBody("");
    onSent?.(channel);
  };

  const textareaId = inputId ?? "client-comms-body";

  return (
    <div
      className={cn(
        "flex flex-col gap-3 rounded-xl border border-border bg-card",
        compact ? "p-3" : "p-4",
      )}
      data-testid="client-comms-composer"
    >
      {showChannelTabs && (
        <Tabs
          value={channel}
          onValueChange={(v) => setChannel(v as ClientMessageChannel)}
        >
          <TabsList className="h-9 w-full justify-start gap-1 bg-muted/40 p-0.5">
            <ChannelTab value="crm" channel="crm" />
            <ChannelTab value="slack" channel="slack" />
            <ChannelTab value="email" channel="email" />
          </TabsList>
        </Tabs>
      )}

      {/* Channel context strip */}
      <div className="flex flex-wrap items-center gap-2 text-[11px] text-muted-foreground">
        <ChannelBadge channel={channel} internal={isInternal} />
        <span className="truncate">
          {channel === "slack"
            ? `Internal team — staff-only note about ${client.name}.`
            : channel === "email"
              ? `Drafts an email to ${client.email} (mailto handoff).`
              : `Client-facing message to ${client.name} via OverseeCRM (HighLevel).`}
        </span>
      </div>

      {channel === "email" && (
        <div className="space-y-1.5">
          <Label htmlFor="client-comms-email-subject" className="text-[11px]">
            Subject
          </Label>
          <Input
            id="client-comms-email-subject"
            value={emailSubject}
            onChange={(e) => setEmailSubject(e.target.value)}
            placeholder="Subject"
            className="h-9 text-sm"
            data-testid="input-email-subject"
          />
          <p className="flex items-center gap-1 text-[10px] text-muted-foreground">
            <AtSign className="size-3" />
            To: {client.email}
          </p>
        </div>
      )}

      <div className="space-y-1.5">
        <Label htmlFor={textareaId} className="text-[11px]">
          Message
        </Label>
        <Textarea
          id={textareaId}
          value={body}
          onChange={(e) => setBody(e.target.value)}
          placeholder={
            channel === "slack"
              ? `Add an internal note for the team about ${client.name}…`
              : channel === "email"
                ? `Write the email body…`
                : `Message ${client.name.split(" ")[0]} via OverseeCRM…`
          }
          rows={compact ? 3 : 4}
          className="resize-none text-sm"
          data-testid="textarea-comms-body"
        />
      </div>

      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="flex items-center gap-1 text-[10px] text-muted-foreground">
          <ExternalLink className="size-3" />
          Production: {meta.productionRoute}
        </p>
        <Button
          size="sm"
          className="gap-1.5"
          disabled={!canSubmit}
          onClick={send}
          data-testid={`button-send-${channel}`}
        >
          {channel === "email" ? (
            <Mail className="size-3.5" />
          ) : channel === "slack" ? (
            <Hash className="size-3.5" />
          ) : (
            <Send className="size-3.5" />
          )}
          {channel === "email"
            ? "Open draft & log"
            : channel === "slack"
              ? "Post internal note"
              : "Send via CRM"}
        </Button>
      </div>
    </div>
  );
}

function ChannelTab({
  value,
  channel,
}: {
  value: ClientMessageChannel;
  channel: ClientMessageChannel;
}) {
  const meta = CHANNEL_META[channel];
  const Icon = meta.icon;
  return (
    <TabsTrigger
      value={value}
      className="h-8 gap-1.5 px-2.5 text-xs"
      data-testid={`tab-channel-${channel}`}
    >
      <Icon className="size-3.5" />
      {meta.shortLabel}
    </TabsTrigger>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// MessageFilterTabs — small filter strip for thread views
//   (all | client-facing | internal | email).
// ─────────────────────────────────────────────────────────────────────────────
export type MessageFilter = "all" | "crm" | "slack" | "email" | "internal";

export function MessageFilterTabs({
  value,
  onChange,
  counts,
}: {
  value: MessageFilter;
  onChange: (v: MessageFilter) => void;
  counts: Record<MessageFilter, number>;
}) {
  const opts: { v: MessageFilter; label: string; icon: LucideIcon }[] = [
    { v: "all", label: "All", icon: MessageSquare },
    { v: "crm", label: "Client (CRM)", icon: MessageCircle },
    { v: "email", label: "Email", icon: Mail },
    { v: "internal", label: "Internal", icon: Lock },
  ];
  return (
    <Tabs value={value} onValueChange={(v) => onChange(v as MessageFilter)}>
      <TabsList className="h-9 flex-wrap gap-1 bg-muted/40 p-0.5">
        {opts.map((o) => {
          const Icon = o.icon;
          return (
            <TabsTrigger
              key={o.v}
              value={o.v}
              className="h-8 gap-1.5 px-2.5 text-xs"
              data-testid={`filter-${o.v}`}
            >
              <Icon className="size-3" />
              {o.label}
              <span className="ml-0.5 text-[10px] text-muted-foreground">
                ({counts[o.v]})
              </span>
            </TabsTrigger>
          );
        })}
      </TabsList>
    </Tabs>
  );
}
