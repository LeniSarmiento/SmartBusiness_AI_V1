import { useEffect, useState, type FormEvent } from "react";
import { useSearchParams } from "react-router-dom";
import { Boxes, Pencil, Plus, Search, Trash2, History } from "lucide-react";
import { api, date, errorMessage, money } from "../lib/api";
import { useAuth } from "../lib/auth";
import { allRows, useList } from "../lib/useList";
import {
    Badge,
    Empty,
    ErrorBox,
    Modal,
    PageTitle,
    Pagination,
    Spinner,
} from "../components/ui";
import type { Pagination as PageData, Product, Row, Sale } from "../types";

type Kind = "customers" | "suppliers" | "categories" | "products";
type Field = {
    name: string;
    label: string;
    type?: string;
    required?: boolean;
    maxLength?: number;
};
const contactFields: Field[] = [
    { name: "name", label: "Nombre", required: true, maxLength: 255 },
    { name: "identification", label: "Identificación", maxLength: 50 },
    { name: "email", label: "Correo electrónico", type: "email" },
    { name: "phone", label: "Teléfono", maxLength: 40 },
    { name: "address", label: "Dirección", maxLength: 500 },
];
const config: Record<
    Kind,
    { title: string; singular: string; description: string; fields: Field[] }
> = {
    customers: {
        title: "Clientes",
        singular: "cliente",
        description: "Conoce a las personas que hacen crecer tu negocio.",
        fields: contactFields,
    },
    suppliers: {
        title: "Proveedores",
        singular: "proveedor",
        description: "Una buena operación empieza con buenos aliados.",
        fields: contactFields,
    },
    categories: {
        title: "Categorías",
        singular: "categoría",
        description: "Un catálogo organizado es más fácil de gestionar.",
        fields: [
            { name: "name", label: "Nombre", required: true },
            { name: "description", label: "Descripción", maxLength: 500 },
        ],
    },
    products: {
        title: "Productos",
        singular: "producto",
        description: "Todo tu catálogo, con precios y stock al día.",
        fields: [
            { name: "name", label: "Nombre del producto", required: true },
            { name: "sku", label: "SKU", required: true, maxLength: 80 },
            { name: "code", label: "Código de barras", maxLength: 80 },
            {
                name: "purchase_price",
                label: "Precio de compra",
                required: true,
                type: "money",
            },
            {
                name: "sale_price",
                label: "Precio de venta (sin impuesto)",
                required: true,
                type: "money",
            },
            {
                name: "tax_rate_bps",
                label: "Impuesto (puntos base: 1900 = 19%)",
                type: "number",
            },
            { name: "minimum_stock", label: "Stock mínimo", type: "number" },
            { name: "description", label: "Descripción", maxLength: 2000 },
        ],
    },
};

export default function CatalogPage({ kind }: { kind: Kind }) {
    const cfg = config[kind];
    const { can, membership } = useAuth();
    const currency = membership!.organization.currency;
    const [params, setParams] = useSearchParams();
    const lowStock = kind === "products" && params.get("low_stock") === "1";
    const list = useList<Row>(kind, lowStock ? "&low_stock=1" : "");
    const [editing, setEditing] = useState<Row | "new" | null>(null);
    const [deleting, setDeleting] = useState<Row | null>(null);
    const [actionError, setActionError] = useState("");
    const [busy, setBusy] = useState(false);
    const [history, setHistory] = useState<Row | null>(null);
    const editable = can(`${kind}.write`);
    async function remove() {
        if (!deleting) return;
        setBusy(true);
        setActionError("");
        try {
            await api.delete(`${list.base}/${kind}/${deleting.id}`);
            setDeleting(null);
            list.refresh();
        } catch (e) {
            setActionError(errorMessage(e));
        } finally {
            setBusy(false);
        }
    }
    return (
        <>
            <PageTitle
                eyebrow={
                    kind === "customers"
                        ? "RELACIONES QUE IMPORTAN"
                        : "OPERACIÓN"
                }
                title={cfg.title}
                description={cfg.description}
                action={
                    editable && (
                        <button
                            className="button primary"
                            onClick={() => setEditing("new")}
                        >
                            <Plus size={18} /> Crear {cfg.singular}
                        </button>
                    )
                }
            />
            <section className="panel">
                <div className="list-toolbar">
                    <div className="search-input">
                        <Search size={18} />
                        <input
                            aria-label={`Buscar ${cfg.title.toLowerCase()}`}
                            placeholder={`Buscar ${cfg.title.toLowerCase()}…`}
                            value={list.search}
                            onChange={(e) => list.setSearch(e.target.value)}
                        />
                    </div>
                    {kind === "products" && (
                        <button
                            className={`button ${lowStock ? "soft" : "secondary"}`}
                            onClick={() => {
                                setParams(lowStock ? {} : { low_stock: "1" });
                                list.setPage(1);
                            }}
                        >
                            <Boxes size={16} />
                            {lowStock ? "Mostrando stock bajo" : "Stock bajo"}
                        </button>
                    )}
                    <span className="count-label">
                        {list.data?.meta.total ?? 0} registros
                    </span>
                </div>
                <ErrorBox message={list.error} />
                {list.loading ? (
                    <Spinner />
                ) : !list.data?.data.length ? (
                    <Empty
                        title={
                            list.search
                                ? "No encontramos coincidencias"
                                : `Tu lista de ${cfg.title.toLowerCase()} está vacía`
                        }
                        description="Crea un registro o prueba otra búsqueda."
                    />
                ) : (
                    <div className="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>
                                        {kind === "products"
                                            ? "Producto / SKU"
                                            : "Nombre"}
                                    </th>
                                    {kind === "products" ? (
                                        <>
                                            <th>Categoría</th>
                                            <th>Precio de venta</th>
                                            <th>Stock</th>
                                        </>
                                    ) : kind === "categories" ? (
                                        <th>Descripción</th>
                                    ) : (
                                        <>
                                            <th>Correo electrónico</th>
                                            <th>Teléfono</th>
                                        </>
                                    )}
                                    <th>Estado</th>
                                    <th className="text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.data.data.map((row) => (
                                    <tr key={row.id}>
                                        <td>
                                            <strong className="table-main">
                                                {row.name}
                                            </strong>
                                            <small>
                                                {String(
                                                    row.sku ??
                                                        row.identification ??
                                                        "",
                                                )}
                                            </small>
                                        </td>
                                        {kind === "products" ? (
                                            <>
                                                <td>
                                                    {(row as Product).category
                                                        ?.name ??
                                                        "Sin categoría"}
                                                </td>
                                                <td className="nowrap">
                                                    {money(
                                                        String(row.sale_price),
                                                        currency,
                                                    )}
                                                </td>
                                                <td>
                                                    <Badge
                                                        tone={
                                                            Number(
                                                                row.current_stock,
                                                            ) <=
                                                            Number(
                                                                row.minimum_stock,
                                                            )
                                                                ? "orange"
                                                                : "green"
                                                        }
                                                    >
                                                        {String(
                                                            row.current_stock,
                                                        )}{" "}
                                                        uds.
                                                    </Badge>
                                                </td>
                                            </>
                                        ) : kind === "categories" ? (
                                            <td>
                                                {String(row.description ?? "—")}
                                            </td>
                                        ) : (
                                            <>
                                                <td>
                                                    {String(row.email ?? "—")}
                                                </td>
                                                <td>
                                                    {String(row.phone ?? "—")}
                                                </td>
                                            </>
                                        )}
                                        <td>
                                            <Badge
                                                tone={
                                                    row.active
                                                        ? "green"
                                                        : "neutral"
                                                }
                                            >
                                                {row.active
                                                    ? "Activo"
                                                    : "Inactivo"}
                                            </Badge>
                                        </td>
                                        <td>
                                            <div className="row-actions">
                                                {(kind === "customers" ||
                                                    kind === "suppliers") && (
                                                    <button
                                                        className="icon-button"
                                                        aria-label={`Ver ${kind === "customers" ? "compras" : "productos"} de ${row.name}`}
                                                        onClick={() =>
                                                            setHistory(row)
                                                        }
                                                    >
                                                        <History size={16} />
                                                    </button>
                                                )}
                                                {editable && (
                                                    <>
                                                        <button
                                                            className="icon-button"
                                                            aria-label={`Editar ${row.name}`}
                                                            onClick={() =>
                                                                setEditing(row)
                                                            }
                                                        >
                                                            <Pencil size={16} />
                                                        </button>
                                                        <button
                                                            className="icon-button danger"
                                                            aria-label={`Archivar ${row.name}`}
                                                            onClick={() => {
                                                                setActionError(
                                                                    "",
                                                                );
                                                                setDeleting(
                                                                    row,
                                                                );
                                                            }}
                                                        >
                                                            <Trash2 size={16} />
                                                        </button>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                {list.data && (
                    <Pagination
                        page={list.page}
                        last={list.data.meta.last_page}
                        total={list.data.meta.total}
                        onChange={list.setPage}
                    />
                )}
            </section>
            {editing && (
                <CatalogForm
                    kind={kind}
                    row={editing}
                    base={list.base}
                    onClose={() => setEditing(null)}
                    onSaved={() => {
                        setEditing(null);
                        list.refresh();
                    }}
                />
            )}
            {deleting && (
                <Modal
                    title={`Archivar ${cfg.singular}`}
                    onClose={() => !busy && setDeleting(null)}
                >
                    <p>
                        ¿Quieres archivar <strong>{deleting.name}</strong>? Se
                        conservarán las ventas y los movimientos históricos.
                    </p>
                    <ErrorBox message={actionError} />
                    <div className="modal-actions">
                        <button
                            className="button secondary"
                            onClick={() => setDeleting(null)}
                            disabled={busy}
                        >
                            Volver
                        </button>
                        <button
                            className="button destructive"
                            onClick={remove}
                            disabled={busy}
                        >
                            {busy ? "Archivando…" : "Archivar"}
                        </button>
                    </div>
                </Modal>
            )}
            {history && (
                <RelatedHistory
                    kind={kind}
                    row={history}
                    base={list.base}
                    onClose={() => setHistory(null)}
                    currency={currency}
                />
            )}
        </>
    );
}

function CatalogForm({
    kind,
    row,
    base,
    onClose,
    onSaved,
}: {
    kind: Kind;
    row: Row | "new";
    base: string;
    onClose: () => void;
    onSaved: () => void;
}) {
    const cfg = config[kind];
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");
    const [categories, setCategories] = useState<Row[]>([]);
    const [suppliers, setSuppliers] = useState<Row[]>([]);
    useEffect(() => {
        if (kind === "products")
            Promise.all([
                allRows(base, "categories"),
                allRows(base, "suppliers"),
            ])
                .then(([a, b]) => {
                    setCategories(a);
                    setSuppliers(b);
                })
                .catch((e) => setError(errorMessage(e)));
    }, [base, kind]);
    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setBusy(true);
        setError("");
        const form = new FormData(event.currentTarget);
        const data: Record<string, unknown> = {};
        for (const field of cfg.fields) {
            const value = String(form.get(field.name) ?? "");
            data[field.name] =
                value === ""
                    ? field.type === "number"
                        ? 0
                        : null
                    : field.type === "number"
                      ? Number(value)
                      : value;
        }
        data.active = form.get("active") === "on";
        if (kind === "products")
            for (const key of ["category_id", "supplier_id"])
                data[key] = form.get(key) ? Number(form.get(key)) : null;
        try {
            if (row === "new") await api.post(`${base}/${kind}`, data);
            else await api.patch(`${base}/${kind}/${row.id}`, data);
            onSaved();
        } catch (e) {
            setError(errorMessage(e));
        } finally {
            setBusy(false);
        }
    }
    return (
        <Modal
            title={`${row === "new" ? "Crear" : "Editar"} ${cfg.singular}`}
            onClose={() => !busy && onClose()}
            wide={kind === "products"}
        >
            <form onSubmit={submit}>
                <ErrorBox message={error} />
                <div className="form-grid">
                    {cfg.fields.map((field) => (
                        <label key={field.name}>
                            {field.label}
                            {field.required && (
                                <span className="required"> *</span>
                            )}
                            <input
                                name={field.name}
                                type={
                                    field.type === "money"
                                        ? "text"
                                        : (field.type ?? "text")
                                }
                                inputMode={
                                    field.type === "money"
                                        ? "decimal"
                                        : undefined
                                }
                                pattern={
                                    field.type === "money"
                                        ? "[0-9]{1,10}(\.[0-9]{1,2})?"
                                        : undefined
                                }
                                required={field.required}
                                maxLength={field.maxLength ?? 255}
                                min={field.type === "number" ? 0 : undefined}
                                defaultValue={
                                    row === "new"
                                        ? field.type === "number"
                                            ? "0"
                                            : ""
                                        : String(row[field.name] ?? "")
                                }
                            />
                        </label>
                    ))}
                    {kind === "products" && (
                        <>
                            <label>
                                Categoría
                                <select
                                    name="category_id"
                                    defaultValue={
                                        row === "new"
                                            ? ""
                                            : String(row.category_id ?? "")
                                    }
                                >
                                    <option value="">Sin categoría</option>
                                    {categories.map((item) => (
                                        <option key={item.id} value={item.id}>
                                            {item.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label>
                                Proveedor
                                <select
                                    name="supplier_id"
                                    defaultValue={
                                        row === "new"
                                            ? ""
                                            : String(row.supplier_id ?? "")
                                    }
                                >
                                    <option value="">Sin proveedor</option>
                                    {suppliers.map((item) => (
                                        <option key={item.id} value={item.id}>
                                            {item.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        </>
                    )}
                </div>
                <label className="checkbox-label">
                    <input
                        type="checkbox"
                        name="active"
                        defaultChecked={row === "new" || Boolean(row.active)}
                    />{" "}
                    Registro activo
                </label>
                {kind === "products" && (
                    <p className="form-hint">
                        El producto inicia con stock cero. Registra su
                        existencia desde Inventario para conservar la
                        trazabilidad.
                    </p>
                )}
                <div className="modal-actions">
                    <button
                        type="button"
                        className="button secondary"
                        disabled={busy}
                        onClick={onClose}
                    >
                        Cancelar
                    </button>
                    <button className="button primary" disabled={busy}>
                        {busy ? "Guardando…" : "Guardar cambios"}
                    </button>
                </div>
            </form>
        </Modal>
    );
}

function RelatedHistory({
    kind,
    row,
    base,
    onClose,
    currency,
}: {
    kind: Kind;
    row: Row;
    base: string;
    onClose: () => void;
    currency: string;
}) {
    const [data, setData] = useState<PageData<Row> | null>(null);
    const [page, setPage] = useState(1);
    const [error, setError] = useState("");
    useEffect(() => {
        api.get<PageData<Row>>(
            `${base}/${kind}/${row.id}/${kind === "customers" ? "sales" : "products"}?page=${page}`,
        )
            .then((r) => setData(r.data))
            .catch((e) => setError(errorMessage(e)));
    }, [base, kind, row.id, page]);
    return (
        <Modal
            title={`${kind === "customers" ? "Compras" : "Productos"} · ${row.name}`}
            onClose={onClose}
            wide
        >
            <ErrorBox message={error} />
            {!data ? (
                <Spinner />
            ) : (
                <>
                    {data.data.length ? (
                        <div className="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>
                                            {kind === "customers"
                                                ? "Venta"
                                                : "Producto"}
                                        </th>
                                        <th>
                                            {kind === "customers"
                                                ? "Fecha"
                                                : "SKU"}
                                        </th>
                                        <th>Total / precio</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.data.map((item) => (
                                        <tr key={item.id}>
                                            <td>
                                                {kind === "customers"
                                                    ? `#${item.number}`
                                                    : item.name}
                                            </td>
                                            <td>
                                                {kind === "customers"
                                                    ? date(
                                                          (item as Sale)
                                                              .sold_at,
                                                      )
                                                    : String(item.sku)}
                                            </td>
                                            <td>
                                                {money(
                                                    String(
                                                        item.total ??
                                                            item.sale_price,
                                                    ),
                                                    currency,
                                                )}
                                            </td>
                                            <td>
                                                {kind === "customers"
                                                    ? item.status ===
                                                      "completed"
                                                        ? "Completada"
                                                        : "Anulada"
                                                    : item.active
                                                      ? "Activo"
                                                      : "Inactivo"}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <Empty />
                    )}
                    <Pagination
                        page={page}
                        last={data.meta.last_page}
                        total={data.meta.total}
                        onChange={setPage}
                    />
                </>
            )}
        </Modal>
    );
}
