// Lightweight assertion-style tests for resolveBootRoute. Run with:
//   cd spa && npx tsx src/lib/boot.test.mjs
// tsx transpiles the imported boot.ts on the fly. The harness is intentionally
// dependency-free (only Node's built-in assert) so CI doesn't need a runner.

import assert from "node:assert/strict";
import { resolveBootRoute, deriveRole } from "./boot";

let passed = 0;
function it(label, fn) {
  fn();
  console.log(`  ok ${label}`);
  passed++;
}

it("guest user with empty hash boots to login", () => {
  const r = resolveBootRoute({
    config: { isLoggedIn: false },
    mountRole: null,
    search: "",
    currentHash: "",
  });
  assert.equal(r, "#/login");
});

it("admin user boots to /#/admin", () => {
  const r = resolveBootRoute({
    config: { isLoggedIn: true, isAdmin: true, role: "admin" },
    mountRole: null,
    search: "",
    currentHash: "#/",
  });
  assert.equal(r, "#/admin");
});

it("customer user boots to /#/client", () => {
  const r = resolveBootRoute({
    config: { isLoggedIn: true, isAdmin: false, role: "customer" },
    mountRole: null,
    search: "",
    currentHash: "",
  });
  assert.equal(r, "#/client");
});

it("?preview=1 keeps the welcome surface accessible", () => {
  const r = resolveBootRoute({
    config: { isLoggedIn: true, isAdmin: true, role: "admin" },
    mountRole: null,
    search: "?preview=1",
    currentHash: "",
  });
  assert.equal(r, "#/welcome");
});

it("data-ocd-role on the mount overrides config (admin shortcode case)", () => {
  const r = resolveBootRoute({
    config: { isLoggedIn: true, isAdmin: false, role: "customer" },
    mountRole: "admin",
    search: "",
    currentHash: "",
  });
  assert.equal(r, "#/admin");
});

it("deep links into matching surface are preserved", () => {
  const r = resolveBootRoute({
    config: { isLoggedIn: true, isAdmin: true, role: "admin" },
    mountRole: null,
    search: "",
    currentHash: "#/admin/boards/foo",
  });
  assert.equal(r, "#/admin/boards/foo");
});

it("deep link into wrong surface is overridden by role", () => {
  const r = resolveBootRoute({
    config: { isLoggedIn: true, isAdmin: false, role: "customer" },
    mountRole: null,
    search: "",
    currentHash: "#/admin/boards/foo",
  });
  assert.equal(r, "#/client");
});

it("deriveRole short-circuits on explicit mountRole", () => {
  assert.equal(deriveRole({ config: undefined, mountRole: "admin" }), "admin");
  assert.equal(deriveRole({ config: { isLoggedIn: true }, mountRole: "guest" }), "guest");
});

console.log(`\n${passed} assertions passed.`);
