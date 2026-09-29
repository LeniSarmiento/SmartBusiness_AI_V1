import { lazy, Suspense, useState, type ReactNode } from "react";
import {
    Navigate,
    NavLink,
    Outlet,
    Route,
    Routes,
    useLocation,
} from "react-router-dom";
import {
    ArrowUpRight,
    Boxes,
    Building2,
    ChevronDown,
    CircleHelp,
    ContactRound,
    FolderTree,
    Layers3,
    LayoutDashboard,
    LogOut,
    Menu,
    Package,
    ReceiptText,
    ShieldCheck,
    Truck,
    UserRound,
    Users,
    X,
} from "lucide-react";
import { useAuth } from "./lib/auth";
import { errorMessage } from "./lib/api";
import { ErrorBox, Spinner } from "./components/ui";
import AuthPage from "./pages/AuthPage";
import CatalogPage from "./pages/CatalogPage";
import InventoryPage from "./pages/InventoryPage";
import { NewSalePage, SaleDetailPage, SalesPage } from "./pages/SalesPage";
import { MembersPage, ProfilePage, roleLabel } from "./pages/SettingsPage";

const Dashboard = lazy(() => import("./pages/Dashboard"));
const nav = [
    {
        to: "/",
        label: "Resumen general",
        icon: LayoutDashboard,
        permission: "reports.read",
        group: "PRINCIPAL",
    },
    {
        to: "/sales",
        label: "Ventas",
        icon: ReceiptText,
        permission: "sales.read",
        group: "PRINCIPAL",
    },
    {
        to: "/customers",
        label: "Clientes",
        icon: ContactRound,
        permission: "customers.read",
        group: "PRINCIPAL",
    },
    {
        to: "/products",
        label: "Productos",
        icon: Package,
        permission: "products.read",
        group: "OPERACIÓN",
    },
    {
        to: "/inventory",
        label: "Inventario",
        icon: Boxes,
        permission: "inventory.read",
        group: "OPERACIÓN",
    },
    {
        to: "/suppliers",
        label: "Proveedores",
        icon: Truck,
        permission: "suppliers.read",
        group: "OPERACIÓN",
    },
    {
        to: "/categories",
        label: "Categorías",
        icon: FolderTree,
        permission: "categories.read",
        group: "OPERACIÓN",
    },
    {
        to: "/members",
        label: "Equipo",
        icon: Users,
        permission: "members.manage",
        group: "ADMINISTRACIÓN",
    },
    {
        to: "/profile",
        label: "Mi perfil",
        icon: UserRound,
        permission: "",
        group: "ADMINISTRACIÓN",
    },
];
function Allowed({
    permission,
    children,
}: {
    permission: string;
    children: ReactNode;
}) {
    const { can } = useAuth();
    return can(permission) ? children : <Navigate to="/profile" replace />;
}
function Home() {
    const { can } = useAuth();
    return can("reports.read") ? (
        <Dashboard />
    ) : (
        <Navigate to="/sales" replace />
    );
}
function Layout() {
    const { user, loading, membership, setOrganization, can, logout } =
        useAuth();
    const location = useLocation();
    const [open, setOpen] = useState(false);
    const [error, setError] = useState("");
    if (loading) return <Spinner />;
    if (!user) return <Navigate to="/login" replace />;
    if (!membership)
        return <ErrorBox message="Tu cuenta no tiene una empresa asignada." />;
    const visibleNav = nav.filter(
        (item) => !item.permission || can(item.permission),
    );
    const activeTitle =
        [...nav]
            .reverse()
            .find(
                (item) =>
                    item.to !== "/" && location.pathname.startsWith(item.to),
            )?.label ?? "Resumen general";
    return (
        <div className="app-shell">
            {open && (
                <button
                    className="sidebar-backdrop"
                    aria-label="Cerrar menú"
                    onClick={() => setOpen(false)}
                />
            )}
            <aside className={`sidebar ${open ? "open" : ""}`}>
                <div className="sidebar-brand">
                    <NavLink to="/" className="brand">
                        <span className="brand-mark">
                            <Layers3 size={22} />
                        </span>
                        SmartBusiness<span className="version-tag">V1</span>
                    </NavLink>
                    <button
                        className="mobile-only icon-button"
                        aria-label="Cerrar menú"
                        onClick={() => setOpen(false)}
                    >
                        <X size={20} />
                    </button>
                </div>
                <div className="workspace-picker">
                    <span className="workspace-icon">
                        <Building2 size={19} />
                    </span>
                    <div>
                        <small>ESPACIO DE TRABAJO</small>
                        <select
                            aria-label="Seleccionar empresa"
                            value={membership.organization.id}
                            onChange={(e) => {
                                setOrganization(Number(e.target.value));
                                setOpen(false);
                            }}
                        >
                            {user.memberships.map((m) => (
                                <option key={m.id} value={m.organization.id}>
                                    {m.organization.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <ChevronDown size={14} />
                </div>
                <nav aria-label="Navegación principal">
                    {visibleNav.map((item, index) => (
                        <div key={item.to}>
                            {(index === 0 ||
                                visibleNav[index - 1].group !== item.group) && (
                                <p className="nav-heading">{item.group}</p>
                            )}
                            <NavLink
                                to={item.to}
                                end={item.to === "/"}
                                onClick={() => setOpen(false)}
                                className={({ isActive }) =>
                                    `nav-link ${isActive ? "active" : ""}`
                                }
                            >
                                <item.icon size={19} />
                                <span>{item.label}</span>
                                {item.to === "/" && (
                                    <span className="nav-active-dot" />
                                )}
                            </NavLink>
                        </div>
                    ))}
                </nav>
                <div className="sidebar-bottom">
                    <div className="workspace-status">
                        <ShieldCheck size={18} />
                        <div>
                            <strong>Tu negocio está conectado</strong>
                            <small>Acceso seguro · Datos privados</small>
                        </div>
                    </div>
                    <div className="sidebar-user">
                        <span className="avatar">
                            {user.name
                                .split(" ")
                                .slice(0, 2)
                                .map((part) => part[0])
                                .join("")}
                        </span>
                        <div>
                            <strong>{user.name}</strong>
                            <small>{roleLabel[membership.role]}</small>
                        </div>
                        <button
                            className="icon-button"
                            aria-label="Cerrar sesión"
                            onClick={() =>
                                logout().catch((e) => setError(errorMessage(e)))
                            }
                        >
                            <LogOut size={18} />
                        </button>
                    </div>
                </div>
            </aside>
            <div className="main-shell">
                <header className="topbar">
                    <div className="breadcrumb">
                        <button
                            className="mobile-only icon-button"
                            aria-label="Abrir menú"
                            onClick={() => setOpen(true)}
                        >
                            <Menu size={21} />
                        </button>
                        <span>Workspace</span>
                        <span className="breadcrumb-divider">/</span>
                        <strong>{activeTitle}</strong>
                    </div>
                    <div className="topbar-right">
                        <span className="environment">
                            <span className="tiny-dot green" /> V1 · Gestión
                            empresarial
                        </span>
                        <NavLink
                            className="icon-button"
                            to="/profile"
                            aria-label="Ver mi perfil"
                        >
                            <UserRound size={19} />
                        </NavLink>
                    </div>
                </header>
                <main key={membership.organization.id}>
                    <ErrorBox message={error} />
                    <Suspense fallback={<Spinner />}>
                        <Outlet />
                    </Suspense>
                </main>
                <footer className="app-footer">
                    <span>SmartBusiness · Una visión clara de tu negocio.</span>
                    <span>
                        <CircleHelp size={13} /> API V1{" "}
                        <ArrowUpRight size={13} />
                    </span>
                </footer>
            </div>
        </div>
    );
}
export default function App() {
    return (
        <Routes>
            <Route path="/login" element={<AuthPage key="login" />} />
            <Route
                path="/register"
                element={<AuthPage key="register" mode="register" />}
            />
            <Route
                path="/forgot-password"
                element={<AuthPage key="forgot" mode="forgot" />}
            />
            <Route
                path="/reset-password"
                element={<AuthPage key="reset" mode="reset" />}
            />
            <Route element={<Layout />}>
                <Route index element={<Home />} />
                {(
                    [
                        "customers",
                        "suppliers",
                        "categories",
                        "products",
                    ] as const
                ).map((kind) => (
                    <Route
                        key={kind}
                        path={kind}
                        element={
                            <Allowed permission={`${kind}.read`}>
                                <CatalogPage key={kind} kind={kind} />
                            </Allowed>
                        }
                    />
                ))}
                <Route
                    path="inventory"
                    element={
                        <Allowed permission="inventory.read">
                            <InventoryPage />
                        </Allowed>
                    }
                />
                <Route
                    path="sales"
                    element={
                        <Allowed permission="sales.read">
                            <SalesPage />
                        </Allowed>
                    }
                />
                <Route
                    path="sales/new"
                    element={
                        <Allowed permission="sales.write">
                            <NewSalePage />
                        </Allowed>
                    }
                />
                <Route
                    path="sales/:id"
                    element={
                        <Allowed permission="sales.read">
                            <SaleDetailPage />
                        </Allowed>
                    }
                />
                <Route
                    path="members"
                    element={
                        <Allowed permission="members.manage">
                            <MembersPage />
                        </Allowed>
                    }
                />
                <Route path="profile" element={<ProfilePage />} />
                <Route path="*" element={<Navigate to="/" replace />} />
            </Route>
        </Routes>
    );
}
