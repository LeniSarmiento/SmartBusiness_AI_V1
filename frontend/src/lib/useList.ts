import { useCallback, useEffect, useState } from "react";
import axios from "axios";
import { api, errorMessage } from "./api";
import { useAuth } from "./auth";
import type { Pagination, Row } from "../types";

export function useList<T extends Row>(endpoint: string, extra = "") {
    const { membership } = useAuth();
    const base = `/organizations/${membership?.organization.id}`;
    const [data, setData] = useState<Pagination<T> | null>(null);
    const [page, setPage] = useState(1);
    const [search, setSearchValue] = useState("");
    const [version, setVersion] = useState(0);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const refresh = useCallback(() => setVersion((v) => v + 1), []);
    const setSearch = (value: string) => {
        setSearchValue(value);
        setPage(1);
    };
    useEffect(() => {
        const abort = new AbortController();
        setLoading(true);
        setError("");
        const timeout = setTimeout(
            () => {
                api.get<Pagination<T>>(
                    `${base}/${endpoint}?page=${page}&search=${encodeURIComponent(search)}${extra}`,
                    { signal: abort.signal },
                )
                    .then((response) => {
                        setData(response.data);
                        if (page > response.data.meta.last_page)
                            setPage(response.data.meta.last_page);
                    })
                    .catch((e) => {
                        if (!axios.isCancel(e)) setError(errorMessage(e));
                    })
                    .finally(() => {
                        if (!abort.signal.aborted) setLoading(false);
                    });
            },
            search ? 250 : 0,
        );
        return () => {
            clearTimeout(timeout);
            abort.abort();
        };
    }, [base, endpoint, page, search, version, extra]);
    return {
        data,
        page,
        setPage,
        search,
        setSearch,
        refresh,
        loading,
        error,
        base,
    };
}

export async function allRows<T extends Row>(
    base: string,
    endpoint: string,
): Promise<T[]> {
    const first = (
        await api.get<Pagination<T>>(
            `${base}/${endpoint}?per_page=100&active=1`,
        )
    ).data;
    const rows = [...first.data];
    for (let page = 2; page <= first.meta.last_page; page++)
        rows.push(
            ...(
                await api.get<Pagination<T>>(
                    `${base}/${endpoint}?per_page=100&active=1&page=${page}`,
                )
            ).data.data,
        );
    return rows;
}
