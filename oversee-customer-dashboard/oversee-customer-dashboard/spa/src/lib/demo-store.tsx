// In-memory demo state for the Oversee dashboard preview.
// No localStorage / sessionStorage / cookies. Pure React context.

import {
  createContext,
  useCallback,
  useContext,
  useMemo,
  useState,
  type PropsWithChildren,
} from "react";

// ============== Types ==============

export type ID = string;

export type BoardView = "task-list" | "kanban" | "roadmap" | "calendar" | "files";

export type ItemStatus =
  | "not-started"
  | "in-progress"
  | "client-input"
  | "review"
  | "done"
  | "stuck";

export type ItemPriority = "low" | "medium" | "high";

export type Reaction = { emoji: string; count: number; reacted?: boolean };

export type Attachment = {
  id: ID;
  kind: "image" | "loom" | "file";
  src: string;
  caption?: string;
  loomUrl?: string;
  durationLabel?: string;
};

export type DiscussionPost = {
  id: ID;
  authorName: string;
  authorRole: "client" | "admin" | "system";
  body: string;
  postedAt: string;
  internalOnly?: boolean;
  attachments?: Attachment[];
  reactions?: Reaction[];
  replies?: DiscussionPost[];
  pinned?: boolean;
  seenBy?: string[]; // names
  checklist?: { id: ID; label: string; done: boolean }[];
  systemKind?: "status-change" | "approval" | "created";
};

export type SubItem = {
  id: ID;
  title: string;
  done: boolean;
  assignee?: string;
};

export type ActivityEntry = {
  id: ID;
  at: string;
  authorName: string;
  text: string;
};

export type BoardItem = {
  id: ID;
  boardId: ID;
  title: string;
  description?: string;
  status: ItemStatus;
  priority: ItemPriority;
  due?: string;
  assignee: string;
  workflowStage: string;
  tags: string[];
  subitems: SubItem[];
  discussion: DiscussionPost[];
  deliverables: Attachment[];
  activity: ActivityEntry[];
};

export type Board = {
  id: ID;
  name: string;
  clientEmail: string;
  description: string;
  workflow: string[]; // stage names in order, used as both Status labels AND group/section names
  progress: number; // 0-1 (now derived if items exist)
  unreadUpdates: number;
  pendingClientInput: number;
  groups?: { id: ID; name: string; collapsed?: boolean }[]; // optional group definitions
  // New optional admin metadata
  serviceType?: string;
  status?: "active" | "on-hold" | "completed" | "archived";
  due?: string; // ISO date — overall project due date (admin-editable)
  owner?: string; // assigned owner name
  slackChannel?: string; // optional channel mapping (preview-only, e.g. "#brand-northstar")
};

export type FeedUpdate = {
  id: ID;
  boardId: ID;
  itemId?: ID;
  authorName: string;
  authorRole: "client" | "admin";
  body: string;
  postedAt: string;
  unread: boolean;
  mentioned: boolean;
  bookmarked: boolean;
  internalOnly: boolean;
  attachment?: Attachment;
};

export type FileEntry = {
  id: ID;
  name: string;
  kind: "image" | "doc" | "pdf" | "video";
  src?: string;
  size: string;
  uploadedAt: string;
  uploadedBy: string;
  boardName?: string;
  approval: "pending" | "approved" | "changes-requested" | "n/a";
  version: number;
};

export type Service = {
  id: ID;
  sku: string;
  title: string;
  blurb: string;
  price: number;
  cadence: "one-time" | "monthly" | "quarterly";
  category: "SEO" | "Content" | "Design" | "Web" | "Ads";
  templateId?: ID;
  badge?: string;
};

// ----- Real Oversee WooCommerce product catalog (subset, source-of-truth shape)
export type WooProductType = "simple" | "variable" | "subscription" | "variable-subscription";

export type WooAttribute = {
  name: string; // e.g. "Length of Commercial"
  options: string[]; // e.g. ["30 Seconds", "60 Seconds", "90 Seconds"]
};

export type WooVariation = {
  id: ID;
  attrs: Record<string, string>; // attribute name -> selected option
  price: number;
  // Optional variation-specific imagery. Woo allows each variation to carry
  // its own image; when set we prefer this over the parent product image
  // wherever the variation is the active selection (detail sheet, cart line,
  // checkout summary).
  image?: string;
  imageAlt?: string;
  // for subscriptions: amount is per-period; period determined by product
};

export type WooProduct = {
  id: ID;
  wooId: number;
  slug: string;
  name: string;
  type: WooProductType;
  category: string; // primary category name
  shortDescription: string;
  // Primary product image. Mirrors WooCommerce Store API
  // `product.images[0].src`. Empty/undefined means the product genuinely has
  // no image; the visual layer falls back to a category-tinted initials panel
  // (NOT a stock placeholder).
  imageUrl?: string;
  imageAlt?: string;
  industries: string[];
  // pricing
  price?: number; // simple / subscription single price
  priceMin?: number; // variable products
  priceMax?: number;
  period?: "month" | "one-time"; // subscription period
  termMonths?: number; // for fixed-term subscriptions (e.g. 6 months)
  // attributes / variations
  attributes?: WooAttribute[];
  variations?: WooVariation[];
  templateId?: ID;
  badge?: string;
};

export type CartLine = {
  id: ID; // cart line id (unique per add)
  productId: ID;
  variationId?: ID;
  selectedAttrs?: Record<string, string>;
  unitPrice: number;
  qty: number;
  period?: "month" | "one-time";
  termMonths?: number;
};

export type Subscription = {
  id: ID;
  serviceId: ID;
  status: "active" | "paused" | "past-due" | "cancelled";
  renewsOn: string;
  amount: number;
};

export type Invoice = {
  id: ID;
  number: string;
  amount: number;
  status: "paid" | "open" | "past-due";
  date: string;
  service: string;
};

export type Order = {
  id: ID;
  number: string;
  customer: string;
  service: string;
  total: number;
  status: "processing" | "completed" | "on-hold" | "refunded";
  date: string;
};

export type ServiceTemplate = {
  id: ID;
  name: string;
  category: string;
  workflow: string[];
  defaultItems: number;
  serviceId: ID;
};

export type IntakeForm = {
  id: ID;
  name: string;
  fields: number;
  responses: number;
  attachedTo: string;
};

export type CalendarEvent = {
  id: ID;
  title: string;
  date: string;
  time: string;
  type: "call" | "deadline" | "review";
  client: string;
};

export type Automation = {
  id: ID;
  name: string;
  trigger: string;
  conditions: string[];
  actions: string[];
  active: boolean;
  runs: number;
};

export type TeamMember = {
  id: ID;
  name: string;
  email: string;
  role: "Owner" | "Admin" | "PM" | "Designer" | "Strategist";
  avatarColor: string;
};

export type IntegrationStatus = "connected" | "needs-attention" | "disconnected";

export type Integration = {
  id: string;
  name: string;
  status: IntegrationStatus;
  detail: string;
};

export type ClientRecord = {
  id: ID;
  name: string;
  company: string;
  email: string;
  phone: string;
  status: "active" | "prospect" | "churned" | "paused";
  lifecycleStage: "discovery" | "onboarding" | "delivery" | "renewal" | "alumni";
  lifetimeValue: number;
  mrr: number;
  pm: string;
  tags: string[];
  customFields: { label: string; value: string }[];
  internalNotes: { id: ID; author: string; at: string; body: string }[];
  joined: string;
  lastTouch: string;
  avatarColor: string;
};

// Cross-channel client communication record. Drives the Admin Clients
// Messages tab and the right-rail composer. Each channel maps to a real
// production system (HighLevel/OverseeCRM, Slack, email) but in preview
// mode we only mutate local state — see ClientCommsComposer.
export type ClientMessageChannel = "crm" | "slack" | "email";

export type ClientMessage = {
  id: ID;
  clientId: ID;
  channel: ClientMessageChannel;
  /** "out" = staff sent to client; "in" = client sent to staff. */
  direction: "in" | "out";
  authorName: string;
  body: string;
  at: string;
  /**
   * Internal-only flag. Slack notes are always internal. CRM/Email may also be
   * marked internal for staff annotations but default to client-facing.
   */
  internal: boolean;
  /** Email-only metadata for draft/handoff context. */
  email?: { subject: string; to: string };
};

export type ContractRecord = {
  id: ID;
  name: string;
  status: "draft" | "sent" | "signed" | "expired";
  client: string;
  amount?: number;
  sentAt?: string;
  signedAt?: string;
  pages: number;
};

export type FormRecord = {
  id: ID;
  name: string;
  description: string;
  status: "open" | "submitted" | "draft";
  due?: string;
  fields: { id: ID; label: string; kind: "text" | "textarea" | "select" | "checkbox" | "file"; options?: string[]; required?: boolean }[];
};

export type Payment = {
  id: ID;
  amount: number;
  customer: string;
  service: string;
  method: "Stripe · Visa" | "Stripe · MC" | "Stripe · Amex" | "PayPal" | "Bank";
  status: "succeeded" | "refunded" | "failed";
  date: string;
};

// =========== Leadsie onboarding (preview) ===========
// Leadsie is the access-request layer. Clients sign in to Google/Meta/etc.
// and approve permissions; the agency gets access without sharing passwords.
// In preview we model the *shape* of that flow without making real network calls.

export type LeadsiePlatform =
  | "google-analytics"
  | "google-search-console"
  | "google-business-profile"
  | "google-tag-manager"
  | "google-ads"
  | "google-merchant-center"
  | "meta-business"
  | "meta-page"
  | "meta-ads"
  | "meta-pixel"
  | "meta-catalog"
  | "instagram"
  | "linkedin"
  | "tiktok"
  | "shopify"
  | "wordpress"
  | "klaviyo"
  | "mailchimp"
  | "highlevel";

export type LeadsieRequestStatus =
  | "not-started"
  | "link-sent"
  | "client-opened"
  | "approved"
  | "blocked";

export type OnboardingStepKind =
  | "leadsie-access"
  | "intake-form"
  | "upload"
  | "schedule"
  | "approve";

export type OnboardingStep = {
  id: ID;
  kind: OnboardingStepKind;
  title: string;
  why: string;
  // Required for kind === "leadsie-access"
  platform?: LeadsiePlatform;
  permissions?: string[];
  // For preview, every Leadsie request has a fake URL.
  requestUrl?: string;
  status: "not-started" | "in-progress" | "complete" | "blocked";
  leadsieStatus?: LeadsieRequestStatus;
  due?: string;
  optional?: boolean;
};

export type OnboardingPlan = {
  // Each plan is tied to a service the client purchased.
  serviceId: ID;
  // Optional override label. Defaults to the service title.
  label?: string;
  steps: OnboardingStep[];
};

export type LeadsieSettings = {
  connected: boolean; // user does not yet have a Leadsie account
  agencySlug: string; // agency-level URL slug, e.g. "oversee"
  embedSnippet: string; // pasted from Leadsie dashboard in production
  webhookUrl: string; // status sync placeholder
  defaultTemplates: { platform: LeadsiePlatform; label: string }[];
};

// ============== Seed data ==============

const NOW = new Date("2025-04-15T10:00:00Z");
const isoFromNow = (daysOffset: number, hour = 10) => {
  const d = new Date(NOW);
  d.setDate(d.getDate() + daysOffset);
  d.setHours(hour, 0, 0, 0);
  return d.toISOString();
};

const CUSTOMER = {
  name: "Maya Lin",
  email: "maya@northstargallery.com",
  company: "Northstar Gallery",
};

const TEAM: TeamMember[] = [
  { id: "u1", name: "Sasha Patel", email: "sasha@oversee.agency", role: "PM", avatarColor: "bg-orange-500" },
  { id: "u2", name: "Devon Ortiz", email: "devon@oversee.agency", role: "Strategist", avatarColor: "bg-amber-500" },
  { id: "u3", name: "Lena Howe", email: "lena@oversee.agency", role: "Designer", avatarColor: "bg-rose-500" },
  { id: "u4", name: "Marcus Reed", email: "marcus@oversee.agency", role: "Admin", avatarColor: "bg-emerald-600" },
  { id: "u5", name: "Priya Shah", email: "priya@oversee.agency", role: "Owner", avatarColor: "bg-indigo-500" },
];

const SAMPLE_IMG = (id: number) =>
  ({
    1: "https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=1200&q=80&auto=format&fit=crop",
    2: "https://images.unsplash.com/photo-1559028012-481c04fa702d?w=1200&q=80&auto=format&fit=crop",
    3: "https://images.unsplash.com/photo-1542435503-956c469947f6?w=1200&q=80&auto=format&fit=crop",
    4: "https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=1200&q=80&auto=format&fit=crop",
    5: "https://images.unsplash.com/photo-1481487196290-c152efe083f5?w=1200&q=80&auto=format&fit=crop",
    6: "https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=1200&q=80&auto=format&fit=crop",
  }[id] || "");

const WORKFLOW_DEFAULT = ["Backlog", "Working On It", "Waiting on Client", "In Review", "Approved", "Stuck"];
const WORKFLOW_SEO = ["Backlog", "Working On It", "Waiting on Client", "In Review", "Approved", "Stuck"];

const BOARDS: Board[] = [
  {
    id: "b1",
    name: "Brand Refresh — Northstar",
    clientEmail: CUSTOMER.email,
    description: "End-to-end brand identity refresh: logo, type, web, gallery collateral.",
    workflow: WORKFLOW_DEFAULT,
    progress: 0.62,
    unreadUpdates: 4,
    pendingClientInput: 2,
    serviceType: "Brand identity",
    status: "active",
    owner: "Lena Howe",
    due: isoFromNow(21),
    slackChannel: "#brand-northstar",
  },
  {
    id: "b2",
    name: "Q2 SEO & Content",
    clientEmail: CUSTOMER.email,
    description: "Editorial calendar, technical SEO sweep, and 6 long-form articles.",
    workflow: WORKFLOW_SEO,
    progress: 0.34,
    unreadUpdates: 1,
    pendingClientInput: 1,
    serviceType: "SEO retainer",
    status: "active",
    owner: "Devon Ortiz",
    due: isoFromNow(45),
  },
  {
    id: "b3",
    name: "Spring Exhibition Microsite",
    clientEmail: CUSTOMER.email,
    description: "Single-page microsite for the May exhibition opening.",
    workflow: WORKFLOW_DEFAULT,
    progress: 0.18,
    unreadUpdates: 0,
    pendingClientInput: 0,
    serviceType: "Web build",
    status: "active",
    owner: "Sasha Patel",
    due: isoFromNow(14),
  },
];

const ITEMS: BoardItem[] = [
  {
    id: "i1",
    boardId: "b1",
    title: "Logo concept exploration — round 2",
    description: "Three refined directions based on last week's feedback.",
    status: "review",
    priority: "high",
    due: isoFromNow(2),
    assignee: "Lena Howe",
    workflowStage: "In Review",
    tags: ["design", "brand"],
    subitems: [
      { id: "s1", title: "Concept A — geometric mark", done: true, assignee: "Lena Howe" },
      { id: "s2", title: "Concept B — wordmark with monogram", done: true, assignee: "Lena Howe" },
      { id: "s3", title: "Concept C — abstract motif", done: false, assignee: "Lena Howe" },
    ],
    discussion: [
      {
        id: "d1",
        authorName: "Lena Howe",
        authorRole: "admin",
        body: "Three concepts ready for your review. I think Concept B threads the needle on warmth and authority. Loom walkthrough below.",
        postedAt: isoFromNow(-1, 14),
        attachments: [
          { id: "a1", kind: "loom", src: SAMPLE_IMG(2), loomUrl: "https://loom.com/share/example-1", caption: "Walkthrough", durationLabel: "4:12" },
          { id: "a2", kind: "image", src: SAMPLE_IMG(3), caption: "Concept board" },
        ],
        reactions: [{ emoji: "🔥", count: 2, reacted: true }, { emoji: "👀", count: 1 }],
      },
      {
        id: "d2",
        authorName: "Maya Lin",
        authorRole: "client",
        body: "Loving B. Can we see B with the warmer orange from the moodboard?",
        postedAt: isoFromNow(0, 9),
        reactions: [{ emoji: "👍", count: 1 }],
      },
      {
        id: "d3",
        authorName: "Sasha Patel",
        authorRole: "admin",
        body: "Internal: Lena, push the warmth on B and prep a tertiary fallback before EOD Thursday so we don't slip the brand book.",
        postedAt: isoFromNow(0, 10),
        internalOnly: true,
      },
    ],
    deliverables: [
      { id: "del1", kind: "image", src: SAMPLE_IMG(3), caption: "concept-b-v2.png" },
      { id: "del2", kind: "file", src: "#", caption: "brand-board-v2.pdf" },
    ],
    activity: [
      { id: "act1", at: isoFromNow(-3, 11), authorName: "Lena Howe", text: "moved item from Backlog → In Progress" },
      { id: "act2", at: isoFromNow(-1, 14), authorName: "Lena Howe", text: "moved item to Review and posted 3 deliverables" },
      { id: "act3", at: isoFromNow(0, 9), authorName: "Maya Lin", text: "left feedback on Concept B" },
    ],
  },
  {
    id: "i2",
    boardId: "b1",
    title: "Type system & vertical scale",
    status: "in-progress",
    priority: "medium",
    due: isoFromNow(5),
    assignee: "Lena Howe",
    workflowStage: "Working On It",
    tags: ["design", "typography"],
    subitems: [
      { id: "s4", title: "Heading scale", done: true },
      { id: "s5", title: "Body & UI scale", done: false },
      { id: "s6", title: "Specimen sheet", done: false },
    ],
    discussion: [],
    deliverables: [],
    activity: [],
  },
  {
    id: "i3",
    boardId: "b1",
    title: "Approve final wordmark direction",
    status: "client-input",
    priority: "high",
    due: isoFromNow(1),
    assignee: "Maya Lin",
    workflowStage: "Waiting on Client",
    tags: ["approval"],
    subitems: [],
    discussion: [],
    deliverables: [],
    activity: [],
  },
  {
    id: "i4",
    boardId: "b1",
    title: "Brand guidelines doc — outline",
    status: "not-started",
    priority: "low",
    due: isoFromNow(12),
    assignee: "Sasha Patel",
    workflowStage: "Backlog",
    tags: ["docs"],
    subitems: [],
    discussion: [],
    deliverables: [],
    activity: [],
  },
  {
    id: "i5",
    boardId: "b1",
    title: "Stationery print-ready files",
    status: "done",
    priority: "medium",
    assignee: "Lena Howe",
    workflowStage: "Approved",
    tags: ["print"],
    subitems: [],
    discussion: [],
    deliverables: [{ id: "del3", kind: "file", src: "#", caption: "stationery-print.zip" }],
    activity: [],
  },
  {
    id: "i6",
    boardId: "b2",
    title: "Topic cluster — 'art collecting basics'",
    status: "in-progress",
    priority: "high",
    due: isoFromNow(4),
    assignee: "Devon Ortiz",
    workflowStage: "Working On It",
    tags: ["seo", "content"],
    subitems: [
      { id: "s7", title: "Pillar outline", done: true },
      { id: "s8", title: "Internal link map", done: false },
    ],
    discussion: [],
    deliverables: [],
    activity: [],
  },
  {
    id: "i7",
    boardId: "b2",
    title: "Approve March keyword targets",
    status: "client-input",
    priority: "medium",
    due: isoFromNow(0),
    assignee: "Maya Lin",
    workflowStage: "Waiting on Client",
    tags: ["approval", "seo"],
    subitems: [],
    discussion: [],
    deliverables: [],
    activity: [],
  },
  {
    id: "i8",
    boardId: "b2",
    title: "Technical SEO audit report",
    status: "done",
    priority: "medium",
    assignee: "Devon Ortiz",
    workflowStage: "Approved",
    tags: ["audit"],
    subitems: [],
    discussion: [],
    deliverables: [{ id: "del4", kind: "file", src: "#", caption: "tech-audit-q2.pdf" }],
    activity: [],
  },
  {
    id: "i9",
    boardId: "b3",
    title: "Microsite wireframe v1",
    status: "in-progress",
    priority: "high",
    due: isoFromNow(3),
    assignee: "Lena Howe",
    workflowStage: "Working On It",
    tags: ["web", "design"],
    subitems: [],
    discussion: [],
    deliverables: [],
    activity: [],
  },
  {
    id: "i10",
    boardId: "b3",
    title: "Confirm exhibition copy & artist list",
    status: "client-input",
    priority: "medium",
    due: isoFromNow(2),
    assignee: "Maya Lin",
    workflowStage: "Waiting on Client",
    tags: ["copy"],
    subitems: [],
    discussion: [],
    deliverables: [],
    activity: [],
  },
];

const FEED_UPDATES: FeedUpdate[] = [
  {
    id: "u1",
    boardId: "b1",
    itemId: "i1",
    authorName: "Lena Howe",
    authorRole: "admin",
    body: "Posted Round 2 logo concepts. Loom walkthrough included — ~4 min.",
    postedAt: isoFromNow(-1, 14),
    unread: true,
    mentioned: true,
    bookmarked: false,
    internalOnly: false,
    attachment: { id: "ua1", kind: "image", src: SAMPLE_IMG(3), caption: "Concept board snapshot" },
  },
  {
    id: "u2",
    boardId: "b1",
    itemId: "i3",
    authorName: "Sasha Patel",
    authorRole: "admin",
    body: "@Maya — we need wordmark sign-off by Wednesday to keep print on track.",
    postedAt: isoFromNow(0, 9),
    unread: true,
    mentioned: true,
    bookmarked: true,
    internalOnly: false,
  },
  {
    id: "u3",
    boardId: "b2",
    itemId: "i7",
    authorName: "Devon Ortiz",
    authorRole: "admin",
    body: "March keyword set is ready for your review — 18 primary, 24 secondary.",
    postedAt: isoFromNow(0, 11),
    unread: true,
    mentioned: false,
    bookmarked: false,
    internalOnly: false,
  },
  {
    id: "u4",
    boardId: "b1",
    itemId: "i1",
    authorName: "Maya Lin",
    authorRole: "client",
    body: "Loving direction B — can we warm the orange a touch?",
    postedAt: isoFromNow(0, 9),
    unread: false,
    mentioned: false,
    bookmarked: false,
    internalOnly: false,
  },
  {
    id: "u5",
    boardId: "b2",
    authorName: "Devon Ortiz",
    authorRole: "admin",
    body: "Internal: paused 'collector profiles' draft until Maya signs off on tone guidelines.",
    postedAt: isoFromNow(-2, 16),
    unread: false,
    mentioned: false,
    bookmarked: false,
    internalOnly: true,
  },
  {
    id: "u6",
    boardId: "b3",
    authorName: "Lena Howe",
    authorRole: "admin",
    body: "Microsite wireframes are 70% done. Pulling color from the new brand direction.",
    postedAt: isoFromNow(-1, 10),
    unread: false,
    mentioned: false,
    bookmarked: true,
    internalOnly: false,
  },
];

const FILES: FileEntry[] = [
  { id: "f1", name: "concept-b-v2.png", kind: "image", src: SAMPLE_IMG(3), size: "2.1 MB", uploadedAt: isoFromNow(-1, 14), uploadedBy: "Lena Howe", boardName: "Brand Refresh — Northstar", approval: "pending", version: 2 },
  { id: "f2", name: "brand-moodboard.png", kind: "image", src: SAMPLE_IMG(5), size: "3.4 MB", uploadedAt: isoFromNow(-7, 11), uploadedBy: "Lena Howe", boardName: "Brand Refresh — Northstar", approval: "approved", version: 1 },
  { id: "f3", name: "wireframe-v1.png", kind: "image", src: SAMPLE_IMG(2), size: "1.7 MB", uploadedAt: isoFromNow(-2, 9), uploadedBy: "Lena Howe", boardName: "Spring Exhibition Microsite", approval: "pending", version: 1 },
  { id: "f4", name: "tech-audit-q2.pdf", kind: "pdf", size: "892 KB", uploadedAt: isoFromNow(-4, 15), uploadedBy: "Devon Ortiz", boardName: "Q2 SEO & Content", approval: "approved", version: 1 },
  { id: "f5", name: "topic-cluster.png", kind: "image", src: SAMPLE_IMG(4), size: "1.1 MB", uploadedAt: isoFromNow(-3, 10), uploadedBy: "Devon Ortiz", boardName: "Q2 SEO & Content", approval: "approved", version: 1 },
  { id: "f6", name: "gallery-photo-ref.jpg", kind: "image", src: SAMPLE_IMG(6), size: "4.2 MB", uploadedAt: isoFromNow(-5, 12), uploadedBy: "Maya Lin", boardName: "Brand Refresh — Northstar", approval: "n/a", version: 1 },
  { id: "f7", name: "exhibition-brief.pdf", kind: "pdf", size: "640 KB", uploadedAt: isoFromNow(-9, 13), uploadedBy: "Maya Lin", boardName: "Spring Exhibition Microsite", approval: "approved", version: 1 },
  { id: "f8", name: "stationery-print.zip", kind: "doc", size: "12.4 MB", uploadedAt: isoFromNow(-2, 16), uploadedBy: "Lena Howe", boardName: "Brand Refresh — Northstar", approval: "approved", version: 1 },
];

// Image base — real Oversee WooCommerce product images served from
// overseeagency.com's wp-content CDN. The slugs below come from the live
// Woo product feed (Store API exposes them as `images[0].src` per product).
// They are intentionally hard-coded here in the demo store because the demo
// runs without a real Woo backend; in production the same `imageUrl` field
// would be hydrated from the Woo Store API response.
const OS_IMG = (slug: string) => `https://overseeagency.com/wp-content/uploads/${slug}`;
// Fallback used ONLY when a Woo product genuinely has no image. Visual layer
// renders a category-tinted initials panel instead of a stock photo, so the
// fallback URL stays empty here. Anything truthy would defeat the fallback
// path inside <ServiceVisual />.
const NO_WOO_IMAGE: undefined = undefined;

const INDUSTRIES_DEFAULT = [
  "Automotive",
  "Construction & Home",
  "E-commerce & Retail",
  "Education",
  "Financial Services",
  "Healthcare",
  "Hospitality & Tourism",
  "Legal Services",
  "Real Estate",
  "Technology & Software",
];

// Generate variations from attribute combinations, tier-priced linearly.
const combos = (attrs: WooAttribute[]): Record<string, string>[] => {
  if (attrs.length === 0) return [{}];
  const [first, ...rest] = attrs;
  const subs = combos(rest);
  const out: Record<string, string>[] = [];
  for (const opt of first.options) {
    for (const s of subs) out.push({ [first.name]: opt, ...s });
  }
  return out;
};

// Helper: build variations with a price function.
const buildVariations = (
  prefix: string,
  attrs: WooAttribute[],
  priceFn: (selected: Record<string, string>) => number,
): WooVariation[] =>
  combos(attrs).map((selected, i) => ({
    id: `${prefix}-v${i + 1}`,
    attrs: selected,
    price: priceFn(selected),
  }));

const osProductsRaw: Omit<WooProduct, "priceMin" | "priceMax" | "price">[] = [];

const OS_PRODUCTS: WooProduct[] = (() => {
  // Video Commercial Advertisement — variable, $5k-$13k, length attribute.
  const videoLen: WooAttribute = { name: "Length of Commercial", options: ["30 Seconds", "60 Seconds", "90 Seconds"] };
  const videoVars = buildVariations("vca", [videoLen], (s) => {
    const len = s["Length of Commercial"];
    return len === "30 Seconds" ? 5000 : len === "60 Seconds" ? 9000 : 13000;
  });

  // Social Media Management — variable-subscription, static + video posts, base $250 + adders.
  const smm: WooAttribute[] = [
    { name: "Static Posts per Month", options: ["0", "6", "10", "15", "20", "30"] },
    { name: "Video Posts per Month", options: ["0", "1", "2", "3", "5"] },
  ];
  const smmVars = buildVariations("smm", smm, (s) => {
    const stat = parseInt(s["Static Posts per Month"], 10);
    const vid = parseInt(s["Video Posts per Month"], 10);
    return 250 + stat * 30 + vid * 150;
  });

  // WP Maintenance — variable-subscription, $150 base + $80/site
  const wpMaint: WooAttribute = {
    name: "Select Number of Websites",
    options: ["1", "2", "3", "4", "5", "6", "7", "8", "9", "10"],
  };
  const wpMaintVars = buildVariations("wpm", [wpMaint], (s) => {
    const n = parseInt(s["Select Number of Websites"], 10);
    return 150 + (n - 1) * 80;
  });

  // Managed WP Hosting — $250 base + $50/site
  const hosting: WooAttribute = {
    name: "Select Number of Websites",
    options: ["1", "2", "3", "4", "5", "6", "7", "8", "9", "10", "15", "20", "30"],
  };
  const hostingVars = buildVariations("host", [hosting], (s) => {
    const n = parseInt(s["Select Number of Websites"], 10);
    return 250 + (n - 1) * 50;
  });

  // WP E-commerce Website — pages + product pages, $250/mo for 8 months base.
  const ecom: WooAttribute[] = [
    { name: "Select Number of Webpages", options: ["1", "5", "10", "20"] },
    { name: "Select Number of Product Pages", options: ["10", "50", "100", "200", "500"] },
  ];
  const ecomVars = buildVariations("ecom", ecom, (s) => {
    const pages = parseInt(s["Select Number of Webpages"], 10);
    const products = parseInt(s["Select Number of Product Pages"], 10);
    return 250 + pages * 25 + products * 1.5;
  });

  // WP Website Development — pages, $250/mo for 6 months base.
  const dev: WooAttribute = {
    name: "Select Number of Webpages",
    options: ["1", "2", "3", "4", "5", "6", "7", "8", "9", "10", "15", "20", "30"],
  };
  const devVars = buildVariations("dev", [dev], (s) => {
    const n = parseInt(s["Select Number of Webpages"], 10);
    return 250 + (n - 1) * 30;
  });

  // SEO — $200 base + adders.
  const seo: WooAttribute[] = [
    { name: "On-Page Optimized Pages", options: ["0", "5", "10"] },
    { name: "Blog Posts or Pages", options: ["0", "1", "2", "3"] },
    { name: "Backlinks (DA 30-50+)", options: ["0", "10", "20", "50"] },
  ];
  const seoVars = buildVariations("seo", seo, (s) => {
    const pages = parseInt(s["On-Page Optimized Pages"], 10);
    const posts = parseInt(s["Blog Posts or Pages"], 10);
    const links = parseInt(s["Backlinks (DA 30-50+)"], 10);
    return 200 + pages * 35 + posts * 120 + links * 20;
  });

  const minMax = (vs: WooVariation[]) => ({
    priceMin: Math.min(...vs.map((v) => v.price)),
    priceMax: Math.max(...vs.map((v) => v.price)),
  });

  const products: WooProduct[] = [
    {
      id: "p15858", wooId: 15858, slug: "video-commercial-advertisement",
      name: "Video Commercial Advertisement", type: "variable",
      category: "Video Production",
      shortDescription: "Full-production commercial — concept, script, shoot, edit. Pick your length.",
      imageUrl: OS_IMG("2025/01/Oversee-Agency-Video-Commercail-Advertisiement-Commercial-Package-Product-Image.webp"),
      imageAlt: "Oversee Agency video commercial advertisement package",
      industries: INDUSTRIES_DEFAULT,
      attributes: [videoLen], variations: videoVars,
      ...minMax(videoVars),
      badge: "Variable",
      templateId: "tpl4",
    },
    {
      id: "p14341", wooId: 14341, slug: "testimonial-video",
      name: "Testimonial Video", type: "simple",
      category: "Video Production",
      shortDescription: "Two-camera testimonial shoot, edited 60-90s clip with B-roll and lower thirds.",
      imageUrl: OS_IMG("2025/01/Oversee-Agency-Testimonial-Video-Package-Product-Image.webp"),
      imageAlt: "Oversee Agency testimonial video package",
      industries: INDUSTRIES_DEFAULT,
      price: 1500,
    },
    {
      id: "p14337", wooId: 14337, slug: "static-social-post-design",
      name: "Static Social Media Post Design", type: "simple",
      category: "Branding & Graphic Design",
      shortDescription: "On-brand static post graphics for any platform — concept, copy, design.",
      imageUrl: NO_WOO_IMAGE,
      imageAlt: "Static social media post design",
      industries: INDUSTRIES_DEFAULT.filter((i) => i !== "Legal Services"),
      price: 250,
    },
    {
      id: "p14335", wooId: 14335, slug: "brand-style-guide",
      name: "Brand Style Guide Package", type: "simple",
      category: "Branding & Graphic Design",
      shortDescription: "Logo lockups, type system, color, voice and usage rules — one PDF.",
      imageUrl: NO_WOO_IMAGE,
      imageAlt: "Brand style guide package",
      industries: INDUSTRIES_DEFAULT,
      price: 1000,
    },
    {
      id: "p14333", wooId: 14333, slug: "business-card-design",
      name: "Business Card Design", type: "simple",
      category: "Branding & Graphic Design",
      shortDescription: "Front + back, print-ready files in CMYK with bleeds.",
      imageUrl: NO_WOO_IMAGE,
      imageAlt: "Business card design",
      industries: INDUSTRIES_DEFAULT,
      price: 250,
    },
    {
      id: "p14331", wooId: 14331, slug: "logo-package",
      name: "Logo Package", type: "simple",
      category: "Branding & Graphic Design",
      shortDescription: "3 concepts, 2 revision rounds, primary + secondary marks, full file pack.",
      imageUrl: NO_WOO_IMAGE,
      imageAlt: "Logo package",
      industries: INDUSTRIES_DEFAULT,
      price: 500,
      badge: "Popular",
    },
    {
      id: "p14250", wooId: 14250, slug: "social-media-management",
      name: "Social Media Management (1 Platform)", type: "variable-subscription",
      category: "Social Media Management",
      shortDescription: "Strategy, content, scheduling, replies. Pick post mix per month.",
      imageUrl: OS_IMG("2025/01/Oversee-Agency-Social-Media-Managment-Product-Image.webp"),
      imageAlt: "Oversee Agency social media management package",
      industries: INDUSTRIES_DEFAULT,
      attributes: smm, variations: smmVars, period: "month",
      ...minMax(smmVars),
      badge: "Most popular",
      templateId: "tpl3",
    },
    // Subscription ad products — fixed $250/mo each. Image slugs map to the
    // canonical Woo product image on overseeagency.com's CDN.
    ...([
      { id: 14110, slug: "tiktok-ads", name: "TikTok Ads", img: "2024/12/Oversee-Agency-Tiktok-Ads-Product-Image.webp" },
      { id: 12243, slug: "snapchat-ads", name: "Snapchat Ads", img: "2024/12/Oversee-Agency-Snapchat-Ads-Product-Image.webp" },
      { id: 12242, slug: "linkedin-ads", name: "LinkedIn Ads", img: "2024/12/Oversee-Agency-LinkedIn-Ads-Product-Image.webp" },
      { id: 12240, slug: "meta-instagram-ads", name: "Meta & Instagram Ads", img: "2024/12/Oversee-Agency-Facebook-Meta-Instagram-Ads-Product-Image.webp" },
      { id: 9715, slug: "google-display-ad", name: "Google Display Ad", img: "2024/12/Oversee-Agency-Google-Display-Ads-Product-Image.webp" },
      { id: 9713, slug: "bing-display-ad", name: "Bing Display Ad", img: "2024/12/Oversee-Agency-Bing-Display-Ads-Product-Image.webp" },
      { id: 9712, slug: "bing-shopping-ad", name: "Bing Shopping Ad", img: "2024/12/Oversee-Agency-Bing-Shopping-Ads-Product-Image.webp" },
      { id: 9709, slug: "bing-search-ad", name: "Bing Search Ad", img: "2024/12/Oversee-Agency-Bing-Search-Ads-Product-Image.webp" },
      { id: 9685, slug: "google-local-service-ad", name: "Google Local Service Ad", img: "2024/12/Oversee-Agency-Google-Service-Ads-Product-Image.webp" },
      { id: 9577, slug: "google-search-ad", name: "Google Search Ad", img: "2024/12/Oversee-Agency-Google-Search-Ads-Product.webp" },
      { id: 9573, slug: "google-shopping-ad", name: "Google Shopping Ad", img: "2024/12/Google-Search-Ads-Product-Image.webp" },
    ].map((a) => ({
      id: `p${a.id}`,
      wooId: a.id,
      slug: a.slug,
      name: a.name,
      type: "subscription" as WooProductType,
      category: a.name.includes("TikTok") || a.name.includes("Snapchat") || a.name.includes("LinkedIn") || a.name.includes("Meta")
        ? "Social Media Advertising"
        : "Search Engine Advertising",
      shortDescription: `Managed ${a.name} campaigns — setup, creative, optimization, monthly reporting.`,
      imageUrl: OS_IMG(a.img),
      imageAlt: `Oversee Agency ${a.name} package`,
      industries: INDUSTRIES_DEFAULT,
      price: 250,
      period: "month" as const,
    }))),
    {
      id: "p11761", wooId: 11761, slug: "bing-business-setup",
      name: "Bing Business Setup", type: "simple",
      category: "Search Engine Optimization",
      shortDescription: "Claim, verify, optimize and submit your Bing Places listing.",
      imageUrl: OS_IMG("2024/12/Oversee-Agency-Bing-Business-Page-Setup-Product-Image.webp"),
      imageAlt: "Oversee Agency Bing Business Page setup",
      industries: INDUSTRIES_DEFAULT, price: 150,
    },
    {
      id: "p11709", wooId: 11709, slug: "google-business-setup",
      name: "Google Business Setup", type: "simple",
      category: "Search Engine Optimization",
      shortDescription: "Claim, verify, optimize Google Business Profile + photos & categories.",
      imageUrl: OS_IMG("2024/12/Oversee-Agency-Google-Business-Page-Setup-Product-Image.webp"),
      imageAlt: "Oversee Agency Google Business Page setup",
      industries: INDUSTRIES_DEFAULT, price: 150,
    },
    {
      id: "p11698", wooId: 11698, slug: "wp-website-maintenance",
      name: "WordPress Website Maintenance", type: "variable-subscription",
      category: "Website Hosting & Maintenance",
      shortDescription: "Monthly plugin updates, backups, monitoring, and small content edits.",
      imageUrl: OS_IMG("2024/12/Oversee-Agency-Wordpress-Website-Maintenance-Product-Image.webp"),
      imageAlt: "Oversee Agency WordPress website maintenance package",
      industries: INDUSTRIES_DEFAULT,
      attributes: [wpMaint], variations: wpMaintVars, period: "month",
      ...minMax(wpMaintVars),
    },
    {
      id: "p10981", wooId: 10981, slug: "managed-wp-hosting",
      name: "Managed WordPress Hosting", type: "variable-subscription",
      category: "Website Hosting & Maintenance",
      shortDescription: "Performance hosting on a tuned WordPress stack — CDN, SSL, daily backups.",
      imageUrl: OS_IMG("2024/12/Oversee-Agency-Managed-Wordpress-Website-Hosting-Product-Image.webp"),
      imageAlt: "Oversee Agency managed WordPress hosting package",
      industries: INDUSTRIES_DEFAULT,
      attributes: [hosting], variations: hostingVars, period: "month",
      ...minMax(hostingVars),
    },
    {
      id: "p10926", wooId: 10926, slug: "wp-ecommerce-website",
      name: "WordPress E-Commerce Website", type: "variable-subscription",
      category: "Website Development",
      shortDescription: "WooCommerce build with payments, shipping, and product imports. 8-month plan.",
      imageUrl: OS_IMG("2024/12/Oversee-Agency-Ecommerce-Website-Development-Setup-Product-Image.webp"),
      imageAlt: "Oversee Agency WordPress e-commerce website development",
      industries: INDUSTRIES_DEFAULT,
      attributes: ecom, variations: ecomVars, period: "month", termMonths: 8,
      ...minMax(ecomVars),
    },
    {
      id: "p10911", wooId: 10911, slug: "wp-website-development",
      name: "WordPress Website Development", type: "variable-subscription",
      category: "Website Development",
      shortDescription: "Custom WordPress build, design + dev + launch on a 6-month plan.",
      imageUrl: OS_IMG("2024/12/Oversee-Agency-Website-Development-Setup-Product-Image.webp"),
      imageAlt: "Oversee Agency WordPress website development package",
      industries: INDUSTRIES_DEFAULT,
      attributes: [dev], variations: devVars, period: "month", termMonths: 6,
      ...minMax(devVars),
      badge: "In your plan",
      templateId: "tpl4",
    },
    {
      id: "p9732", wooId: 9732, slug: "search-engine-optimization",
      name: "Search Engine Optimization", type: "variable-subscription",
      category: "Search Engine Optimization",
      shortDescription: "On-page SEO, content, and link building — pick volume per month.",
      imageUrl: OS_IMG("2024/12/Oversee-Agency-Search-Engine-Optimization-Product-Image.webp"),
      imageAlt: "Oversee Agency search engine optimization package",
      industries: INDUSTRIES_DEFAULT,
      attributes: seo, variations: seoVars, period: "month",
      ...minMax(seoVars),
      badge: "Most popular",
      templateId: "tpl1",
    },
  ];
  return products;
})();

const SERVICES: Service[] = [
  { id: "sv1", sku: "OS-SEO-START", title: "SEO Starter", blurb: "Foundational technical audit + 3 monthly content pieces.", price: 1490, cadence: "monthly", category: "SEO", templateId: "tpl1", badge: "Most popular" },
  { id: "sv2", sku: "OS-SEO-GROWTH", title: "SEO Growth", blurb: "Aggressive program: audits, link earning, 6 long-form / mo.", price: 3290, cadence: "monthly", category: "SEO", templateId: "tpl2" },
  { id: "sv3", sku: "OS-CONTENT-EDIT", title: "Editorial Sprint", blurb: "Six-week editorial sprint with calendar + 8 articles.", price: 5800, cadence: "one-time", category: "Content", templateId: "tpl3" },
  { id: "sv4", sku: "OS-WEB-MICRO", title: "Microsite Build", blurb: "Single-page microsite, design + dev + launch.", price: 4900, cadence: "one-time", category: "Web", templateId: "tpl4" },
  { id: "sv5", sku: "OS-BRAND-REFRESH", title: "Brand Refresh", blurb: "Identity refresh: logo, type, color, brand guidelines.", price: 7200, cadence: "one-time", category: "Design", templateId: "tpl5", badge: "In your plan" },
  { id: "sv6", sku: "OS-ADS-MGMT", title: "Paid Ads Management", blurb: "Google + Meta ads management. 10% of spend, $1.5k floor.", price: 1500, cadence: "monthly", category: "Ads" },
];

const SUBSCRIPTIONS: Subscription[] = [
  { id: "sub1", serviceId: "sv1", status: "active", renewsOn: isoFromNow(15), amount: 1490 },
  { id: "sub2", serviceId: "sv5", status: "active", renewsOn: isoFromNow(45), amount: 7200 },
  { id: "sub3", serviceId: "sv6", status: "past-due", renewsOn: isoFromNow(-2), amount: 1500 },
];

const INVOICES: Invoice[] = [
  { id: "inv1", number: "INV-1042", amount: 1490, status: "paid", date: isoFromNow(-15), service: "SEO Starter" },
  { id: "inv2", number: "INV-1051", amount: 7200, status: "paid", date: isoFromNow(-25), service: "Brand Refresh" },
  { id: "inv3", number: "INV-1063", amount: 1500, status: "past-due", date: isoFromNow(-2), service: "Paid Ads Management" },
  { id: "inv4", number: "INV-1064", amount: 1490, status: "open", date: isoFromNow(15), service: "SEO Starter" },
];

const ORDERS: Order[] = [
  { id: "o1", number: "#10428", customer: "Maya Lin", service: "SEO Starter", total: 1490, status: "completed", date: isoFromNow(-15) },
  { id: "o2", number: "#10433", customer: "Reggie Park", service: "Brand Refresh", total: 7200, status: "processing", date: isoFromNow(-3) },
  { id: "o3", number: "#10440", customer: "Lila Chen", service: "Microsite Build", total: 4900, status: "processing", date: isoFromNow(-1) },
  { id: "o4", number: "#10441", customer: "Vector Foods", service: "SEO Growth", total: 3290, status: "on-hold", date: isoFromNow(-1) },
  { id: "o5", number: "#10422", customer: "Boreal Studio", service: "Editorial Sprint", total: 5800, status: "completed", date: isoFromNow(-22) },
  { id: "o6", number: "#10444", customer: "Maya Lin", service: "Paid Ads Management", total: 1500, status: "processing", date: isoFromNow(0) },
];

const TEMPLATES: ServiceTemplate[] = [
  { id: "tpl1", name: "SEO Starter Template", category: "SEO", workflow: WORKFLOW_SEO, defaultItems: 8, serviceId: "sv1" },
  { id: "tpl2", name: "SEO Growth Template", category: "SEO", workflow: WORKFLOW_SEO, defaultItems: 14, serviceId: "sv2" },
  { id: "tpl3", name: "Editorial Sprint Template", category: "Content", workflow: ["Plan", "Draft", "Edit", "Approve", "Publish"], defaultItems: 12, serviceId: "sv3" },
  { id: "tpl4", name: "Microsite Build Template", category: "Web", workflow: WORKFLOW_DEFAULT, defaultItems: 10, serviceId: "sv4" },
  { id: "tpl5", name: "Brand Refresh Template", category: "Design", workflow: WORKFLOW_DEFAULT, defaultItems: 16, serviceId: "sv5" },
];

const INTAKE_FORMS: IntakeForm[] = [
  { id: "if1", name: "New client onboarding", fields: 14, responses: 23, attachedTo: "All services" },
  { id: "if2", name: "Brand discovery questionnaire", fields: 22, responses: 8, attachedTo: "Brand Refresh" },
  { id: "if3", name: "SEO target keyword input", fields: 9, responses: 12, attachedTo: "SEO Starter, SEO Growth" },
  { id: "if4", name: "Microsite content brief", fields: 18, responses: 4, attachedTo: "Microsite Build" },
];

const CALENDAR: CalendarEvent[] = [
  { id: "ev1", title: "Northstar — design review", date: isoFromNow(0), time: "2:00 PM", type: "call", client: "Northstar Gallery" },
  { id: "ev2", title: "Wordmark sign-off due", date: isoFromNow(2), time: "EOD", type: "deadline", client: "Northstar Gallery" },
  { id: "ev3", title: "Boreal Studio — kickoff call", date: isoFromNow(1), time: "10:30 AM", type: "call", client: "Boreal Studio" },
  { id: "ev4", title: "Q2 strategy sync — Vector Foods", date: isoFromNow(3), time: "11:00 AM", type: "call", client: "Vector Foods" },
  { id: "ev5", title: "Microsite wireframe review", date: isoFromNow(4), time: "3:00 PM", type: "review", client: "Northstar Gallery" },
];

const AUTOMATIONS: Automation[] = [
  {
    id: "au1",
    name: "WooCommerce → Project board",
    trigger: "Order completed",
    conditions: ["Service = 'Brand Refresh'", "Customer is new"],
    actions: ["Create board from Brand Refresh template", "Send welcome email", "Assign Sasha as PM"],
    active: true,
    runs: 47,
  },
  {
    id: "au2",
    name: "Notify on overdue client input",
    trigger: "Item status = 'Client Input' for 48h",
    conditions: ["Item priority ≥ Medium"],
    actions: ["Email client", "Post @mention in HighLevel chat", "Bump priority"],
    active: true,
    runs: 12,
  },
  {
    id: "au3",
    name: "Auto-archive completed boards",
    trigger: "All items status = Done",
    conditions: ["No activity for 14 days"],
    actions: ["Archive board", "Send 30-day review request"],
    active: false,
    runs: 0,
  },
  {
    id: "au4",
    name: "Failed payment recovery",
    trigger: "Subscription status = 'past-due'",
    conditions: [],
    actions: ["Retry charge in 3d", "Email customer", "Notify finance"],
    active: true,
    runs: 4,
  },
];

const CLIENTS: ClientRecord[] = [
  {
    id: "c1",
    name: "Maya Lin",
    company: "Northstar Gallery",
    email: "maya@northstargallery.com",
    phone: "+1 (415) 555-0181",
    status: "active",
    lifecycleStage: "delivery",
    lifetimeValue: 14_280,
    mrr: 1490,
    pm: "Sasha Patel",
    tags: ["VIP", "Brand", "SEO"],
    customFields: [
      { label: "Industry", value: "Art / Gallery" },
      { label: "Region", value: "San Francisco, CA" },
      { label: "WP user ID", value: "#1942" },
      { label: "Tier", value: "Growth" },
    ],
    internalNotes: [
      { id: "n1", author: "Sasha Patel", at: isoFromNow(-2, 11), body: "Maya prefers deck on Tuesdays. Loves warm orange direction." },
      { id: "n2", author: "Devon Ortiz", at: isoFromNow(-7, 14), body: "Pulled top organic queries from GSC — collector intent up 22% MoM." },
    ],
    joined: isoFromNow(-180),
    lastTouch: isoFromNow(0, 9),
    avatarColor: "bg-indigo-500",
  },
  {
    id: "c2",
    name: "Reggie Park",
    company: "Park & Co.",
    email: "reggie@parkco.com",
    phone: "+1 (212) 555-0144",
    status: "active",
    lifecycleStage: "onboarding",
    lifetimeValue: 7_200,
    mrr: 0,
    pm: "Sasha Patel",
    tags: ["Brand"],
    customFields: [
      { label: "Industry", value: "Boutique consulting" },
      { label: "Region", value: "New York, NY" },
      { label: "WP user ID", value: "#1955" },
      { label: "Tier", value: "Starter" },
    ],
    internalNotes: [
      { id: "n3", author: "Marcus Reed", at: isoFromNow(-1, 16), body: "Sent kickoff packet — brand discovery questionnaire pending." },
    ],
    joined: isoFromNow(-12),
    lastTouch: isoFromNow(-1, 16),
    avatarColor: "bg-emerald-600",
  },
  {
    id: "c3",
    name: "Lila Chen",
    company: "Chen Architects",
    email: "lila@chenarchitects.com",
    phone: "+1 (415) 555-0123",
    status: "active",
    lifecycleStage: "discovery",
    lifetimeValue: 4_900,
    mrr: 0,
    pm: "Devon Ortiz",
    tags: ["Web"],
    customFields: [
      { label: "Industry", value: "Architecture" },
      { label: "Region", value: "Oakland, CA" },
      { label: "WP user ID", value: "#1968" },
      { label: "Tier", value: "One-time" },
    ],
    internalNotes: [
      { id: "n4", author: "Devon Ortiz", at: isoFromNow(-1, 11), body: "New order for microsite build — needs template assignment." },
    ],
    joined: isoFromNow(-3),
    lastTouch: isoFromNow(-1, 11),
    avatarColor: "bg-rose-500",
  },
  {
    id: "c4",
    name: "Vector Foods",
    company: "Vector Foods Co.",
    email: "ops@vectorfoods.com",
    phone: "+1 (510) 555-0190",
    status: "paused",
    lifecycleStage: "renewal",
    lifetimeValue: 22_120,
    mrr: 3290,
    pm: "Sasha Patel",
    tags: ["SEO", "Renewal"],
    customFields: [
      { label: "Industry", value: "CPG / Food" },
      { label: "Region", value: "Berkeley, CA" },
      { label: "WP user ID", value: "#1820" },
      { label: "Tier", value: "Growth" },
    ],
    internalNotes: [
      { id: "n5", author: "Sasha Patel", at: isoFromNow(-1, 10), body: "Order on hold — waiting on template assignment & deposit invoice." },
    ],
    joined: isoFromNow(-380),
    lastTouch: isoFromNow(-1, 10),
    avatarColor: "bg-amber-500",
  },
  {
    id: "c5",
    name: "Boreal Studio",
    company: "Boreal Studio LLC",
    email: "hi@borealstudio.co",
    phone: "+1 (646) 555-0177",
    status: "active",
    lifecycleStage: "delivery",
    lifetimeValue: 5_800,
    mrr: 0,
    pm: "Lena Howe",
    tags: ["Editorial"],
    customFields: [
      { label: "Industry", value: "Design studio" },
      { label: "Region", value: "Brooklyn, NY" },
      { label: "WP user ID", value: "#1872" },
      { label: "Tier", value: "Sprint" },
    ],
    internalNotes: [],
    joined: isoFromNow(-90),
    lastTouch: isoFromNow(-3, 13),
    avatarColor: "bg-fuchsia-500",
  },
  {
    id: "c6",
    name: "Ana Velasquez",
    company: "Velasquez Wines",
    email: "ana@velasquezwines.com",
    phone: "+1 (707) 555-0145",
    status: "prospect",
    lifecycleStage: "discovery",
    lifetimeValue: 0,
    mrr: 0,
    pm: "Priya Shah",
    tags: ["Discovery"],
    customFields: [
      { label: "Industry", value: "Wine / DTC" },
      { label: "Region", value: "Sonoma, CA" },
      { label: "WP user ID", value: "—" },
      { label: "Tier", value: "Prospect" },
    ],
    internalNotes: [],
    joined: isoFromNow(-2),
    lastTouch: isoFromNow(-2, 10),
    avatarColor: "bg-purple-500",
  },
];

// Seed cross-channel client conversations so the Admin Clients Messages tab
// shows realistic history on first load. Each entry maps to a real production
// channel (HighLevel/OverseeCRM, Slack, email).
const CLIENT_MESSAGES_SEED: ClientMessage[] = [
  {
    id: "cm_seed_1",
    clientId: "c1",
    channel: "crm",
    direction: "out",
    authorName: "Sasha Patel",
    body: "Maya — sharing the three brand directions today. Excited to hear which one resonates.",
    at: isoFromNow(-2, 9),
    internal: false,
  },
  {
    id: "cm_seed_2",
    clientId: "c1",
    channel: "crm",
    direction: "in",
    authorName: "Maya Lin",
    body: "Thanks Sasha — Concept B is closest. Could we warm up Concept A too?",
    at: isoFromNow(-2, 11),
    internal: false,
  },
  {
    id: "cm_seed_3",
    clientId: "c1",
    channel: "slack",
    direction: "out",
    authorName: "Sasha Patel",
    body: "FYI — Maya leaning on Concept B. Devon, please prep a warmer A variant for Tuesday.",
    at: isoFromNow(-2, 12),
    internal: true,
  },
  {
    id: "cm_seed_4",
    clientId: "c1",
    channel: "email",
    direction: "out",
    authorName: "Sasha Patel",
    body: "Hi Maya — attaching the Q2 brand-refresh SOW for signature. Let me know if you'd like to adjust scope before kickoff.",
    at: isoFromNow(-1, 15),
    internal: false,
    email: { subject: "Q2 Brand Refresh — SOW for signature", to: "maya@northstargallery.com" },
  },
  {
    id: "cm_seed_5",
    clientId: "c2",
    channel: "crm",
    direction: "out",
    authorName: "Sasha Patel",
    body: "Reggie — onboarding packet sent. Brand discovery questionnaire is the next step whenever you're ready.",
    at: isoFromNow(-1, 14),
    internal: false,
  },
  {
    id: "cm_seed_6",
    clientId: "c4",
    channel: "slack",
    direction: "out",
    authorName: "Sasha Patel",
    body: "Vector renewal on hold pending deposit invoice. Will follow up with billing tomorrow.",
    at: isoFromNow(-1, 9),
    internal: true,
  },
];

const CONTRACTS: ContractRecord[] = [
  { id: "ct1", name: "Brand Refresh — Master Services Agreement", status: "signed", client: "Northstar Gallery", amount: 7200, sentAt: isoFromNow(-30, 14), signedAt: isoFromNow(-29, 11), pages: 6 },
  { id: "ct2", name: "Q2 SEO & Content — Statement of Work", status: "signed", client: "Northstar Gallery", amount: 1490, sentAt: isoFromNow(-22, 9), signedAt: isoFromNow(-21, 15), pages: 4 },
  { id: "ct3", name: "Spring Exhibition Microsite — SOW", status: "sent", client: "Northstar Gallery", amount: 4900, sentAt: isoFromNow(-2, 9), pages: 5 },
  { id: "ct4", name: "Photography release — May exhibition", status: "draft", client: "Northstar Gallery", pages: 3 },
];

const FORMS: FormRecord[] = [
  {
    id: "fm1",
    name: "Brand discovery questionnaire",
    description: "Help us understand your brand voice, audience, and aesthetic goals.",
    status: "open",
    due: isoFromNow(3),
    fields: [
      { id: "q1", label: "In one sentence, what does your brand stand for?", kind: "textarea", required: true },
      { id: "q2", label: "Three brands you admire (and why)", kind: "textarea" },
      { id: "q3", label: "Primary audience", kind: "select", options: ["Collectors", "Galleries", "Curators", "Press"], required: true },
      { id: "q4", label: "Tone", kind: "select", options: ["Authoritative", "Warm", "Editorial", "Playful"] },
      { id: "q5", label: "Existing brand assets to reuse", kind: "checkbox" },
      { id: "q6", label: "Logo references", kind: "file" },
    ],
  },
  {
    id: "fm2",
    name: "Microsite content brief",
    description: "Tell us what should live on the May exhibition microsite.",
    status: "submitted",
    fields: [
      { id: "q1", label: "Exhibition title", kind: "text", required: true },
      { id: "q2", label: "Artist list", kind: "textarea", required: true },
      { id: "q3", label: "Press contact", kind: "text" },
    ],
  },
  {
    id: "fm3",
    name: "SEO target keyword input",
    description: "Confirm the priority keyword set for next quarter.",
    status: "draft",
    fields: [
      { id: "q1", label: "Top 5 priority keywords", kind: "textarea", required: true },
      { id: "q2", label: "Geographic focus", kind: "text" },
    ],
  },
];

const PAYMENTS: Payment[] = [
  { id: "p1", amount: 1490, customer: "Maya Lin", service: "SEO Starter", method: "Stripe · Visa", status: "succeeded", date: isoFromNow(-15, 9) },
  { id: "p2", amount: 7200, customer: "Maya Lin", service: "Brand Refresh", method: "Stripe · Visa", status: "succeeded", date: isoFromNow(-25, 10) },
  { id: "p3", amount: 5800, customer: "Boreal Studio", service: "Editorial Sprint", method: "Stripe · MC", status: "succeeded", date: isoFromNow(-22, 11) },
  { id: "p4", amount: 1500, customer: "Maya Lin", service: "Paid Ads Management", method: "Stripe · Visa", status: "failed", date: isoFromNow(-2, 8) },
  { id: "p5", amount: 4900, customer: "Lila Chen", service: "Microsite Build", method: "Stripe · Amex", status: "succeeded", date: isoFromNow(-1, 14) },
  { id: "p6", amount: 7200, customer: "Reggie Park", service: "Brand Refresh", method: "Stripe · MC", status: "succeeded", date: isoFromNow(-3, 9) },
  { id: "p7", amount: 250, customer: "Boreal Studio", service: "SEO Add-on", method: "Stripe · Visa", status: "refunded", date: isoFromNow(-9, 16) },
];

const INTEGRATIONS: Integration[] = [
  { id: "highlevel", name: "HighLevel CRM", status: "connected", detail: "OAuth token valid · last sync 4m ago" },
  { id: "woocommerce", name: "WooCommerce", status: "connected", detail: "REST API · 6 products mapped · last sync 2m ago" },
  { id: "leadsie", name: "Leadsie (access requests)", status: "disconnected", detail: "Not connected. Add agency slug in Settings → Leadsie to start sending real requests." },
  { id: "pusher", name: "Pusher (real-time)", status: "connected", detail: "Channels: project-updates, messages" },
  { id: "bunny", name: "Bunny.net (CDN/storage)", status: "needs-attention", detail: "API key expires in 7 days" },
  { id: "resend", name: "Resend (email)", status: "connected", detail: "Domain verified · 3 templates active" },
];

// Default Leadsie settings — preview state.
const LEADSIE_SETTINGS_DEFAULT: LeadsieSettings = {
  connected: false,
  agencySlug: "oversee",
  embedSnippet: "<!-- Paste embed code from Leadsie → Settings → Embed -->",
  webhookUrl: "https://oversee.agency/api/webhooks/leadsie",
  defaultTemplates: [
    { platform: "google-analytics", label: "GA4 — admin/edit" },
    { platform: "google-search-console", label: "Search Console — restricted" },
    { platform: "google-tag-manager", label: "GTM — publish" },
    { platform: "google-ads", label: "Google Ads — standard" },
    { platform: "google-business-profile", label: "GBP — manager" },
    { platform: "meta-business", label: "Meta Business — admin" },
    { platform: "meta-ads", label: "Meta Ads — manage" },
    { platform: "wordpress", label: "WordPress — administrator" },
  ],
};

// Seed onboarding plans, keyed off the SERVICES list above.
const leadsieUrl = (slug: string, platform: LeadsiePlatform) =>
  `https://app.leadsie.com/connect/oversee-${slug}-${platform}`;

const ONBOARDING_PLANS: OnboardingPlan[] = [
  // SEO Starter (sv1) and SEO Growth (sv2) share the same shape
  ...(["sv1", "sv2"].map((sid) => ({
    serviceId: sid,
    steps: [
      {
        id: `${sid}-ga`,
        kind: "leadsie-access" as OnboardingStepKind,
        title: "Grant Google Analytics 4 access",
        why: "So we can measure organic traffic, conversions, and report monthly without you exporting CSVs.",
        platform: "google-analytics" as LeadsiePlatform,
        permissions: ["View reports", "Edit goals/events", "Read user list"],
        requestUrl: leadsieUrl(sid, "google-analytics"),
        status: sid === "sv1" ? ("complete" as const) : ("not-started" as const),
        leadsieStatus: sid === "sv1" ? ("approved" as LeadsieRequestStatus) : ("not-started" as LeadsieRequestStatus),
        due: isoFromNow(2),
      },
      {
        id: `${sid}-gsc`,
        kind: "leadsie-access" as OnboardingStepKind,
        title: "Grant Google Search Console access",
        why: "Required to track keywords, indexing issues, and submit sitemaps for technical SEO.",
        platform: "google-search-console" as LeadsiePlatform,
        permissions: ["Restricted user — view performance + submit sitemaps"],
        requestUrl: leadsieUrl(sid, "google-search-console"),
        status: sid === "sv1" ? ("in-progress" as const) : ("not-started" as const),
        leadsieStatus: sid === "sv1" ? ("link-sent" as LeadsieRequestStatus) : ("not-started" as LeadsieRequestStatus),
        due: isoFromNow(2),
      },
      {
        id: `${sid}-gbp`,
        kind: "leadsie-access" as OnboardingStepKind,
        title: "Grant Google Business Profile access",
        why: "Lets us manage local listings, photos, and posts to show up in Maps and local search.",
        platform: "google-business-profile" as LeadsiePlatform,
        permissions: ["Manager — edit profile, photos, posts"],
        requestUrl: leadsieUrl(sid, "google-business-profile"),
        status: "not-started" as const,
        leadsieStatus: "not-started" as LeadsieRequestStatus,
        due: isoFromNow(4),
      },
      {
        id: `${sid}-gtm`,
        kind: "leadsie-access" as OnboardingStepKind,
        title: "Grant Google Tag Manager access",
        why: "For installing and updating tracking pixels without dev help.",
        platform: "google-tag-manager" as LeadsiePlatform,
        permissions: ["Publish access on the container"],
        requestUrl: leadsieUrl(sid, "google-tag-manager"),
        status: "not-started" as const,
        leadsieStatus: "not-started" as LeadsieRequestStatus,
        due: isoFromNow(4),
        optional: true,
      },
      {
        id: `${sid}-intake`,
        kind: "intake-form" as OnboardingStepKind,
        title: "Complete SEO intake",
        why: "Tell us your top 5 services, target locations, competitors, and dream keywords.",
        status: "not-started" as const,
        due: isoFromNow(5),
      },
      {
        id: `${sid}-kickoff`,
        kind: "schedule" as OnboardingStepKind,
        title: "Schedule kickoff call",
        why: "30-minute call with your strategist to align on goals and reporting cadence.",
        status: "not-started" as const,
        due: isoFromNow(7),
      },
    ],
  } satisfies OnboardingPlan)) as OnboardingPlan[]),
  // Brand Refresh (sv5) — design-first onboarding
  {
    serviceId: "sv5",
    label: "Brand Refresh — onboarding",
    steps: [
      {
        id: "sv5-intake",
        kind: "intake-form",
        title: "Complete brand discovery questionnaire",
        why: "22 questions. Tone, audience, vibe, references. Without this, design is a guess.",
        status: "complete",
        due: isoFromNow(-3),
      },
      {
        id: "sv5-upload",
        kind: "upload",
        title: "Upload existing logo + brand assets",
        why: "Old logo files, fonts, photography, anything currently in market.",
        status: "in-progress",
        due: isoFromNow(2),
      },
      {
        id: "sv5-wp",
        kind: "leadsie-access",
        title: "Grant WordPress access (for rollout)",
        why: "So we can install the new identity on your site once it's approved.",
        platform: "wordpress",
        permissions: ["Administrator role on /wp-admin"],
        requestUrl: leadsieUrl("sv5", "wordpress"),
        status: "not-started",
        leadsieStatus: "not-started",
        due: isoFromNow(10),
      },
      {
        id: "sv5-kickoff",
        kind: "schedule",
        title: "Book design kickoff",
        why: "Walk through the brief together so the first concepts land closer to right.",
        status: "not-started",
        due: isoFromNow(3),
      },
    ],
  },
  // Paid Ads Management (sv6) — Google + Meta
  {
    serviceId: "sv6",
    label: "Paid Ads Management — onboarding",
    steps: [
      {
        id: "sv6-gads",
        kind: "leadsie-access",
        title: "Grant Google Ads (or MCC) access",
        why: "We manage your campaigns from our agency MCC. No password sharing.",
        platform: "google-ads",
        permissions: ["Standard access — manage campaigns + budgets"],
        requestUrl: leadsieUrl("sv6", "google-ads"),
        status: "not-started",
        leadsieStatus: "not-started",
        due: isoFromNow(1),
      },
      {
        id: "sv6-meta",
        kind: "leadsie-access",
        title: "Grant Meta Business + Ads access",
        why: "Required to launch Facebook/Instagram ads and read Pixel events.",
        platform: "meta-business",
        permissions: ["Admin on Business Manager", "Manage on Ads account", "Pixel access"],
        requestUrl: leadsieUrl("sv6", "meta-business"),
        status: "not-started",
        leadsieStatus: "not-started",
        due: isoFromNow(1),
      },
      {
        id: "sv6-ga",
        kind: "leadsie-access",
        title: "Grant Google Analytics 4 access",
        why: "For attribution: tying ad spend back to revenue.",
        platform: "google-analytics",
        permissions: ["View reports"],
        requestUrl: leadsieUrl("sv6", "google-analytics"),
        status: "not-started",
        leadsieStatus: "not-started",
        due: isoFromNow(2),
        optional: true,
      },
      {
        id: "sv6-budget",
        kind: "approve",
        title: "Confirm starting monthly ad budget",
        why: "We need a number to plan campaigns around. You can change it anytime.",
        status: "not-started",
        due: isoFromNow(2),
      },
      {
        id: "sv6-assets",
        kind: "upload",
        title: "Upload brand assets + creative",
        why: "Logos, hero photography, video clips, prior best-performing ads.",
        status: "not-started",
        due: isoFromNow(4),
      },
    ],
  },
];

// ============== Context ==============

type DemoState = {
  customer: typeof CUSTOMER;
  team: TeamMember[];
  boards: Board[];
  items: BoardItem[];
  feed: FeedUpdate[];
  files: FileEntry[];
  services: Service[];
  products: WooProduct[];
  subscriptions: Subscription[];
  invoices: Invoice[];
  orders: Order[];
  templates: ServiceTemplate[];
  intakeForms: IntakeForm[];
  calendar: CalendarEvent[];
  automations: Automation[];
  integrations: Integration[];
  clients: ClientRecord[];
  contracts: ContractRecord[];
  forms: FormRecord[];
  payments: Payment[];
  cart: CartLine[];
  showInternalNotes: boolean;
  toggleInternalNotes: () => void;
  moveItem: (itemId: ID, toStage: string) => void;
  addLineToCart: (line: Omit<CartLine, "id">) => void;
  removeLineFromCart: (lineId: ID) => void;
  clearCart: () => void;
  bookmarkUpdate: (id: ID) => void;
  markUpdateRead: (id: ID) => void;
  addDiscussionPost: (itemId: ID, post: Omit<DiscussionPost, "id" | "postedAt">) => void;
  addReplyToPost: (itemId: ID, postId: ID, reply: Omit<DiscussionPost, "id" | "postedAt">) => void;
  togglePostReaction: (itemId: ID, postId: ID, emoji: string) => void;
  togglePostPin: (itemId: ID, postId: ID) => void;
  togglePostInternal: (itemId: ID, postId: ID) => void;
  // Boards & items
  addBoard: (name: string, clientEmail: string, description?: string) => ID;
  updateBoard: (boardId: ID, patch: Partial<Board>) => void;
  deleteBoard: (boardId: ID) => void;
  addItem: (boardId: ID, title: string, opts?: Partial<BoardItem>) => ID;
  addSubitem: (itemId: ID, title: string, assignee?: string) => void;
  toggleSubitem: (itemId: ID, subitemId: ID) => void;
  setItemStatus: (itemId: ID, toStage: string, actorName?: string, actorRole?: "client" | "admin") => void;
  setItemField: (itemId: ID, field: Partial<BoardItem>) => void;
  // Files
  setFileApproval: (fileId: ID, approval: FileEntry["approval"], note?: string) => void;
  addFile: (file: Omit<FileEntry, "id">) => ID;
  // subscription mutations (preview state)
  pauseSubscription: (subId: ID) => void;
  resumeSubscription: (subId: ID) => void;
  cancelSubscription: (subId: ID) => void;
  // contracts preview
  signContract: (contractId: ID) => void;
  // Client mutations
  addClientNote: (clientId: ID, body: string, author?: string) => void;
  addClient: (input: { name: string; company: string; email: string }) => ID;
  addClientCustomField: (clientId: ID, label: string, value: string) => void;
  // Client cross-channel comms (CRM / Slack / Email).
  clientMessages: ClientMessage[];
  addClientMessage: (
    msg: Omit<ClientMessage, "id" | "at"> & { at?: string },
  ) => ID;
  // Onboarding (Leadsie + intake/upload/schedule)
  onboardingPlans: OnboardingPlan[];
  leadsieSettings: LeadsieSettings;
  setOnboardingStepStatus: (planServiceId: ID, stepId: ID, status: OnboardingStep["status"], leadsieStatus?: LeadsieRequestStatus) => void;
  updateLeadsieSettings: (patch: Partial<LeadsieSettings>) => void;
};

const Ctx = createContext<DemoState | null>(null);

export function DemoStoreProvider({ children }: PropsWithChildren) {
  const [items, setItems] = useState<BoardItem[]>(ITEMS);
  const [feed, setFeed] = useState<FeedUpdate[]>(FEED_UPDATES);
  const [boardsState, setBoardsState] = useState<Board[]>(BOARDS);
  const [filesState, setFilesState] = useState<FileEntry[]>(FILES);
  const [cart, setCart] = useState<CartLine[]>([]);
  const [subs, setSubs] = useState<Subscription[]>(SUBSCRIPTIONS);
  const [contractsState, setContractsState] = useState<ContractRecord[]>(CONTRACTS);
  const [showInternalNotes, setShowInternalNotes] = useState(true);
  const [onboardingPlans, setOnboardingPlans] = useState<OnboardingPlan[]>(ONBOARDING_PLANS);
  const [leadsieSettings, setLeadsieSettings] = useState<LeadsieSettings>(LEADSIE_SETTINGS_DEFAULT);
  const [clientsState, setClientsState] = useState<ClientRecord[]>(CLIENTS);
  const [clientMessagesState, setClientMessagesState] = useState<ClientMessage[]>(
    CLIENT_MESSAGES_SEED,
  );

  const addClientMessage = useCallback(
    (msg: Omit<ClientMessage, "id" | "at"> & { at?: string }) => {
      const id = `cm_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`;
      const entry: ClientMessage = {
        id,
        clientId: msg.clientId,
        channel: msg.channel,
        direction: msg.direction,
        authorName: msg.authorName,
        body: msg.body,
        at: msg.at ?? new Date().toISOString(),
        internal: msg.internal,
        email: msg.email,
      };
      setClientMessagesState((prev) => [...prev, entry]);
      // Update lastTouch on the client record so the list shows fresh activity.
      setClientsState((prev) =>
        prev.map((c) =>
          c.id !== msg.clientId ? c : { ...c, lastTouch: entry.at },
        ),
      );
      return id;
    },
    [],
  );

  const addClient = useCallback(({ name, company, email }: { name: string; company: string; email: string }) => {
    const id = `c_${Date.now()}`;
    const now = new Date().toISOString().slice(0, 10);
    setClientsState((prev) => [
      {
        id,
        name: name.trim() || "New client",
        company: company.trim() || name.trim() || "",
        email: email.trim(),
        phone: "",
        status: "prospect",
        lifecycleStage: "discovery",
        lifetimeValue: 0,
        mrr: 0,
        pm: "Sasha Patel",
        tags: ["manual"],
        customFields: [],
        internalNotes: [],
        joined: now,
        lastTouch: now,
        avatarColor: "bg-zinc-200 text-zinc-700",
      },
      ...prev,
    ]);
    return id;
  }, []);

  const addClientCustomField = useCallback((clientId: ID, label: string, value: string) => {
    if (!label.trim()) return;
    setClientsState((prev) =>
      prev.map((c) =>
        c.id !== clientId
          ? c
          : { ...c, customFields: [...c.customFields, { label: label.trim(), value: value.trim() }] },
      ),
    );
  }, []);

  const addClientNote = useCallback((clientId: ID, body: string, author = "Sasha Patel") => {
    if (!body.trim()) return;
    setClientsState((prev) =>
      prev.map((c) =>
        c.id !== clientId
          ? c
          : {
              ...c,
              internalNotes: [
                {
                  id: `note_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`,
                  author,
                  at: new Date().toISOString(),
                  body: body.trim(),
                },
                ...c.internalNotes,
              ],
            },
      ),
    );
  }, []);

  // Pure stage->status mapper.
  const stageToStatus = (stage: string): ItemStatus => {
    const lower = stage.toLowerCase();
    if (lower.includes("stuck") || lower.includes("blocked")) return "stuck";
    if (lower.includes("revision")) return "client-input";
    if (lower.includes("approved") || lower.includes("done") || lower.includes("live") || lower.includes("publish")) return "done";
    if (lower.includes("review")) return "review";
    if (lower.includes("waiting") || lower.includes("client") || lower.includes("input")) return "client-input";
    if (lower.includes("working") || lower.includes("progress") || lower.includes("draft") || lower.includes("edit")) return "in-progress";
    return "not-started";
  };

  const moveItem = useCallback((itemId: ID, toStage: string) => {
    setItems((prev) =>
      prev.map((i) => {
        if (i.id !== itemId) return i;
        return { ...i, workflowStage: toStage, status: stageToStatus(toStage) };
      })
    );
  }, []);

  // Status change with system-post broadcast (Monday parity).
  const setItemStatus = useCallback(
    (itemId: ID, toStage: string, actorName = "Sasha Patel", actorRole: "client" | "admin" = "admin") => {
      setItems((prev) =>
        prev.map((i) => {
          if (i.id !== itemId) return i;
          const fromStage = i.workflowStage;
          if (fromStage === toStage) return i;
          const sys: DiscussionPost = {
            id: `sys_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`,
            authorName: actorName,
            authorRole: "system",
            systemKind: "status-change",
            body: `moved status from ${fromStage} → ${toStage}`,
            postedAt: new Date().toISOString(),
          };
          return {
            ...i,
            workflowStage: toStage,
            status: stageToStatus(toStage),
            discussion: [...i.discussion, sys],
            activity: [
              ...i.activity,
              {
                id: `act_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`,
                at: new Date().toISOString(),
                authorName: actorName,
                text: `moved status to ${toStage}`,
              },
            ],
          };
        }),
      );
      // Push into cross-board feed too
      setFeed((prev) => [
        {
          id: `u_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`,
          boardId: items.find((i) => i.id === itemId)?.boardId || "",
          itemId,
          authorName: actorName,
          authorRole: actorRole,
          body: `moved item to ${toStage}`,
          postedAt: new Date().toISOString(),
          unread: true,
          mentioned: false,
          bookmarked: false,
          internalOnly: false,
        },
        ...prev,
      ]);
    },
    [items],
  );

  const setItemField = useCallback((itemId: ID, field: Partial<BoardItem>) => {
    setItems((prev) => prev.map((i) => (i.id === itemId ? { ...i, ...field } : i)));
  }, []);

  const addBoard = useCallback((name: string, clientEmail: string, description = "") => {
    const id = `b_${Date.now()}`;
    const newBoard: Board = {
      id,
      name,
      clientEmail,
      description,
      workflow: WORKFLOW_DEFAULT,
      progress: 0,
      unreadUpdates: 0,
      pendingClientInput: 0,
      status: "active",
      owner: "Sasha Patel",
    };
    setBoardsState((prev) => [newBoard, ...prev]);
    return id;
  }, []);

  const updateBoard = useCallback((boardId: ID, patch: Partial<Board>) => {
    setBoardsState((prev) => prev.map((b) => (b.id === boardId ? { ...b, ...patch } : b)));
  }, []);

  const deleteBoard = useCallback((boardId: ID) => {
    setBoardsState((prev) => prev.filter((b) => b.id !== boardId));
    // Also drop items that belonged to that board so counts/feeds stay clean.
    setItems((prev) => prev.filter((i) => i.boardId !== boardId));
    setFeed((prev) => prev.filter((f) => f.boardId !== boardId));
  }, []);

  const addItem = useCallback((boardId: ID, title: string, opts?: Partial<BoardItem>) => {
    const id = `i_${Date.now()}_${Math.random().toString(36).slice(2, 6)}`;
    const newItem: BoardItem = {
      id,
      boardId,
      title,
      status: opts?.status ?? "not-started",
      priority: opts?.priority ?? "medium",
      due: opts?.due,
      assignee: opts?.assignee ?? "Sasha Patel",
      workflowStage: opts?.workflowStage ?? "Backlog",
      tags: opts?.tags ?? [],
      subitems: opts?.subitems ?? [],
      discussion: opts?.discussion ?? [],
      deliverables: opts?.deliverables ?? [],
      activity: opts?.activity ?? [
        { id: `act_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`, at: new Date().toISOString(), authorName: "Sasha Patel", text: "created item" },
      ],
      description: opts?.description,
    };
    setItems((prev) => [...prev, newItem]);
    return id;
  }, []);

  const addSubitem = useCallback((itemId: ID, title: string, assignee?: string) => {
    setItems((prev) =>
      prev.map((i) =>
        i.id !== itemId
          ? i
          : {
              ...i,
              subitems: [
                ...i.subitems,
                { id: `s_${Date.now()}_${Math.random().toString(36).slice(2, 5)}`, title, done: false, assignee },
              ],
            },
      ),
    );
  }, []);

  const toggleSubitem = useCallback((itemId: ID, subitemId: ID) => {
    setItems((prev) =>
      prev.map((i) =>
        i.id !== itemId
          ? i
          : {
              ...i,
              subitems: i.subitems.map((s) => (s.id === subitemId ? { ...s, done: !s.done } : s)),
            },
      ),
    );
  }, []);

  const addLineToCart = useCallback((line: Omit<CartLine, "id">) => {
    setCart((prev) => [
      ...prev,
      { ...line, id: `cl_${Date.now()}_${Math.floor(Math.random() * 9999)}` },
    ]);
  }, []);
  const removeLineFromCart = useCallback((lineId: ID) => {
    setCart((prev) => prev.filter((c) => c.id !== lineId));
  }, []);
  const clearCart = useCallback(() => setCart([]), []);

  const pauseSubscription = useCallback((subId: ID) => {
    setSubs((prev) => prev.map((s) => (s.id === subId ? { ...s, status: "paused" } : s)));
  }, []);
  const resumeSubscription = useCallback((subId: ID) => {
    setSubs((prev) => prev.map((s) => (s.id === subId ? { ...s, status: "active" } : s)));
  }, []);
  const cancelSubscription = useCallback((subId: ID) => {
    setSubs((prev) => prev.map((s) => (s.id === subId ? { ...s, status: "cancelled" } : s)));
  }, []);
  const signContract = useCallback((contractId: ID) => {
    setContractsState((prev) =>
      prev.map((c) =>
        c.id === contractId ? { ...c, status: "signed", signedAt: new Date().toISOString() } : c,
      ),
    );
  }, []);

  const bookmarkUpdate = useCallback((id: ID) => {
    setFeed((prev) => prev.map((u) => (u.id === id ? { ...u, bookmarked: !u.bookmarked } : u)));
  }, []);
  const markUpdateRead = useCallback((id: ID) => {
    setFeed((prev) => prev.map((u) => (u.id === id ? { ...u, unread: false } : u)));
  }, []);

  const addDiscussionPost = useCallback(
    (itemId: ID, post: Omit<DiscussionPost, "id" | "postedAt">) => {
      setItems((prev) =>
        prev.map((i) =>
          i.id !== itemId
            ? i
            : {
                ...i,
                discussion: [
                  ...i.discussion,
                  { ...post, id: `d_${Date.now()}_${Math.random().toString(36).slice(2, 5)}`, postedAt: new Date().toISOString() },
                ],
              }
        )
      );
      // mirror into cross-board feed (skip system posts to avoid noise)
      if (post.authorRole !== "system") {
        const item = items.find((i) => i.id === itemId);
        setFeed((prev) => [
          {
            id: `u_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`,
            boardId: item?.boardId || "",
            itemId,
            authorName: post.authorName,
            authorRole: post.authorRole === "client" ? "client" : "admin",
            body: post.body,
            postedAt: new Date().toISOString(),
            unread: true,
            mentioned: post.body.includes("@"),
            bookmarked: false,
            internalOnly: !!post.internalOnly,
            attachment: post.attachments?.[0],
          },
          ...prev,
        ]);
      }
    },
    [items],
  );

  const addReplyToPost = useCallback(
    (itemId: ID, postId: ID, reply: Omit<DiscussionPost, "id" | "postedAt">) => {
      setItems((prev) =>
        prev.map((i) => {
          if (i.id !== itemId) return i;
          return {
            ...i,
            discussion: i.discussion.map((p) =>
              p.id === postId
                ? {
                    ...p,
                    replies: [
                      ...(p.replies || []),
                      { ...reply, id: `r_${Date.now()}_${Math.random().toString(36).slice(2, 5)}`, postedAt: new Date().toISOString() },
                    ],
                  }
                : p,
            ),
          };
        }),
      );
    },
    [],
  );

  const togglePostReaction = useCallback((itemId: ID, postId: ID, emoji: string) => {
    setItems((prev) =>
      prev.map((i) => {
        if (i.id !== itemId) return i;
        return {
          ...i,
          discussion: i.discussion.map((p) => {
            if (p.id !== postId) return p;
            const reactions = p.reactions || [];
            const existing = reactions.find((r) => r.emoji === emoji);
            let next: Reaction[];
            if (existing) {
              next = reactions
                .map((r) =>
                  r.emoji === emoji ? { ...r, count: r.count + (r.reacted ? -1 : 1), reacted: !r.reacted } : r,
                )
                .filter((r) => r.count > 0);
            } else {
              next = [...reactions, { emoji, count: 1, reacted: true }];
            }
            return { ...p, reactions: next };
          }),
        };
      }),
    );
  }, []);

  const togglePostPin = useCallback((itemId: ID, postId: ID) => {
    setItems((prev) =>
      prev.map((i) =>
        i.id !== itemId
          ? i
          : {
              ...i,
              discussion: i.discussion.map((p) => (p.id === postId ? { ...p, pinned: !p.pinned } : p)),
            },
      ),
    );
  }, []);

  const togglePostInternal = useCallback((itemId: ID, postId: ID) => {
    setItems((prev) =>
      prev.map((i) =>
        i.id !== itemId
          ? i
          : {
              ...i,
              discussion: i.discussion.map((p) => (p.id === postId ? { ...p, internalOnly: !p.internalOnly } : p)),
            },
      ),
    );
  }, []);

  const setFileApproval = useCallback((fileId: ID, approval: FileEntry["approval"], _note?: string) => {
    setFilesState((prev) => prev.map((f) => (f.id === fileId ? { ...f, approval } : f)));
  }, []);

  const addFile = useCallback((file: Omit<FileEntry, "id">) => {
    const id = `f_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`;
    setFilesState((prev) => [{ id, ...file }, ...prev]);
    // Record an upload event in the feed so it appears in client/admin activity surfaces.
    setBoardsState((boardsPrev) => {
      const matchedBoard = file.boardName
        ? boardsPrev.find((b) => b.name === file.boardName)
        : undefined;
      const boardId = matchedBoard?.id ?? boardsPrev[0]?.id;
      if (boardId) {
        const feedId = `fu_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`;
        setFeed((feedPrev) => [
          {
            id: feedId,
            boardId,
            authorName: file.uploadedBy,
            authorRole: "client",
            body: `Uploaded \u201C${file.name}\u201D (${file.kind}, ${file.size}).`,
            postedAt: file.uploadedAt,
            unread: true,
            mentioned: false,
            bookmarked: false,
            internalOnly: false,
            attachment: file.src
              ? { id: `a_${id}`, kind: file.kind === "image" ? "image" : "file", src: file.src, caption: file.name }
              : undefined,
          },
          ...feedPrev,
        ]);
      }
      return boardsPrev;
    });
    return id;
  }, []);

  const setOnboardingStepStatus = useCallback(
    (planServiceId: ID, stepId: ID, status: OnboardingStep["status"], leadsieStatus?: LeadsieRequestStatus) => {
      setOnboardingPlans((prev) =>
        prev.map((plan) =>
          plan.serviceId === planServiceId
            ? {
                ...plan,
                steps: plan.steps.map((s) =>
                  s.id === stepId
                    ? { ...s, status, leadsieStatus: leadsieStatus ?? s.leadsieStatus }
                    : s,
                ),
              }
            : plan,
        ),
      );
    },
    [],
  );

  const updateLeadsieSettings = useCallback((patch: Partial<LeadsieSettings>) => {
    setLeadsieSettings((prev) => ({ ...prev, ...patch }));
  }, []);

  const value = useMemo<DemoState>(
    () => ({
      customer: CUSTOMER,
      team: TEAM,
      boards: boardsState,
      items,
      feed,
      files: filesState,
      services: SERVICES,
      products: OS_PRODUCTS,
      subscriptions: subs,
      invoices: INVOICES,
      orders: ORDERS,
      templates: TEMPLATES,
      intakeForms: INTAKE_FORMS,
      calendar: CALENDAR,
      automations: AUTOMATIONS,
      integrations: INTEGRATIONS,
      clients: clientsState,
      addClientNote,
      addClient,
      addClientCustomField,
      clientMessages: clientMessagesState,
      addClientMessage,
      contracts: contractsState,
      forms: FORMS,
      payments: PAYMENTS,
      cart,
      showInternalNotes,
      toggleInternalNotes: () => setShowInternalNotes((v) => !v),
      moveItem,
      addLineToCart,
      removeLineFromCart,
      clearCart,
      bookmarkUpdate,
      markUpdateRead,
      addDiscussionPost,
      addReplyToPost,
      togglePostReaction,
      togglePostPin,
      togglePostInternal,
      addBoard,
      updateBoard,
      deleteBoard,
      addItem,
      addSubitem,
      toggleSubitem,
      setItemStatus,
      setItemField,
      setFileApproval,
      addFile,
      pauseSubscription,
      resumeSubscription,
      cancelSubscription,
      signContract,
      onboardingPlans,
      leadsieSettings,
      setOnboardingStepStatus,
      updateLeadsieSettings,
    }),
    [items, feed, boardsState, filesState, cart, subs, contractsState, showInternalNotes, onboardingPlans, leadsieSettings, clientsState, clientMessagesState, addClientNote, addClient, addClientCustomField, addClientMessage, moveItem, setItemStatus, addLineToCart, removeLineFromCart, clearCart, bookmarkUpdate, markUpdateRead, addDiscussionPost, addReplyToPost, togglePostReaction, togglePostPin, togglePostInternal, addBoard, updateBoard, deleteBoard, addItem, addSubitem, toggleSubitem, setItemField, setFileApproval, addFile, pauseSubscription, resumeSubscription, cancelSubscription, signContract, setOnboardingStepStatus, updateLeadsieSettings]
  );

  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}

export function useDemoStore() {
  const v = useContext(Ctx);
  if (!v) throw new Error("useDemoStore must be used inside DemoStoreProvider");
  return v;
}

export const SAMPLE_IMAGE_PICKER = [
  { id: "p1", src: SAMPLE_IMG(1), caption: "Dashboard screenshot" },
  { id: "p2", src: SAMPLE_IMG(2), caption: "Wireframe v1" },
  { id: "p3", src: SAMPLE_IMG(3), caption: "Brand moodboard" },
  { id: "p4", src: SAMPLE_IMG(4), caption: "Keyword cluster" },
  { id: "p5", src: SAMPLE_IMG(5), caption: "Photography ref" },
  { id: "p6", src: SAMPLE_IMG(6), caption: "Gallery interior" },
];
