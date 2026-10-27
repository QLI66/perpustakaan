import type {
    BookIncidentRecord,
    FineRecord,
    FineSummary,
    LoanRecord,
    MemberProfile,
    PaginatedResult,
    PaginationMeta,
    ReservationRecord,
} from '~/types/member'

interface ApiEnvelope<T> {
    success: boolean
    message: string
    data: T
}

interface ActionResult {
    [key: string]: unknown
}

const isRecord = (value: unknown): value is Record<string, unknown> => {
    return value !== null && typeof value === 'object' && !Array.isArray(value)
}

const isMoneyValue = (value: unknown): value is number | string => {
    if (typeof value === 'number') {
        return Number.isFinite(value)
    }

    return typeof value === 'string'
        && value.trim() !== ''
        && Number.isFinite(Number(value))
}

const isInteger = (value: unknown): value is number =>
    typeof value === 'number' && Number.isInteger(value)

export const useMember = () => {
    const { apiFetch } = useApi()
    const authStore = useAuthStore()
    const toast = useToast()

    const authHeaders = () => ({
        Accept: 'application/json',
        ...(authStore.token
            ? {
                  Authorization: `Bearer ${authStore.token}`,
              }
            : {}),
    })

    const get = <T>(
        path: string,
        query?: Record<string, string | number | boolean | undefined>,
        signal?: AbortSignal,
    ): Promise<ApiEnvelope<T>> => {
        return apiFetch<T>(path, {
            method: 'GET',
            query,
            headers: authHeaders(),
            signal,
        }).then(validateEnvelope)
    }

    const post = <T>(
        path: string,
        body?: Record<string, unknown>,
    ): Promise<ApiEnvelope<T>> => {
        return apiFetch<T>(path, {
            method: 'POST',
            body,
            headers: authHeaders(),
        }).then(validateEnvelope)
    }

    const validateEnvelope = <T>(response: ApiEnvelope<T>): ApiEnvelope<T> => {
        if (
            !isRecord(response) ||
            !Object.prototype.hasOwnProperty.call(response, 'success') ||
            typeof response.success !== 'boolean' ||
            !Object.prototype.hasOwnProperty.call(response, 'message') ||
            typeof response.message !== 'string' ||
            !Object.prototype.hasOwnProperty.call(response, 'data')
        ) {
            throw new Error('Respons API tidak valid.')
        }

        if (response.success !== true) {
            throw new Error(response.message || 'Permintaan API gagal.')
        }

        if (!Object.prototype.hasOwnProperty.call(response, 'data')) {
            throw new Error('Respons API tidak berisi data.')
        }

        return response
    }

    const normalizePaginated = <T>(
        response: ApiEnvelope<unknown>,
    ): PaginatedResult<T> => {
        const payload = response.data
        if (!isRecord(payload)) {
            throw new Error('Format data paginasi API tidak valid.')
        }

        const normalizeMeta = (value: unknown): PaginationMeta | null => {
            if (value == null) {
                return null
            }

            if (!isRecord(value)) {
                throw new Error('Metadata paginasi API tidak valid.')
            }

            const { current_page, per_page, total, last_page, from, to } = value
            if (
                !isInteger(current_page) ||
                !isInteger(per_page) ||
                !isInteger(total) ||
                !isInteger(last_page) ||
                (from != null && !isInteger(from)) ||
                (to != null && !isInteger(to))
            ) {
                throw new Error('Metadata paginasi API tidak valid.')
            }

            return {
                current_page,
                per_page,
                total,
                last_page,
                ...(from !== undefined ? { from } : {}),
                ...(to !== undefined ? { to } : {}),
            }
        }

        if (Array.isArray(payload.items)) {
            return {
                items: payload.items as T[],
                meta: normalizeMeta(payload.pagination),
            }
        }

        if (Array.isArray(payload.data)) {
            return {
                items: payload.data as T[],
                meta: normalizeMeta(payload.meta),
            }
        }

        throw new Error('Respons API tidak berisi daftar data yang dapat ditampilkan.')
    }

    const getProfile = async (): Promise<MemberProfile> => {
        const response = await get<MemberProfile>('/me/member')
        return response.data
    }

    const getLoans = async (
        page = 1,
        perPage = 15,
    ): Promise<PaginatedResult<LoanRecord>> => {
        const response = await get<unknown>('/me/loans', {
            page,
            per_page: perPage,
        })

        return normalizePaginated<LoanRecord>(response)
    }

    const getFines = async (
        page = 1,
        perPage = 15,
    ): Promise<PaginatedResult<FineRecord>> => {
        const response = await get<unknown>('/me/fines', {
            page,
            per_page: perPage,
        })

        const result = normalizePaginated<FineRecord>(response)
        const payload = response.data
        if (!isRecord(payload) || !isRecord(payload.summary)) {
            throw new Error('Respons API tidak berisi ringkasan denda.')
        }

        const summary = payload.summary
        if (
            !isMoneyValue(summary.total_amount) ||
            !isMoneyValue(summary.total_paid) ||
            !isMoneyValue(summary.outstanding_amount)
        ) {
            throw new Error('Ringkasan denda dari API tidak valid.')
        }

        return {
            ...result,
            summary: {
                total_amount: summary.total_amount,
                total_paid: summary.total_paid,
                outstanding_amount: summary.outstanding_amount,
            } satisfies FineSummary,
        }
    }

    const getReservations = async (
        page = 1,
        perPage = 15,
        signal?: AbortSignal,
    ): Promise<PaginatedResult<ReservationRecord>> => {
        const response = await get<unknown>('/me/reservations', {
            page,
            per_page: perPage,
        }, signal)

        return normalizePaginated<ReservationRecord>(response)
    }

    const getIncidents = async (
        page = 1,
        perPage = 15,
    ): Promise<PaginatedResult<BookIncidentRecord>> => {
        const response = await get<unknown>('/me/book-incidents', {
            page,
            per_page: perPage,
        })

        return normalizePaginated<BookIncidentRecord>(response)
    }

    const renewLoanItem = async (
        loanId: number,
        loanItemId: number,
    ): Promise<ActionResult> => {
        const response = await post<ActionResult>(
            `/loans/${loanId}/items/${loanItemId}/renew`,
        )

        return response.data
    }

    const createReservation = async (
        bookId: number,
        notes?: string,
    ): Promise<ReservationRecord> => {
        const response = await post<ReservationRecord>(
            '/reservations',
            {
                book_id: bookId,
                ...(notes?.trim()
                    ? {
                          notes: notes.trim(),
                      }
                    : {}),
            },
        )

        return response.data
    }

    const cancelReservation = async (
        reservationId: number,
    ): Promise<ReservationRecord> => {
        const response = await post<ReservationRecord>(
            `/reservations/${reservationId}/cancel`,
        )

        return response.data
    }

    const restoreReservation = async (
    reservationId: number,
): Promise<ReservationRecord> => {
    const response = await post<ReservationRecord>(
        `/reservations/${reservationId}/restore`,
    )

    return response.data
}
    const logout = async (): Promise<void> => {
        if (authStore.token) {
            try {
                await post<null>('/auth/logout')
            } catch {
                // Local session is still cleared when server revoke fails.
            }
        }

        authStore.clearAuth()
        toast.add({
            severity: 'info',
            summary: 'Logout',
            detail: 'Anda telah keluar.',
            life: 3000,
        })
        await navigateTo('/login')
    }

    return {
        getProfile,
        getLoans,
        getFines,
        getReservations,
        getIncidents,
        renewLoanItem,
        createReservation,
        cancelReservation,
        restoreReservation,
        logout,
    }
}