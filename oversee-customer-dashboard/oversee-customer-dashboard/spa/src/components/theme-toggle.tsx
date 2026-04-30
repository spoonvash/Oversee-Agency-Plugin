import { useEffect, useState } from "react";
import { Moon, Sun } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";

// In-memory only. Toggles `dark` class on <html>. No localStorage/cookies.
let listeners: Array<(v: boolean) => void> = [];
let currentDark = false;

export function setDark(v: boolean) {
  currentDark = v;
  document.documentElement.classList.toggle("dark", v);
  listeners.forEach((l) => l(v));
}

export function useDark() {
  const [v, setV] = useState(currentDark);
  useEffect(() => {
    const listener = (val: boolean) => setV(val);
    listeners.push(listener);
    return () => {
      listeners = listeners.filter((l) => l !== listener);
    };
  }, []);
  return v;
}

export function ThemeToggle() {
  const dark = useDark();
  return (
    <TooltipProvider>
      <Tooltip>
        <TooltipTrigger asChild>
          <Button
            variant="ghost"
            size="icon"
            onClick={() => setDark(!dark)}
            aria-label="Toggle dark mode"
            title={dark ? "Switch to light mode" : "Switch to dark mode"}
            data-testid="button-theme-toggle"
            className="size-10"
          >
            {dark ? <Sun className="size-[18px]" /> : <Moon className="size-[18px]" />}
          </Button>
        </TooltipTrigger>
        <TooltipContent>{dark ? "Switch to light mode" : "Switch to dark mode"}</TooltipContent>
      </Tooltip>
    </TooltipProvider>
  );
}

export function initTheme() {
  // Default to light. User toggles in-session only.
  setDark(false);
}
