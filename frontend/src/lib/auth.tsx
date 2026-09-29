import {
    createContext,
    useContext,
    useEffect,
    useState,
    type ReactNode,
} from "react";
import { api, csrf } from "./api";
import type { Membership, User } from "../types";

type AuthValue = {
    user: User | null;
    loading: boolean;
    membership: Membership | null;
    setOrganization: (id: number) => void;
    refresh: () => Promise<void>;
    login: (email: string, password: string) => Promise<void>;
    logout: () => Promise<void>;
    can: (permission: string) => boolean;
};
const AuthContext = createContext<AuthValue | null>(null);
export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<User | null>(null);
    const [loading, setLoading] = useState(true);
    const [organizationId, setOrganizationId] = useState(() =>
        Number(localStorage.getItem("smartbusiness.organization")),
    );
    async function refresh() {
        const response = await api.get<{ data: User }>("/auth/me");
        setUser(response.data.data);
    }
    useEffect(() => {
        refresh()
            .catch(() => setUser(null))
            .finally(() => setLoading(false));
    }, []);
    const membership =
        user?.memberships.find((m) => m.organization.id === organizationId) ??
        user?.memberships[0] ??
        null;
    async function login(email: string, password: string) {
        await csrf();
        const response = await api.post<{ data: User }>("/auth/login", {
            email,
            password,
        });
        setUser(response.data.data);
    }
    async function logout() {
        await api.post("/auth/logout");
        setUser(null);
    }
    function setOrganization(id: number) {
        localStorage.setItem("smartbusiness.organization", String(id));
        setOrganizationId(id);
    }
    return (
        <AuthContext.Provider
            value={{
                user,
                loading,
                membership,
                setOrganization,
                refresh,
                login,
                logout,
                can: (permission) =>
                    membership?.permissions.includes(permission) ?? false,
            }}
        >
            {children}
        </AuthContext.Provider>
    );
}
export function useAuth() {
    const context = useContext(AuthContext);
    if (!context) throw new Error("AuthProvider is required");
    return context;
}
