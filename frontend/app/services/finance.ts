import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'

export interface FinanceSummary {
    fines: {
        billed: number
        paid: number
        outstanding: number
    }
    incidents: {
        billed: number
        paid: number
        outstanding: number
    }
    total_received: number
    filters: {
        date_from?: string | null
        date_to?: string | null
    }
}

export interface FinanceTransaction {
    id: number
    type: 'DENDA' | 'GANTI_RUGI'
    date: string
    member_name: string
    member_number: string
    book_title: string
    reference: string
    amount: number
    payment_method: string
    payment_reference: string | null
    received_by_name: string | null
}

export interface FinanceTransactionListResponse {
    items: FinanceTransaction[]
    pagination: {
        total: number
        per_page: number
        current_page: number
        last_page: number
    }
}

export const useFinanceService = () => {
    const { apiFetch } = useApi()
    const authStore = useAuthStore()

    const headers = () => ({
        Accept: 'application/json',
        Authorization: authStore.token ? `Bearer ${authStore.token}` : '',
    })

    const getSummary = async (params: Record<string, unknown> = {}) => {
        return apiFetch<FinanceSummary>('/finance/summary', {
            method: 'GET',
            headers: headers(),
            params,
        })
    }

    const getTransactions = async (params: Record<string, unknown> = {}) => {
        return apiFetch<FinanceTransactionListResponse>('/finance/transactions', {
            method: 'GET',
            headers: headers(),
            params,
        })
    }

    return {
        getSummary,
        getTransactions,
    }
}
