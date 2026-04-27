import { Route, Routes, useLocation } from "react-router-dom";
import { Sidebar } from "./components/Sidebar";
import { Topbar } from "./components/Topbar";
import { Home } from "./pages/Home";
import { Projects } from "./pages/Projects";
import { BoardDetail } from "./pages/BoardDetail";
import {
    // Client (12)
    Tasks, Files, Forms, Contracts, Messages, Schedule, Reports, Reviews,
    BrowseServices, Subscriptions, Billing,
    // Admin (15)
    AdminToday, AdminClients, AdminProjects, AdminTasks, AdminMessages,
    AdminOrders, AdminSubscriptions, AdminServiceTemplates, AdminServiceCatalog, AdminPayments,
    AdminForms, AdminContracts, AdminFiles, AdminAutomations,
    AdminTeam, AdminSettings,
} from "./pages/SimplePages";
import { isAdmin } from "./lib/api";

export function App() {
    const location = useLocation();
    const breadcrumb = humanizePath(location.pathname);
    const isAdminRoute = location.pathname.startsWith("/admin");

    return (
        <div className="flex min-h-screen" style={{ background: "var(--oversee-bg)" }}>
            <Sidebar admin={isAdminRoute && isAdmin} />
            <div className="flex-1 flex flex-col" style={{ paddingRight: 16, paddingBottom: 16 }}>
                <Topbar breadcrumb={breadcrumb} />
                <main className="flex-1 mt-4">
                    <Routes>
                        {/* Client (12 nav items + projects detail) */}
                        <Route path="/" element={<Home />} />
                        <Route path="/tasks" element={<Tasks />} />
                        <Route path="/files" element={<Files />} />
                        <Route path="/forms" element={<Forms />} />
                        <Route path="/contracts" element={<Contracts />} />
                        <Route path="/messages" element={<Messages />} />
                        <Route path="/schedule" element={<Schedule />} />
                        <Route path="/reports" element={<Reports />} />
                        <Route path="/reviews" element={<Reviews />} />
                        <Route path="/services" element={<BrowseServices />} />
                        <Route path="/subscriptions" element={<Subscriptions />} />
                        <Route path="/billing" element={<Billing />} />
                        {/* Project workspace (drill-down) */}
                        <Route path="/projects" element={<Projects />} />
                        <Route path="/projects/:id" element={<BoardDetail />} />
                        {/* Admin (15) */}
                        <Route path="/admin" element={<AdminToday />} />
                        <Route path="/admin/clients" element={<AdminClients />} />
                        <Route path="/admin/projects" element={<AdminProjects />} />
                        <Route path="/admin/tasks" element={<AdminTasks />} />
                        <Route path="/admin/messages" element={<AdminMessages />} />
                        <Route path="/admin/orders" element={<AdminOrders />} />
                        <Route path="/admin/subscriptions" element={<AdminSubscriptions />} />
                        <Route path="/admin/service-templates" element={<AdminServiceTemplates />} />
                        <Route path="/admin/service-catalog" element={<AdminServiceCatalog />} />
                        <Route path="/admin/payments" element={<AdminPayments />} />
                        <Route path="/admin/forms" element={<AdminForms />} />
                        <Route path="/admin/contracts" element={<AdminContracts />} />
                        <Route path="/admin/files" element={<AdminFiles />} />
                        <Route path="/admin/automations" element={<AdminAutomations />} />
                        <Route path="/admin/team" element={<AdminTeam />} />
                        <Route path="/admin/settings" element={<AdminSettings />} />
                        <Route path="*" element={<NotFound />} />
                    </Routes>
                </main>
            </div>
        </div>
    );
}

function NotFound() {
    return <div className="oversee-card p-6 text-sm text-zinc-500">Page not found.</div>;
}

function humanizePath(pathname: string): string {
    const parts = pathname.split("/").filter(Boolean);
    if (parts.length === 0) return "Home";
    return parts.map((p) => p.charAt(0).toUpperCase() + p.slice(1).replaceAll("-", " ")).join(" / ");
}
