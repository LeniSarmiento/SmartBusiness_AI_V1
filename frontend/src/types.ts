export type Organization = {
    id: number;
    name: string;
    currency: string;
    timezone: string;
};
export type Membership = {
    id: number;
    organization: Organization;
    role: "admin" | "manager" | "seller";
    permissions: string[];
};
export type User = {
    id: number;
    name: string;
    email: string;
    memberships: Membership[];
};
export type Row = { id: number; name?: string; [key: string]: unknown };
export type Product = Row & {
    name: string;
    sku: string;
    sale_price: string;
    purchase_price?: string;
    current_stock: number;
    minimum_stock: number;
    tax_rate_bps: number;
    active: boolean;
    low_stock: boolean;
    category?: { id: number; name: string } | null;
};
export type Sale = Row & {
    number: number;
    customer_name: string | null;
    status: "completed" | "cancelled";
    total: string;
    subtotal: string;
    tax: string;
    sold_at: string;
    notes: string | null;
    cancellation_reason: string | null;
    seller: { name: string };
    items?: {
        id: number;
        product_name: string;
        product_sku: string;
        quantity: number;
        unit_price: string;
        total: string;
    }[];
};
export type Pagination<T> = {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
};
export type DashboardData = {
    currency: string;
    timezone: string;
    sales_today: string;
    sales_month: string;
    sales_count_month: number;
    customers_count: number;
    products_sold_month: number;
    low_stock_count: number;
    low_stock_products: Product[];
    trend: { day: string; total: string }[];
    top_products: {
        product_id: number;
        name: string;
        quantity: number;
        total: string;
    }[];
    top_customers: { customer_id: number; name: string; total: string }[];
    recent_sales: Sale[];
};
