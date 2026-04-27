import { Route, Routes, useLocation } from "react-router-dom";
import { Sidebar } from "./components/Sidebar";
import { Topbar } from "./components/Topbar";
import { Home } from "./pages/Home";
import { Projects } from "./pages/Projects";
import { BoardDetail } from "./pages/BoardDetail";
import {
    Messages, ScheduleCall, PerformanceReports, Documents, Reviews,
    Files, Knowledge, Services, Billing, Account,
    AdminHome, AdminClients, AdminBoards, AdminSpecialists, AdminOrders,
    AdminSubscriptions, AdminCatalog, AdminTemplates, AdminAutomations,
    AdminKnowledge, AdminHighLevel, AdminSettings, AdminActivity,
} from "./pages/SimplePages";
import { isAdmin } from "./lib/api";

export function App() {
    const location = useLocation();
    const breadcrumb = humanizePath(location.pathname);

    return (
        <div className="flex min-h-screen" style={{ background: "var(--oversee-bg)" }}>
            <Sidebar admin={location.pathname.startsWith("/admin") && isAdmin} />
            <div className="flex-1 flex flex-col" style={{ paddingRight: 16, paddingBottom: 16 }}>
                <Topbar breadcrumb={breadcrumb} />
                <main className="flex-1 mt-4">
                    <Routes>
                        <Route path="/" element={<Home />} />
                        <Route path="/projects" element={<Projects />} />
                        <Route path="/projects/:id" element={<BoardDetail />} />
                        <Route path="/files" element={<Files />} />
                        <Route path="/messages" element={<Messages />} />
                        <Route path="/schedule-call" element={<ScheduleCall />} />
                        <Route path="/performance-reports" element={<PerformanceReports />} />
                        <Route path="/documents" element={<Documents />} />
                        <Route path="/reviews" element={<Reviews />} />
                        <Route path="/knowledge" element={<Knowledge />} />
                        <Route path="/services" element={<Services />} />
                        <Route path="/billing" element={<Billing />} />
                        <Route path="/account" element={<Account />} />
                        {/* Admin */}
                        <Route path="/admin" element={<AdminHome />} />
                        <Route path="/admin/clients" element={<AdminClients />} />
                        <Route path="/admin/boards" element={<AdminBoards />} />
                        <Route path="/admin/specialists" element={<AdminSpecialists />} />
                        <Route path="/admin/orders" element={<AdminOrders />} />
                        <Route path="/admin/subscriptions" element={<AdminSubscriptions />} />
                        <Route path="/admin/catalog" element={<AdminCatalog />} />
                        <Route path="/admin/templates" element={<AdminTemplates />} />
                        <Route path="/admin/automations" element={<AdminAutomations />} />
                        <Route path="/admin/knowledge" element={<AdminKnowledge />} />
                        <Route path="/admin/highlevel" element={<AdminHighLevel />} />
                        <Route path="/admin/settings" element={<AdminSettings />} />
                        <Route path="/admin/activity" element={<AdminActivity />} />
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
