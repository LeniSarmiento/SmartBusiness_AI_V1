import { useState, type FormEvent } from "react";
import { ArrowDownLeft, ArrowUpRight, Plus, Search } from "lucide-react";
import { api, date, errorMessage } from "../lib/api";
import { useAuth } from "../lib/auth";
import { useList } from "../lib/useList";
import {
    Badge,
    Empty,
    ErrorBox,
    Modal,
    PageTitle,
    Pagination,
    Spinner,
} from "../components/ui";
import type { Pagination as PageData, Product, Row } from "../types";

export default function InventoryPage() {
    const { can, membership } = useAuth();
    const list = useList<Row>("inventory-movements");
    const [show, setShow] = useState(false);
    const labels: Record<string, string> = {
        entry: "Entrada",
        exit: "Salida",
        adjustment: "Ajuste",
        sale: "Venta",
        cancellation: "Anulación",
    };
    return (
        <>
            <PageTitle
                eyebrow="TRAZABILIDAD"
                title="Inventario"
                description="Cada entrada, cada salida. La historia completa de tus productos."
                action={
                    can("inventory.write") && (
                        <button
                            className="button primary"
                            onClick={() => setShow(true)}
                        >
                            <Plus size={18} /> Registrar movimiento
                        </button>
                    )
                }
            />
            <div className="info-strip">
                El stock se actualiza con cada movimiento. Las ventas y sus
                anulaciones generan movimientos automáticamente.
            </div>
            <section className="panel">
                <div className="panel-heading">
                    <h2>Historial de movimientos</h2>
                    <span className="count-label">
                        {list.data?.meta.total ?? 0} movimientos
                    </span>
                </div>
                <ErrorBox message={list.error} />
                {list.loading ? (
                    <Spinner />
                ) : !list.data?.data.length ? (
                    <Empty
                        title="Tu inventario empieza aquí"
                        description="Registra una entrada para dar stock a tus productos."
                    />
                ) : (
                    <div className="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Tipo</th>
                                    <th>Cantidad</th>
                                    <th>Saldo</th>
                                    <th>Motivo</th>
                                    <th>Responsable / fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.data.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="table-main">
                                            {
                                                (
                                                    row.product as {
                                                        name: string;
                                                    }
                                                ).name
                                            }
                                        </td>
                                        <td>
                                            <Badge
                                                tone={
                                                    Number(row.quantity_delta) >
                                                    0
                                                        ? "green"
                                                        : "orange"
                                                }
                                            >
                                                {labels[String(row.type)]}
                                            </Badge>
                                        </td>
                                        <td>
                                            <span
                                                className={`delta ${Number(row.quantity_delta) > 0 ? "positive" : "negative"}`}
                                            >
                                                {Number(row.quantity_delta) >
                                                0 ? (
                                                    <ArrowDownLeft size={15} />
                                                ) : (
                                                    <ArrowUpRight size={15} />
                                                )}
                                                {Number(row.quantity_delta) > 0
                                                    ? "+"
                                                    : ""}
                                                {String(row.quantity_delta)}
                                            </span>
                                        </td>
                                        <td>
                                            {String(row.balance_after)} uds.
                                        </td>
                                        <td className="reason-cell">
                                            {String(row.reason)}
                                        </td>
                                        <td>
                                            {
                                                (row.user as { name: string })
                                                    .name
                                            }
                                            <small>
                                                {date(
                                                    String(row.created_at),
                                                    membership!.organization
                                                        .timezone,
                                                )}
                                            </small>
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
            {show && (
                <StockForm
                    base={list.base}
                    onClose={() => setShow(false)}
                    onSaved={() => {
                        setShow(false);
                        list.refresh();
                    }}
                />
            )}
        </>
    );
}

function StockForm({
    base,
    onClose,
    onSaved,
}: {
    base: string;
    onClose: () => void;
    onSaved: () => void;
}) {
    const [query, setQuery] = useState("");
    const [products, setProducts] = useState<Product[]>([]);
    const [product, setProduct] = useState<Product | null>(null);
    const [type, setType] = useState("entry");
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");
    async function search() {
        setError("");
        try {
            setProducts(
                (
                    await api.get<PageData<Product>>(
                        `${base}/products?per_page=20&search=${encodeURIComponent(query)}`,
                    )
                ).data.data,
            );
        } catch (e) {
            setError(errorMessage(e));
        }
    }
    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!product) {
            setError("Selecciona un producto.");
            return;
        }
        setBusy(true);
        setError("");
        const form = new FormData(event.currentTarget);
        try {
            await api.post(`${base}/inventory-movements`, {
                product_id: product.id,
                type,
                quantity: Number(form.get("quantity")),
                direction: form.get("direction") ?? "increase",
                reason: form.get("reason"),
            });
            onSaved();
        } catch (e) {
            setError(errorMessage(e));
        } finally {
            setBusy(false);
        }
    }
    return (
        <Modal title="Registrar movimiento" onClose={() => !busy && onClose()}>
            <ErrorBox message={error} />
            <div className="lookup">
                <label>
                    Buscar producto
                    <div className="inline-fields">
                        <input
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Nombre o SKU"
                            onKeyDown={(e) => {
                                if (e.key === "Enter") {
                                    e.preventDefault();
                                    search();
                                }
                            }}
                        />
                        <button
                            type="button"
                            className="button secondary"
                            aria-label="Buscar producto"
                            onClick={search}
                        >
                            <Search size={18} />
                        </button>
                    </div>
                </label>
                {products.length > 0 && (
                    <div className="lookup-results">
                        {products.map((item) => (
                            <button
                                type="button"
                                key={item.id}
                                onClick={() => {
                                    setProduct(item);
                                    setProducts([]);
                                }}
                            >
                                <span>
                                    {item.name}
                                    <small>{item.sku}</small>
                                </span>
                                <Badge>{item.current_stock} uds.</Badge>
                            </button>
                        ))}
                    </div>
                )}
                {product && (
                    <div className="selected-product">
                        <strong>{product.name}</strong>
                        <span>Stock actual: {product.current_stock}</span>
                    </div>
                )}
            </div>
            <form onSubmit={submit}>
                <div className="form-grid">
                    <label>
                        Tipo de movimiento
                        <select
                            value={type}
                            onChange={(e) => setType(e.target.value)}
                        >
                            <option value="entry">Entrada</option>
                            <option value="exit">Salida</option>
                            <option value="adjustment">Ajuste</option>
                        </select>
                    </label>
                    <label>
                        Cantidad
                        <input
                            name="quantity"
                            type="number"
                            min={1}
                            max={1000000}
                            step={1}
                            required
                            defaultValue={1}
                        />
                    </label>
                    {type === "adjustment" && (
                        <label>
                            Dirección
                            <select name="direction">
                                <option value="increase">Aumentar stock</option>
                                <option value="decrease">
                                    Disminuir stock
                                </option>
                            </select>
                        </label>
                    )}
                </div>
                <label>
                    Motivo
                    <textarea
                        name="reason"
                        required
                        minLength={5}
                        maxLength={500}
                        placeholder="Ej. Recepción de mercancía del proveedor"
                        rows={3}
                    />
                </label>
                <div className="modal-actions">
                    <button
                        type="button"
                        className="button secondary"
                        onClick={onClose}
                        disabled={busy}
                    >
                        Cancelar
                    </button>
                    <button
                        className="button primary"
                        disabled={busy || !product}
                    >
                        {busy ? "Registrando…" : "Registrar movimiento"}
                    </button>
                </div>
            </form>
        </Modal>
    );
}
