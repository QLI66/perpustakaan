import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'

export interface FineMember {
    id: number
    member_number: string
    name: string
}

export interface FineLoan {
    id: number
    loan_number: string
}

export interface FineBook {
    id: number
    title: string
    isbn: string | null
}

export interface FinePaymentRecord {
    id: number
    amount: string
    payment_method: 'CASH' | 'TRANSFER' | 'OTHER'
    reference_number: string | null
    notes: string | null
    paid_at: string | null
    received_by?: { id: number; name: string } | null
    created_at?: string | null
}

export interface StaffFine {
    id: number | string
    loan_item_id: number
    loan: FineLoan | null
    member: FineMember | null
    book: FineBook | null
    late_days: number
    rate_per_day: string
    late_unit?: 'day' | 'hour' | string | null
    late_units?: number | null
    rate_per_unit?: string | null
    amount: string
    amount_paid: string
    outstanding_amount: string
    status: 'UNPAID' | 'PARTIAL' | 'PAID'
    is_accruing?: boolean
    assessed_at: string | null
    paid_at: string | null
    notes: string | null
    payment_history?: FinePaymentRecord[]
}

export interface FineListResponse {
    items: StaffFine[]
    pagination: {
        total: number
        per_page: number
        current_page: number
        last_page: number
    }
}

export interface FinePaymentPayload {
    amount: number
    payment_method: 'CASH' | 'TRANSFER' | 'OTHER'
    reference_number?: string
    notes?: string
    paid_at?: string
}

export const useFinesService = () => {
    const { apiFetch } = useApi()
    const authStore = useAuthStore()

    const headers = () => ({
        Accept: 'application/json',
        Authorization: authStore.token ? `Bearer ${authStore.token}` : '',
    })

    const getFines = async (params: Record<string, unknown> = {}) => {
        return apiFetch<FineListResponse>('/fines', {
            method: 'GET',
            headers: headers(),
            params,
        })
    }

    const getFine = async (id: number) => {
        return apiFetch<StaffFine>(`/fines/${id}`, {
            method: 'GET',
            headers: headers(),
        })
    }

    const payFine = async (id: number, payload: FinePaymentPayload, idempotencyKey: string) => {
        return apiFetch<FinePaymentRecord>(`/fines/${id}/payments`, {
            method: 'POST',
            headers: {
                ...headers(),
                'Idempotency-Key': idempotencyKey,
            },
            body: payload,
        })
    }

    return {
        getFines,
        getFine,
        payFine,
    }
}
