import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import {
    ArrowDownToLine,
    ArrowRight,
    ArrowUpRight,
    Boxes,
    CircleDollarSign,
    PackageCheck,
    Plus,
    Users,
} from "lucide-react";
import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from "recharts";
import { api, date, errorMessage, money } from "../lib/api";
import { useAuth } from "../lib/auth";
import { Badge, Empty, ErrorBox, PageTitle, Spinner } from "../components/ui";
import type { DashboardData } from "../types";

export default function Dashboard() {
    const { membership, user, can } = useAuth();
    const [data, setData] = useState<DashboardData | null>(null);
    const [error, setError] = useState("");
    const [days, setDays] = useState(30);
    useEffect(() => {
        const controller = new AbortController();
        api.get<{ data: DashboardData }>(
            `/organizations/${membership?.organization.id}/dashboard`,
            { signal: controller.signal },
        )
            .then((r) => setData(r.data.data))
            .catch((e) => {
                if (!controller.signal.aborted) setError(errorMessage(e));
            });
        return () => controller.abort();
    }, [membership?.organization.id]);
    if (error) return <ErrorBox message={error} />;
    if (!data) return <Spinner />;
    const amount = (value: string | number) => money(value, data.currency);
    const cards = [
        {
            label: "Ventas de este mes",
            value: amount(data.sales_month),
            note: `${data.sales_count_month} ventas completadas`,
            icon: CircleDollarSign,
            color: "green",
        },
        {
            label: "Productos vendidos",
            value: data.products_sold_month.toLocaleString("es-CO"),
            note: "Unidades en el mes actual",
            icon: PackageCheck,
            color: "blue",
        },
        {
            label: "Clientes registrados",
            value: data.customers_count.toLocaleString("es-CO"),
            note: "Personas que confían en ti",
            icon: Users,
            color: "purple",
        },
        {
            label: "Stock por reponer",
            value: data.low_stock_count.toLocaleString("es-CO"),
            note: "Productos en el nivel mínimo",
            icon: Boxes,
            color: "orange",
        },
    ];
    return (
        <>
            <PageTitle
                eyebrow="TU NEGOCIO, DE UN VISTAZO"
                title={`Hola, ${user?.name.split(" ")[0]}.`}
                description="Este es el pulso de tu negocio hoy."
                action={
                    can("sales.write") && (
                        <Link to="/sales/new" className="button primary">
                            <Plus size={18} /> Nueva venta
                        </Link>
                    )
                }
            />
            <div className="welcome-strip">
                <span className="live-dot" />
                <span>Tu espacio está al día</span>
                <span className="strip-divider" />
                <span>
                    Hoy has vendido <strong>{amount(data.sales_today)}</strong>
                </span>
                <span className="strip-right">
                    {new Intl.DateTimeFormat("es-CO", {
                        dateStyle: "long",
                        timeZone: data.timezone,
                    }).format(new Date())}
                </span>
            </div>
            <div className="kpi-grid">
                {cards.map((card) => (
                    <article key={card.label} className="kpi">
                        <div className="kpi-top">
                            <span>{card.label}</span>
                            <span className={`kpi-icon ${card.color}`}>
                                <card.icon size={19} />
                            </span>
                        </div>
                        <strong>{card.value}</strong>
                        <p>
                            {card.color === "orange" ? (
                                <span className="tiny-dot orange" />
                            ) : (
                                <span className="tiny-dot green" />
                            )}
                            {card.note}
                        </p>
                    </article>
                ))}
            </div>
            <div className="dashboard-grid">
                <section className="panel chart-panel">
                    <div className="panel-heading">
                        <div>
                            <h2>Evolución de ventas</h2>
                            <p>
                                Ingresos de ventas completadas, impuestos
                                incluidos
                            </p>
                        </div>
                        <div className="segmented">
                            <button
                                className={days === 7 ? "selected" : ""}
                                onClick={() => setDays(7)}
                            >
                                7 días
                            </button>
                            <button
                                className={days === 30 ? "selected" : ""}
                                onClick={() => setDays(30)}
                            >
                                30 días
                            </button>
                        </div>
                    </div>
                    <div className="chart-legend">
                        <span className="tiny-dot green" /> Ventas{" "}
                        <span className="muted">· {data.currency}</span>
                    </div>
                    <div className="chart">
                        <ResponsiveContainer width="100%" height="100%">
                            <AreaChart
                                data={data.trend
                                    .slice(-days)
                                    .map((point) => ({
                                        ...point,
                                        total: Number(point.total),
                                    }))}
                                margin={{
                                    top: 12,
                                    right: 12,
                                    left: 4,
                                    bottom: 0,
                                }}
                            >
                                <defs>
                                    <linearGradient
                                        id="salesGradient"
                                        x1="0"
                                        y1="0"
                                        x2="0"
                                        y2="1"
                                    >
                                        <stop
                                            offset="0%"
                                            stopColor="#0f9b7d"
                                            stopOpacity={0.18}
                                        />
                                        <stop
                                            offset="100%"
                                            stopColor="#0f9b7d"
                                            stopOpacity={0.01}
                                        />
                                    </linearGradient>
                                </defs>
                                <CartesianGrid
                                    strokeDasharray="3 4"
                                    vertical={false}
                                    stroke="#edf0f3"
                                />
                                <XAxis
                                    dataKey="day"
                                    tickFormatter={(value) =>
                                        String(value).slice(8)
                                    }
                                    tickLine={false}
                                    axisLine={false}
                                    minTickGap={22}
                                    tick={{ fontSize: 11, fill: "#8993a3" }}
                                />
                                <YAxis
                                    width={54}
                                    tickFormatter={(value) =>
                                        new Intl.NumberFormat("es", {
                                            notation: "compact",
                                        }).format(Number(value))
                                    }
                                    tickLine={false}
                                    axisLine={false}
                                    tick={{ fontSize: 11, fill: "#8993a3" }}
                                />
                                <Tooltip
                                    formatter={(value) => [
                                        amount(Number(value)),
                                        "Ventas",
                                    ]}
                                    contentStyle={{
                                        borderRadius: 12,
                                        border: "1px solid #e5e9ee",
                                        fontSize: 12,
                                    }}
                                />
                                <Area
                                    type="monotone"
                                    dataKey="total"
                                    stroke="#0c997a"
                                    strokeWidth={2.5}
                                    fill="url(#salesGradient)"
                                />
                            </AreaChart>
                        </ResponsiveContainer>
                    </div>
                </section>
                <section className="panel">
                    <div className="panel-heading">
                        <div>
                            <h2>Productos más vendidos</h2>
                            <p>Los favoritos de este mes</p>
                        </div>
                        <span className="icon-subtle">
                            <ArrowUpRight size={19} />
                        </span>
                    </div>
                    {data.top_products.length ? (
                        <div className="ranking">
                            {data.top_products.map((product, index) => (
                                <div
                                    key={product.product_id}
                                    className="ranking-row"
                                >
                                    <span className="rank-number">
                                        {String(index + 1).padStart(2, "0")}
                                    </span>
                                    <div className="ranking-main">
                                        <strong>{product.name}</strong>
                                        <div className="progress-track">
                                            <span
                                                style={{
                                                    width: `${(Number(product.quantity) / Number(data.top_products[0].quantity)) * 100}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                    <span className="ranking-value">
                                        {product.quantity}
                                        <small>uds.</small>
                                    </span>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <Empty
                            title="Tu próximo éxito empieza aquí"
                            description="Registra ventas para descubrir tus productos favoritos."
                        />
                    )}
                </section>
            </div>
            <div className="dashboard-bottom">
                <section className="panel">
                    <div className="panel-heading">
                        <div>
                            <h2>Últimas ventas</h2>
                            <p>Los movimientos más recientes de tu negocio</p>
                        </div>
                        <Link className="text-link" to="/sales">
                            Ver todas <ArrowRight size={15} />
                        </Link>
                    </div>
                    {data.recent_sales.length ? (
                        <div className="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Venta / cliente</th>
                                        <th>Fecha</th>
                                        <th>Estado</th>
                                        <th className="text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.recent_sales.map((sale) => (
                                        <tr key={sale.id}>
                                            <td>
                                                <Link
                                                    className="table-main"
                                                    to={`/sales/${sale.id}`}
                                                >
                                                    #
                                                    {String(
                                                        sale.number,
                                                    ).padStart(5, "0")}
                                                </Link>
                                                <small>
                                                    {sale.customer_name ??
                                                        "Consumidor final"}
                                                </small>
                                            </td>
                                            <td className="muted nowrap">
                                                {date(
                                                    sale.sold_at,
                                                    data.timezone,
                                                )}
                                            </td>
                                            <td>
                                                <Badge
                                                    tone={
                                                        sale.status ===
                                                        "completed"
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
                                                {amount(sale.total)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <Empty
                            title="Aún no tienes ventas"
                            description="Tu primera venta aparecerá aquí."
                        />
                    )}
                </section>
                <section className="panel stock-panel">
                    <div className="panel-heading">
                        <div>
                            <h2>Necesitan tu atención</h2>
                            <p>Mantén tu inventario en movimiento</p>
                        </div>
                        <span className="kpi-icon orange">
                            <ArrowDownToLine size={18} />
                        </span>
                    </div>
                    {data.low_stock_products.length ? (
                        <div className="stock-alerts">
                            {data.low_stock_products.map((product) => (
                                <div className="stock-row" key={product.id}>
                                    <span className="product-symbol">
                                        <Boxes size={19} />
                                    </span>
                                    <div>
                                        <strong>{product.name}</strong>
                                        <small>
                                            Mínimo: {product.minimum_stock}{" "}
                                            unidades
                                        </small>
                                    </div>
                                    <Badge
                                        tone={
                                            product.current_stock === 0
                                                ? "red"
                                                : "orange"
                                        }
                                    >
                                        {product.current_stock} uds.
                                    </Badge>
                                </div>
                            ))}
                            <Link
                                className="button soft full"
                                to="/products?low_stock=1"
                            >
                                Revisar productos <ArrowRight size={16} />
                            </Link>
                        </div>
                    ) : (
                        <Empty
                            title="Todo en orden"
                            description="Tus productos tienen stock suficiente."
                        />
                    )}
                </section>
            </div>
            {data.top_customers.length > 0 && (
                <section className="panel best-customers">
                    <div className="panel-heading">
                        <div>
                            <h2>Tus mejores clientes</h2>
                            <p>Compras completadas durante este mes</p>
                        </div>
                        <Users size={20} className="muted" />
                    </div>
                    <div className="customer-cards">
                        {data.top_customers.map((customer, index) => (
                            <Link
                                to="/customers"
                                className="customer-card"
                                key={customer.customer_id}
                            >
                                <span className={`avatar tint-${index % 3}`}>
                                    {customer.name.charAt(0)}
                                </span>
                                <div>
                                    <strong>{customer.name}</strong>
                                    <small>{amount(customer.total)}</small>
                                </div>
                            </Link>
                        ))}
                    </div>
                </section>
            )}
            <p className="dashboard-footnote">
                Datos de {membership?.organization.name} · Ventas anuladas
                excluidas de los indicadores.
            </p>
        </>
    );
}
