// Runtime config injected by the WordPress companion plugin (see
// OCD_Assets::runtime_config). The SPA reads this in main.tsx to decide
// whether to boot into the client portal, the admin console, or the
// passwordless login CTA.

import type { OverseeRuntimeConfig } from "./lib/boot";

declare global {
  interface Window {
    OCD_CONFIG?: OverseeRuntimeConfig & {
      restUrl?: string;
      overseeRestUrl?: string;
      wcRestUrl?: string;
      nonce?: string;
      pluginUrl?: string;
      dashboardUrl?: string;
      loginUrl?: string;
      siteUrl?: string;
      logoutUrl?: string;
      currentUser?: {
        id?: number;
        displayName?: string;
        email?: string;
        roles?: string[];
      } | null;
    };
    OVERSEE_CONFIG?: Window["OCD_CONFIG"];
  }
}

export {};
