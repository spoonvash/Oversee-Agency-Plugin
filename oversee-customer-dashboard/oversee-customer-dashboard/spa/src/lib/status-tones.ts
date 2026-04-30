// Shared mappers from domain status strings to design-system StatusTone values.
// Promoted out of client/account.tsx so commerce.tsx and any other surface that
// renders subscription / invoice state can use the same colour vocabulary.
//
// Keep these tone tables in lock-step with the StatusPill tones in
// `components/shared.tsx`. Adding a new domain status? Map it here, never
// inline a one-off colour at the call site.

import type { StatusTone } from "@/components/shared";
import type { Subscription, Invoice } from "@/lib/demo-store";

export function subscriptionTone(s: Subscription["status"]): StatusTone {
  if (s === "active") return "success";
  if (s === "past-due") return "danger";
  if (s === "paused") return "warning";
  return "neutral";
}

export function invoiceTone(s: Invoice["status"]): StatusTone {
  if (s === "paid") return "success";
  if (s === "past-due") return "danger";
  return "warning";
}
