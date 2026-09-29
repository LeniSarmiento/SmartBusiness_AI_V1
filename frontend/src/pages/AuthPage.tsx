import { useState, type FormEvent } from "react";
import { Link, Navigate, useNavigate, useSearchParams } from "react-router-dom";
import {
    ArrowRight,
    Boxes,
    ChartNoAxesCombined,
    Check,
    Layers3,
    ShieldCheck,
} from "lucide-react";
import { api, csrf, errorMessage } from "../lib/api";
import { useAuth } from "../lib/auth";
import { ErrorBox } from "../components/ui";

export default function AuthPage({
    mode = "login",
}: {
    mode?: "login" | "register" | "forgot" | "reset";
}) {
    const { user, login, refresh } = useAuth();
    const navigate = useNavigate();
    const [params] = useSearchParams();
    const [error, setError] = useState("");
    const [notice, setNotice] = useState("");
    const [busy, setBusy] = useState(false);
    if (user) return <Navigate to="/" replace />;
    const titles = {
        login: "Bienvenido de nuevo.",
        register: "Tu negocio empieza aquí.",
        forgot: "Recupera tu acceso.",
        reset: "Una nueva contraseña.",
    };
    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setError("");
        setNotice("");
        setBusy(true);
        const data = Object.fromEntries(new FormData(event.currentTarget));
        try {
            if (mode === "login") {
                await login(String(data.email), String(data.password));
                navigate("/");
            } else {
                await csrf();
                const endpoint =
                    mode === "register"
                        ? "register"
                        : mode === "forgot"
                          ? "forgot-password"
                          : "reset-password";
                const response = await api.post(`/auth/${endpoint}`, {
                    ...data,
                    ...(mode === "reset" ? { token: params.get("token") } : {}),
                });
                if (mode === "register") {
                    await refresh();
                    navigate("/");
                } else setNotice(response.data.message);
            }
        } catch (e) {
            setError(errorMessage(e));
        } finally {
            setBusy(false);
        }
    }
    return (
        <div className="auth-screen">
            <section className="auth-story">
                <Link className="brand" to="/">
                    <span className="brand-mark">
                        <Layers3 size={24} />
                    </span>
                    SmartBusiness<span className="version-tag">V1</span>
                </Link>
                <div className="auth-story-content">
                    <span className="pill-light">
                        EL SIGUIENTE PASO DE TU NEGOCIO
                    </span>
                    <h1>
                        Todo conectado.
                        <br />
                        Todo bajo control.
                    </h1>
                    <p>
                        Convierte tus operaciones diarias en decisiones claras.
                        Ventas, inventario y clientes, en un solo lugar.
                    </p>
                    <div className="story-cards">
                        <div>
                            <ChartNoAxesCombined />
                            <span>La visión completa</span>
                            <small>Conoce cómo crece tu negocio</small>
                        </div>
                        <div>
                            <Boxes />
                            <span>El stock que necesitas</span>
                            <small>Cada movimiento, registrado</small>
                        </div>
                    </div>
                    <div className="story-checks">
                        <span>
                            <Check size={16} /> Información en tiempo real
                        </span>
                        <span>
                            <ShieldCheck size={16} /> Acceso seguro por roles
                        </span>
                    </div>
                </div>
                <p className="auth-footnote">
                    Diseñado para negocios que miran hacia adelante.
                </p>
            </section>
            <section className="auth-form-side">
                <div className="auth-form-wrap">
                    <p className="eyebrow">SMARTBUSINESS WORKSPACE</p>
                    <h2>{titles[mode]}</h2>
                    <p className="muted">
                        {mode === "login"
                            ? "Accede a tu espacio de trabajo y sigue creciendo."
                            : mode === "register"
                              ? "Crea una empresa y tu cuenta de administrador."
                              : "Te ayudamos a volver a tu espacio de trabajo."}
                    </p>
                    <form onSubmit={submit}>
                        <ErrorBox message={error} />
                        {notice && (
                            <div role="status" className="success-box">
                                {notice}
                            </div>
                        )}
                        {mode === "register" && (
                            <>
                                <label>
                                    Tu nombre
                                    <input
                                        name="name"
                                        required
                                        maxLength={120}
                                        autoComplete="name"
                                    />
                                </label>
                                <label>
                                    Nombre de la empresa
                                    <input
                                        name="organization_name"
                                        required
                                        maxLength={120}
                                    />
                                </label>
                            </>
                        )}
                        <label>
                            Correo electrónico
                            <input
                                name="email"
                                type="email"
                                required
                                autoComplete="email"
                                placeholder="tu@empresa.com"
                                defaultValue={params.get("email") ?? ""}
                            />
                        </label>
                        {mode !== "forgot" && (
                            <label>
                                Contraseña
                                <input
                                    name="password"
                                    type="password"
                                    required
                                    maxLength={72}
                                    minLength={mode === "login" ? 1 : 12}
                                    autoComplete={
                                        mode === "login"
                                            ? "current-password"
                                            : "new-password"
                                    }
                                    placeholder="Tu contraseña"
                                />
                            </label>
                        )}
                        {(mode === "register" || mode === "reset") && (
                            <>
                                <small className="muted">
                                    Mínimo 12 caracteres, con mayúsculas,
                                    minúsculas y números.
                                </small>
                                <label>
                                    Confirmar contraseña
                                    <input
                                        name="password_confirmation"
                                        type="password"
                                        required
                                        minLength={12}
                                        maxLength={72}
                                        autoComplete="new-password"
                                    />
                                </label>
                            </>
                        )}
                        {mode === "login" && (
                            <Link className="forgot-link" to="/forgot-password">
                                ¿Olvidaste tu contraseña?
                            </Link>
                        )}
                        <button className="button primary full" disabled={busy}>
                            {busy
                                ? "Procesando…"
                                : mode === "login"
                                  ? "Iniciar sesión"
                                  : mode === "register"
                                    ? "Crear mi empresa"
                                    : mode === "forgot"
                                      ? "Enviar enlace"
                                      : "Actualizar contraseña"}
                            <ArrowRight size={18} />
                        </button>
                    </form>
                    <p className="auth-switch">
                        {mode === "login" ? (
                            <>
                                ¿Es tu primera vez?{" "}
                                <Link to="/register">Crea tu empresa</Link>
                            </>
                        ) : (
                            <Link to="/login">Volver a iniciar sesión</Link>
                        )}
                    </p>
                    <div className="secure-note">
                        <ShieldCheck size={15} /> Sesión protegida · Tus datos,
                        en tu empresa
                    </div>
                </div>
            </section>
        </div>
    );
}
