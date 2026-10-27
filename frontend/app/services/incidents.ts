import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'

export interface IncidentMember {
    id: number
    member_number: string
    name: string
}

export interface IncidentLoan {
    id: number
    loan_number: string
}

export interface IncidentBook {
    id: number
    title: string
    isbn: string | null
}

export interface IncidentBookCopy {
    id: number
    barcode: string
}

export interface IncidentResolvedBy {
    id: number
    name: string
}

export interface StaffIncident {
    id: number
    type: 'LOST' | 'DAMAGED'
    status: 'OPEN' | 'RESOLVED'
    loan_item: { id: number } | null
    loan: IncidentLoan | null
    member: IncidentMember | null
    book_copy: IncidentBookCopy | null
    book: IncidentBook | null
    resolution_type: 'PAID_MONEY' | 'REPLACED_BOOK' | null
    replacement_book_copy: IncidentBookCopy | null
    amount_due: string | null
    amount_paid: string | null
    outstanding_amount: string | null
    opened_at: string | null
    resolved_at: string | null
    resolved_by: IncidentResolvedBy | null
    notes: string | null
}

export interface IncidentListResponse {
    items: StaffIncident[]
    pagination: {
        total: number
        per_page: number
        current_page: number
        last_page: number
    }
}

export interface StoreIncidentPayload {
    loan_item_id: number
    type: 'LOST' | 'DAMAGED'
    notes?: string
    opened_at?: string
}

export interface ResolvePaymentPayload {
    notes?: string
    resolved_at?: string
}

export interface ResolveReplacementPayload {
    replacement_book_copy_id: number
    notes?: string
    resolved_at?: string
}

export const useIncidentsService = () => {
    const { apiFetch } = useApi()
    const authStore = useAuthStore()

    const headers = () => ({
        Accept: 'application/json',
        Authorization: authStore.token ? `Bearer ${authStore.token}` : '',
    })

    const getIncidents = async (params: Record<string, unknown> = {}) => {
        return apiFetch<IncidentListResponse>('/book-incidents', {
            method: 'GET',
            headers: headers(),
            params,
        })
    }

    const getIncident = async (id: number) => {
        return apiFetch<StaffIncident>(`/book-incidents/${id}`, {
            method: 'GET',
            headers: headers(),
        })
    }

    const createIncident = async (payload: StoreIncidentPayload) => {
        return apiFetch<StaffIncident>('/book-incidents', {
            method: 'POST',
            headers: headers(),
            body: payload,
        })
    }

    const resolveByPayment = async (id: number, payload: ResolvePaymentPayload = {}) => {
        return apiFetch<StaffIncident>(`/book-incidents/${id}/resolve/payment`, {
            method: 'POST',
            headers: headers(),
            body: payload,
        })
    }

    const resolveByReplacement = async (id: number, payload: ResolveReplacementPayload) => {
        return apiFetch<StaffIncident>(`/book-incidents/${id}/resolve/replacement`, {
            method: 'POST',
            headers: headers(),
            body: payload,
        })
    }

    return {
        getIncidents,
        getIncident,
        createIncident,
        resolveByPayment,
        resolveByReplacement,
    }
}
