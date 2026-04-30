import { useState } from "react";
import { Link, useLocation } from "wouter";
import { ArrowRight, Check, Mail } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { OverseeLogo } from "@/components/oversee-logo";
import { ThemeToggle } from "@/components/theme-toggle";
import { BackgroundImage } from "@/components/dynamic-styles";

// Passwordless login mimicking BrandMages portal — split layout, single email field,
// "Email me a login link" CTA, image panel on the right. Mocks SSO magic-link flow
// that the WP companion plugin would issue at /dashboard/login/.
export default function LoginPage() {
  const [, navigate] = useLocation();
  const [email, setEmail] = useState("");
  const [sent, setSent] = useState(false);

  return (
    <div className="min-h-screen bg-background">
      <div className="absolute right-4 top-4 flex items-center gap-2">
        <ThemeToggle />
        <Link
          href="/"
          className="rounded-md border px-3 py-1.5 text-xs font-medium text-muted-foreground hover:text-foreground"
          data-testid="link-back-to-selector"
        >
          Back to preview
        </Link>
      </div>

      <div className="mx-auto flex min-h-screen w-full max-w-6xl items-center justify-center px-6 py-12">
        <div className="grid w-full overflow-hidden rounded-lg border bg-card md:grid-cols-2">
          {/* Left: form panel */}
          <div className="flex flex-col justify-center px-8 py-12 md:px-14">
            <OverseeLogo />
            <h1 className="mt-10 text-2xl font-semibold tracking-tight">
              Sign in to your account
            </h1>
            <p className="mt-1.5 text-sm text-muted-foreground">
              Use a passwordless magic link delivered to your inbox.
            </p>

            {sent ? (
              <div
                className="mt-8 rounded-lg border border-success/30 bg-success/5 p-5"
                data-testid="login-sent-state"
              >
                <div className="flex items-center gap-2 text-success">
                  <Check className="size-4" />
                  <span className="text-sm font-semibold">Login link sent</span>
                </div>
                <p className="mt-2 text-sm text-muted-foreground">
                  We sent a one-time link to <span className="font-medium text-foreground">{email}</span>.
                  Click it to sign in.
                </p>
                <p className="mt-3 text-xs text-muted-foreground">
                  In production this would land at <code className="rounded bg-muted px-1 py-0.5 font-mono text-[11px]">/dashboard/</code> after the
                  WordPress companion plugin verifies the token.
                </p>
                <div className="mt-5 flex flex-wrap gap-2">
                  <Button
                    onClick={() => navigate("/client")}
                    className="gap-1.5"
                    data-testid="button-continue-as-client"
                  >
                    Continue as client <ArrowRight className="size-3.5" />
                  </Button>
                  <Button
                    variant="outline"
                    onClick={() => navigate("/admin")}
                    className="gap-1.5"
                    data-testid="button-continue-as-admin"
                  >
                    Continue as admin
                  </Button>
                  <Button
                    variant="ghost"
                    onClick={() => {
                      setSent(false);
                      setEmail("");
                    }}
                    className="text-xs"
                    data-testid="button-resend"
                  >
                    Send another link
                  </Button>
                </div>
              </div>
            ) : (
              <form
                onSubmit={(e) => {
                  e.preventDefault();
                  if (email.trim()) setSent(true);
                }}
                className="mt-8 space-y-4"
              >
                <div className="space-y-1.5">
                  <label htmlFor="email" className="text-xs font-medium">
                    Email
                  </label>
                  <Input
                    id="email"
                    type="email"
                    placeholder="you@company.com"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    autoComplete="email"
                    className="h-11"
                    data-testid="input-login-email"
                  />
                </div>
                <Button
                  type="submit"
                  disabled={!email.trim()}
                  className="h-11 w-full gap-2"
                  data-testid="button-send-login-link"
                >
                  <Mail className="size-4" />
                  Email me a login link
                </Button>
                <p className="pt-2 text-center text-xs text-muted-foreground">
                  Don&apos;t have an account? <span className="font-medium text-foreground">Ask your account manager.</span>
                </p>
              </form>
            )}

            <div className="mt-12 flex items-center gap-2 text-[11px] uppercase tracking-[0.18em] text-muted-foreground">
              <span className="size-1 rounded-full bg-primary" />
              WordPress · /dashboard/login/
            </div>
          </div>

          {/* Right: image / brand panel */}
          <BackgroundImage
            url="https://images.unsplash.com/photo-1518173946687-a4c8892bbd9f?w=1400&q=80&auto=format&fit=crop"
            className="relative hidden bg-muted md:block"
            ariaHidden
          >
            <div className="absolute inset-0 bg-gradient-to-tr from-black/30 via-black/0 to-primary/20" />
            <div className="absolute bottom-8 left-8 right-8 rounded-lg border border-white/20 bg-white/10 p-5 backdrop-blur">
              <p className="text-xs uppercase tracking-[0.18em] text-white/80">Oversee · Client portal</p>
              <p className="mt-2 text-lg font-semibold leading-snug text-white">
                One place for everything we&apos;re building together.
              </p>
              <p className="mt-1.5 text-xs leading-relaxed text-white/80">
                Tasks, files, forms, contracts, messages, billing — all in one passwordless workspace.
              </p>
            </div>
          </BackgroundImage>
        </div>
      </div>
    </div>
  );
}
