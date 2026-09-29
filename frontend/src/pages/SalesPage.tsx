import { useEffect, useState, type FormEvent } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import { ArrowLeft, Plus, Search, ShoppingBag, Trash2 } from "lucide-react";
import { api, date, errorMessage, money } from "../lib/api";
import { useAuth } from "../lib/auth";
import { useList } from "../lib/useList";
import { lineTotal } from "../lib/saleMath";
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

export function SalesPage() {
    const { can, membership } = useAuth();
    const [status, setStatus] = useState("");
    const list = useList<Sale>("sales", status ? `&status=${status}` : "");
    return (
        <>
            <PageTitle
                eyebrow="CADA VENTA CUENTA"
                title="Ventas"
                description="Consulta tus operaciones y registra la próxima oportunidad."
                action={
                    can("sales.write") && (
                        <Link className="button primary" to="/sales/new">
                            <Plus size={18} /> Nueva venta
                        </Link>
                    )
                }
            />
            <section className="panel">
                <div className="list-toolbar">
                    <div className="search-input">
                        <Search size={18} />
                        <input
                            aria-label="Buscar por cliente"
                            placeholder="Buscar por cliente…"
                            value={list.search}
                            onChange={(e) => list.setSearch(e.target.value)}
                        />
                    </div>
                    <select
                        aria-label="Filtrar estado de venta"
                        value={status}
                        onChange={(e) => {
                            setStatus(e.target.value);
                            list.setPage(1);
                        }}
                    >
                        <option value="">Todos los estados</option>
                        <option value="completed">Completadas</option>
                        <option value="cancelled">Anuladas</option>
                    </select>
                </div>
                <ErrorBox message={list.error} />
                {list.loading ? (
                    <Spinner />
                ) : !list.data?.data.length ? (
                    <Empty
                        title="No hay ventas para mostrar"
                        description="Registra tu primera venta o cambia los filtros."
                    />
                ) : (
                    <div className="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Venta</th>
                                    <th>Cliente</th>
                                    <th>Vendedor</th>
                                    <th>Fecha</th>
                                    <th>Estado</th>
                                    <th className="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.data.data.map((sale) => (
                                    <tr key={sale.id}>
                                        <td>
                                            <Link
                                                className="table-link"
                                                to={`/sales/${sale.id}`}
                                            >
                                                #
                                                {String(sale.number).padStart(
                                                    5,
                                                    "0",
                                                )}
                                            </Link>
                                        </td>
                                        <td>
                                            {sale.customer_name ??
                                                "Consumidor final"}
                                        </td>
                                        <td>{sale.seller.name}</td>
                                        <td className="muted nowrap">
                                            {date(
                                                sale.sold_at,
                                                membership!.organization
                                                    .timezone,
                                            )}
                                        </td>
                                        <td>
                                            <Badge
                                                tone={
                                                    sale.status === "completed"
                                                        ? "green"
                                                        : "neutral"
                                                }
                                            >
                                                {sale.status === "completed"
                                                    ? "Completada"
                                                    : "Anulada"}
                                            </Badge>
                                        </td>
                                        <td className="text-right table-main">
                                            {money(
                                                sale.total,
                                                membership!.organization
                                                    .currency,
                                            )}
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
        </>
    );
}

export function NewSalePage() {
    const { membership } = useAuth();
    const base = `/organizations/${membership!.organization.id}`;
    const currency = membership!.organization.currency;
    const navigate = useNavigate();
    const [query, setQuery] = useState("");
    const [results, setResults] = useState<Product[]>([]);
    const [searched, setSearched] = useState(false);
    const [items, setItems] = useState<
        { product: Product; quantity: number }[]
    >([]);
    const [customerQuery, setCustomerQuery] = useState("");
    const [customers, setCustomers] = useState<Row[]>([]);
    const [customer, setCustomer] = useState<Row | null>(null);
    const [notes, setNotes] = useState("");
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");
    const [requestKey, setRequestKey] = useState(() => crypto.randomUUID());
    const totals = items.reduce(
        (sum, item) => {
            const line = lineTotal(
                item.product.sale_price,
                item.quantity,
                item.product.tax_rate_bps,
            );
            return {
                subtotal: sum.subtotal + line.subtotal,
                tax: sum.tax + line.tax,
                total: sum.total + line.total,
            };
        },
        { subtotal: 0, tax: 0, total: 0 },
    );
    function changed() {
        setRequestKey(crypto.randomUUID());
    }
    async function searchProducts() {
        setError("");
        try {
            setResults(
                (
                    await api.get<PageData<Product>>(
                        `${base}/products?active=1&per_page=20&search=${encodeURIComponent(query)}`,
                    )
                ).data.data,
            );
            setSearched(true);
        } catch (e) {
            setError(errorMessage(e));
        }
    }
    async function searchCustomers() {
        try {
            setCustomers(
                (
                    await api.get<PageData<Row>>(
                        `${base}/customers?active=1&per_page=20&search=${encodeURIComponent(customerQuery)}`,
                    )
                ).data.data,
            );
        } catch (e) {
            setError(errorMessage(e));
        }
    }
    function add(product: Product) {
        if (!items.some((item) => item.product.id === product.id)) {
            setItems([...items, { product, quantity: 1 }]);
            changed();
        }
    }
    async function submit() {
        setError("");
        setBusy(true);
        try {
            const response = await api.post<{ data: Sale }>(`${base}/sales`, {
                request_key: requestKey,
                customer_id: customer?.id ?? null,
                notes: notes || null,
                items: items.map((item) => ({
                    product_id: item.product.id,
                    quantity: item.quantity,
                })),
            });
            navigate(`/sales/${response.data.data.id}`);
        } catch (e) {
            setError(errorMessage(e));
        } finally {
            setBusy(false);
        }
    }
    return (
        <>
            <Link to="/sales" className="back-link">
                <ArrowLeft size={16} /> Volver a ventas
            </Link>
            <PageTitle
                eyebrow="PUNTO DE VENTA"
                title="Nueva venta"
                description="Selecciona productos. Los precios y el stock se validan al confirmar."
            />
            <ErrorBox message={error} />
            <fieldset disabled={busy} className="sale-layout">
                <div>
                    <section className="panel padded">
                        <h2>1. Elige los productos</h2>
                        <div className="inline-fields">
                            <input
                                aria-label="Buscar productos para venta"
                                placeholder="Buscar producto por nombre o SKU…"
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === "Enter") searchProducts();
                                }}
                            />
                            <button
                                className="button secondary"
                                onClick={searchProducts}
                            >
                                <Search size={17} /> Buscar
                            </button>
                        </div>
                        {results.length > 0 && (
                            <div className="sale-product-results">
                                {results.map((product) => (
                                    <button
                                        key={product.id}
                                        disabled={
                                            product.current_stock < 1 ||
                                            items.some(
                                                (item) =>
                                                    item.product.id ===
                                                    product.id,
                                            )
                                        }
                                        onClick={() => add(product)}
                                    >
                                        <span className="product-symbol">
                                            <ShoppingBag size={18} />
                                        </span>
                                        <div>
                                            <strong>{product.name}</strong>
                                            <small>
                                                {product.sku} ·{" "}
                                                {product.current_stock}{" "}
                                                disponibles
                                            </small>
                                        </div>
                                        <span>
                                            {money(
                                                product.sale_price,
                                                currency,
                                            )}
                                        </span>
                                        <Plus size={17} />
                                    </button>
                                ))}
                            </div>
                        )}
                        {searched && !results.length && (
                            <p className="form-hint">
                                No encontramos productos. Prueba otro nombre o
                                SKU.
                            </p>
                        )}
                    </section>
                    <section className="panel sale-lines">
                        <div className="panel-heading">
                            <h2>Productos de la venta</h2>
                            <Badge>{items.length} productos</Badge>
                        </div>
                        {items.length ? (
                            <div className="table-scroll">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Cantidad</th>
                                            <th>Total estimado</th>
                                            <th />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {items.map((item) => (
                                            <tr key={item.product.id}>
                                                <td>
                                                    <strong>
                                                        {item.product.name}
                                                    </strong>
                                                    <small>
                                                        {money(
                                                            item.product
                                                                .sale_price,
                                                            currency,
                                                        )}{" "}
                                                        +{" "}
                                                        {item.product
                                                            .tax_rate_bps / 100}
                                                        % imp.
                                                    </small>
                                                </td>
                                                <td>
                                                    <input
                                                        className="quantity-input"
                                                        aria-label={`Cantidad de ${item.product.name}`}
                                                        type="number"
                                                        min={1}
                                                        max={Math.min(
                                                            item.product
                                                                .current_stock,
                                                            10000,
                                                        )}
                                                        value={item.quantity}
                                                        onChange={(e) => {
                                                            setItems(
                                                                items.map(
                                                                    (
                                                                        current,
                                                                    ) =>
                                                                        current
                                                                            .product
                                                                            .id ===
                                                                        item
                                                                            .product
                                                                            .id
                                                                            ? {
                                                                                  ...current,
                                                                                  quantity:
                                                                                      Math.min(
                                                                                          Math.max(
                                                                                              1,
                                                                                              Math.floor(
                                                                                                  Number(
                                                                                                      e
                                                                                                          .target
                                                                                                          .value,
                                                                                                  ),
                                                                                              ),
                                                                                          ),
                                                                                          item
                                                                                              .product
                                                                                              .current_stock,
                                                                                          10000,
                                                                                      ),
                                                                              }
                                                                            : current,
                                                                ),
                                                            );
                                                            changed();
                                                        }}
                                                    />
                                                </td>
                                                <td className="nowrap">
                                                    {money(
                                                        lineTotal(
                                                            item.product
                                                                .sale_price,
                                                            item.quantity,
                                                            item.product
                                                                .tax_rate_bps,
                                                        ).total / 100,
                                                        currency,
                                                    )}
                                                </td>
                                                <td>
                                                    <button
                                                        className="icon-button danger"
                                                        aria-label={`Quitar ${item.product.name}`}
                                                        onClick={() => {
                                                            setItems(
                                                                items.filter(
                                                                    (current) =>
                                                                        current
                                                                            .product
                                                                            .id !==
                                                                        item
                                                                            .product
                                                                            .id,
                                                                ),
                                                            );
                                                            changed();
                                                        }}
                                                    >
                                                        <Trash2 size={17} />
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <Empty
                                title="Añade tu primer producto"
                                description="Busca en el catálogo y selecciona los productos de la venta."
                            />
                        )}
                    </section>
                </div>
                <aside>
                    <section className="panel padded">
                        <h2>2. Cliente y resumen</h2>
                        <label>
                            Buscar cliente
                            <div className="inline-fields">
                                <input
                                    value={customerQuery}
                                    onChange={(e) =>
                                        setCustomerQuery(e.target.value)
                                    }
                                    placeholder="Nombre del cliente"
                                    onKeyDown={(e) => {
                                        if (e.key === "Enter")
                                            searchCustomers();
                                    }}
                                />
                                <button
                                    className="button secondary"
                                    aria-label="Buscar cliente"
                                    onClick={searchCustomers}
                                >
                                    <Search size={17} />
                                </button>
                            </div>
                        </label>
                        {customers.length > 0 && (
                            <div className="lookup-results">
                                {customers.map((item) => (
                                    <button
                                        key={item.id}
                                        onClick={() => {
                                            setCustomer(item);
                                            setCustomers([]);
                                            changed();
                                        }}
                                    >
                                        {item.name}
                                    </button>
                                ))}
                            </div>
                        )}
                        <div className="selected-customer">
                            <span>{customer?.name ?? "Consumidor final"}</span>
                            {customer && (
                                <button
                                    className="text-link"
                                    onClick={() => {
                                        setCustomer(null);
                                        changed();
                                    }}
                                >
                                    Quitar
                                </button>
                            )}
                        </div>
                        <label>
                            Notas
                            <textarea
                                value={notes}
                                onChange={(e) => {
                                    setNotes(e.target.value);
                                    changed();
                                }}
                                rows={3}
                                maxLength={1000}
                                placeholder="Información adicional (opcional)"
                            />
                        </label>
                        <div className="sale-summary">
                            <div>
                                <span>Subtotal</span>
                                <strong>
                                    {money(totals.subtotal / 100, currency)}
                                </strong>
                            </div>
                            <div>
                                <span>Impuestos</span>
                                <strong>
                                    {money(totals.tax / 100, currency)}
                                </strong>
                            </div>
                            <div className="grand-total">
                                <span>Total estimado</span>
                                <strong>
                                    {money(totals.total / 100, currency)}
                                </strong>
                            </div>
                        </div>
                        <button
                            className="button primary full"
                            onClick={submit}
                            disabled={busy || !items.length}
                        >
                            {busy ? "Confirmando…" : "Confirmar venta"}
                        </button>
                        <p className="form-hint">
                            El servidor calcula el total definitivo con los
                            precios vigentes y descuenta las existencias de
                            forma segura.
                        </p>
                    </section>
                </aside>
            </fieldset>
        </>
    );
}

export function SaleDetailPage() {
    const { id } = useParams();
    const { membership, can } = useAuth();
    const base = `/organizations/${membership!.organization.id}`;
    const [sale, setSale] = useState<Sale | null>(null);
    const [error, setError] = useState("");
    const [showCancel, setShowCancel] = useState(false);
    const [busy, setBusy] = useState(false);
    const [cancelError, setCancelError] = useState("");
    useEffect(() => {
        const controller = new AbortController();
        api.get<{ data: Sale }>(`${base}/sales/${id}`, {
            signal: controller.signal,
        })
            .then((r) => setSale(r.data.data))
            .catch((e) => {
                if (!controller.signal.aborted) setError(errorMessage(e));
            });
        return () => controller.abort();
    }, [base, id]);
    async function cancel(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setBusy(true);
        setCancelError("");
        const reason = new FormData(event.currentTarget).get("reason");
        try {
            setSale(
                (
                    await api.post<{ data: Sale }>(
                        `${base}/sales/${id}/cancel`,
                        { reason },
                    )
                ).data.data,
            );
            setShowCancel(false);
        } catch (e) {
            setCancelError(errorMessage(e));
        } finally {
            setBusy(false);
        }
    }
    if (error) return <ErrorBox message={error} />;
    if (!sale) return <Spinner />;
    return (
        <>
            <Link to="/sales" className="back-link">
                <ArrowLeft size={16} /> Volver a ventas
            </Link>
            <PageTitle
                eyebrow="DETALLE DE OPERACIÓN"
                title={`Venta #${String(sale.number).padStart(5, "0")}`}
                description={date(
                    sale.sold_at,
                    membership!.organization.timezone,
                )}
                action={
                    <Badge
                        tone={sale.status === "completed" ? "green" : "neutral"}
                    >
                        {sale.status === "completed" ? "Completada" : "Anulada"}
                    </Badge>
                }
            />
            <section className="panel padded">
                <div className="sale-meta">
                    <div>
                        <small>CLIENTE</small>
                        <strong>
                            {sale.customer_name ?? "Consumidor final"}
                        </strong>
                    </div>
                    <div>
                        <small>VENDEDOR</small>
                        <strong>{sale.seller.name}</strong>
                    </div>
                    <div>
                        <small>MONEDA</small>
                        <strong>{membership!.organization.currency}</strong>
                    </div>
                </div>
                <div className="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio unitario</th>
                                <th className="text-right">
                                    Total con impuesto
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {sale.items?.map((item) => (
                                <tr key={item.id}>
                                    <td>
                                        <strong>{item.product_name}</strong>
                                        <small>{item.product_sku}</small>
                                    </td>
                                    <td>{item.quantity}</td>
                                    <td>
                                        {money(
                                            item.unit_price,
                                            membership!.organization.currency,
                                        )}
                                    </td>
                                    <td className="text-right">
                                        {money(
                                            item.total,
                                            membership!.organization.currency,
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="detail-summary">
                    <div className="sale-summary">
                        <div>
                            <span>Subtotal</span>
                            <strong>
                                {money(
                                    sale.subtotal,
                                    membership!.organization.currency,
                                )}
                            </strong>
                        </div>
                        <div>
                            <span>Impuestos</span>
                            <strong>
                                {money(
                                    sale.tax,
                                    membership!.organization.currency,
                                )}
                            </strong>
                        </div>
                        <div className="grand-total">
                            <span>Total</span>
                            <strong>
                                {money(
                                    sale.total,
                                    membership!.organization.currency,
                                )}
                            </strong>
                        </div>
                    </div>
                </div>
                {sale.notes && <p className="info-strip">{sale.notes}</p>}
                {sale.cancellation_reason && (
                    <p className="error-box">
                        Motivo de anulación: {sale.cancellation_reason}
                    </p>
                )}
                {sale.status === "completed" && can("sales.cancel") && (
                    <button
                        className="button secondary danger"
                        onClick={() => setShowCancel(true)}
                    >
                        Anular venta
                    </button>
                )}
            </section>
            {showCancel && (
                <Modal
                    title="Anular venta"
                    onClose={() => !busy && setShowCancel(false)}
                >
                    <p>
                        Se reintegrarán las unidades vendidas al inventario y se
                        conservará el historial.
                    </p>
                    <form onSubmit={cancel}>
                        <ErrorBox message={cancelError} />
                        <label>
                            Motivo
                            <textarea
                                name="reason"
                                required
                                minLength={5}
                                maxLength={500}
                                rows={3}
                            />
                        </label>
                        <div className="modal-actions">
                            <button
                                type="button"
                                className="button secondary"
                                disabled={busy}
                                onClick={() => setShowCancel(false)}
                            >
                                Volver
                            </button>
                            <button
                                className="button destructive"
                                disabled={busy}
                            >
                                {busy ? "Anulando…" : "Confirmar anulación"}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}
        </>
    );
}
