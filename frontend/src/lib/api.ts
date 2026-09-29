import axios from "axios";

export const api = axios.create({
    baseURL: "/api/v1",
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
    },
});
export async function csrf() {
    await axios.get("/sanctum/csrf-cookie", { withCredentials: true });
}
export function errorMessage(error: unknown): string {
    if (axios.isAxiosError(error)) {
        const errors = error.response?.data?.errors as
            | Record<string, string[]>
            | undefined;
        if (errors) return Object.values(errors).flat().join(" ");
        if (error.response?.status === 419)
            return "Tu sesión ha expirado. Recarga la página e inicia sesión nuevamente.";
        if (error.response?.status === 401)
            return "Inicia sesión para continuar.";
        if (error.response?.status === 403)
            return "Tu rol no tiene permiso para realizar esta acción.";
        return (
            error.response?.data?.message ||
            "No pudimos conectar con el servidor. Inténtalo de nuevo."
        );
    }
    return error instanceof Error
        ? error.message
        : "Ocurrió un error inesperado.";
}
export function money(value: string | number, currency = "COP") {
    return new Intl.NumberFormat("es-CO", {
        style: "currency",
        currency,
        maximumFractionDigits: 2,
    }).format(Number(value));
}
export function date(value: string, timezone = "America/Bogota") {
    return new Intl.DateTimeFormat("es-CO", {
        dateStyle: "medium",
        timeStyle: "short",
        timeZone: timezone,
    }).format(new Date(value));
}
