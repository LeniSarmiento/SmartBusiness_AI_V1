import { useEffect, useState, type FormEvent } from "react";
import { Plus, ShieldCheck } from "lucide-react";
import { api, errorMessage } from "../lib/api";
import { useAuth } from "../lib/auth";
import { Badge, ErrorBox, Modal, PageTitle, Spinner } from "../components/ui";

export function ProfilePage() {
    const { user, membership, refresh } = useAuth();
    const [error, setError] = useState("");
    const [notice, setNotice] = useState("");
    const [busy, setBusy] = useState(false);
    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setError("");
        setNotice("");
        setBusy(true);
        const form = event.currentTarget;
        try {
            await api.patch(
                "/auth/profile",
                Object.fromEntries(new FormData(form)),
            );
            await refresh();
            setNotice("Tu perfil se ha actualizado.");
            (
                form.elements.namedItem("current_password") as HTMLInputElement
            ).value = "";
        } catch (e) {
            setError(errorMessage(e));
        } finally {
            setBusy(false);
        }
    }
    return (
        <>
            <PageTitle
                eyebrow="TU ESPACIO"
                title="Mi perfil"
                description="Gestiona la información de tu cuenta."
            />
            <div className="settings-grid">
                <section className="panel padded">
                    <h2>Información personal</h2>
                    <form onSubmit={submit}>
                        <ErrorBox message={error} />
                        {notice && (
                            <div className="success-box" role="status">
                                {notice}
                            </div>
                        )}
                        <label>
                            Nombre
                            <input
                                name="name"
                                required
                                maxLength={120}
                                defaultValue={user!.name}
                            />
                        </label>
                        <label>
                            Correo electrónico
                            <input
                                name="email"
                                type="email"
                                required
                                defaultValue={user!.email}
                            />
                        </label>
                        <label>
                            Contraseña actual
                            <input
                                name="current_password"
                                type="password"
                                required
                                autoComplete="current-password"
                            />
                        </label>
                        <p className="form-hint">
                            Confirma tu contraseña para guardar cambios en la
                            cuenta.
                        </p>
                        <button className="button primary" disabled={busy}>
                            {busy ? "Guardando…" : "Guardar perfil"}
                        </button>
                    </form>
                </section>
                <section className="panel padded">
                    <ShieldCheck size={27} className="positive" />
                    <h2>Tu empresa</h2>
                    <div className="profile-details">
                        <span>Nombre</span>
                        <strong>{membership!.organization.name}</strong>
                        <span>Moneda</span>
                        <strong>{membership!.organization.currency}</strong>
                        <span>Zona horaria</span>
                        <strong>{membership!.organization.timezone}</strong>
                        <span>Tu rol</span>
                        <Badge tone="green">
                            {roleLabel[membership!.role]}
                        </Badge>
                    </div>
                    <p className="form-hint">
                        Tus permisos se aplican únicamente a esta empresa. Los
                        datos están aislados de otras organizaciones.
                    </p>
                </section>
            </div>
        </>
    );
}

export const roleLabel: Record<string, string> = {
    admin: "Administrador",
    manager: "Gerente",
    seller: "Vendedor",
};
type Member = { id: number; name: string; email: string; role: string };
export function MembersPage() {
    const { membership, refresh } = useAuth();
    const base = `/organizations/${membership!.organization.id}/members`;
    const [members, setMembers] = useState<Member[] | null>(null);
    const [error, setError] = useState("");
    const [show, setShow] = useState(false);
    const [busy, setBusy] = useState(false);
    async function load() {
        setMembers((await api.get<{ data: Member[] }>(base)).data.data);
    }
    useEffect(() => {
        load().catch((e) => setError(errorMessage(e)));
    }, [base]);
    async function changeRole(id: number, role: string) {
        setError("");
        setBusy(true);
        try {
            await api.patch(`${base}/${id}`, { role });
            await load();
            await refresh();
        } catch (e) {
            setError(errorMessage(e));
        } finally {
            setBusy(false);
        }
    }
    return (
        <>
            <PageTitle
                eyebrow="PERSONAS Y PERMISOS"
                title="Equipo"
                description="Cada persona, con el acceso que necesita."
                action={
                    <button
                        className="button primary"
                        onClick={() => setShow(true)}
                    >
                        <Plus size={18} /> Crear usuario
                    </button>
                }
            />
            <ErrorBox message={error} />
            <section className="panel">
                {!members ? (
                    <Spinner />
                ) : (
                    <div className="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Correo</th>
                                    <th>Rol en la empresa</th>
                                </tr>
                            </thead>
                            <tbody>
                                {members.map((member) => (
                                    <tr key={member.id}>
                                        <td className="table-main">
                                            {member.name}
                                        </td>
                                        <td>{member.email}</td>
                                        <td>
                                            <select
                                                aria-label={`Rol de ${member.name}`}
                                                disabled={busy}
                                                value={member.role}
                                                onChange={(e) =>
                                                    changeRole(
                                                        member.id,
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                {Object.entries(roleLabel).map(
                                                    ([value, label]) => (
                                                        <option
                                                            key={value}
                                                            value={value}
                                                        >
                                                            {label}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
            <div className="role-cards">
                <article>
                    <h3>Administrador</h3>
                    <p>Gestiona toda la empresa, los usuarios y sus roles.</p>
                </article>
                <article>
                    <h3>Gerente</h3>
                    <p>Administra catálogo, inventario, ventas y reportes.</p>
                </article>
                <article>
                    <h3>Vendedor</h3>
                    <p>
                        Consulta productos, gestiona clientes y registra ventas.
                    </p>
                </article>
            </div>
            {show && (
                <NewMember
                    base={base}
                    onClose={() => setShow(false)}
                    onSaved={() => {
                        setShow(false);
                        load().catch((e) => setError(errorMessage(e)));
                    }}
                />
            )}
        </>
    );
}
function NewMember({
    base,
    onClose,
    onSaved,
}: {
    base: string;
    onClose: () => void;
    onSaved: () => void;
}) {
    const [error, setError] = useState("");
    const [busy, setBusy] = useState(false);
    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setBusy(true);
        setError("");
        try {
            await api.post(
                base,
                Object.fromEntries(new FormData(event.currentTarget)),
            );
            onSaved();
        } catch (e) {
            setError(errorMessage(e));
        } finally {
            setBusy(false);
        }
    }
    return (
        <Modal
            title="Crear usuario del equipo"
            onClose={() => !busy && onClose()}
        >
            <form onSubmit={submit}>
                <ErrorBox message={error} />
                <label>
                    Nombre
                    <input name="name" required maxLength={120} />
                </label>
                <label>
                    Correo electrónico
                    <input name="email" type="email" required />
                </label>
                <label>
                    Contraseña inicial
                    <input
                        name="password"
                        type="password"
                        autoComplete="new-password"
                        required
                        minLength={12}
                        maxLength={72}
                    />
                </label>
                <small className="muted">
                    Mínimo 12 caracteres, mayúsculas, minúsculas y números.
                </small>
                <label>
                    Rol
                    <select name="role" defaultValue="seller">
                        {Object.entries(roleLabel).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                </label>
                <p className="form-hint">
                    Usa un correo nuevo. La incorporación de cuentas existentes
                    por invitación queda para una próxima versión.
                </p>
                <div className="modal-actions">
                    <button
                        className="button secondary"
                        type="button"
                        disabled={busy}
                        onClick={onClose}
                    >
                        Cancelar
                    </button>
                    <button className="button primary" disabled={busy}>
                        {busy ? "Creando…" : "Crear usuario"}
                    </button>
                </div>
            </form>
        </Modal>
    );
}
