import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'
import type { LoanListResponse, Loan, LoanItem } from '~/types/circulation'

export const useLoansService = () => {
    const { apiFetch } = useApi()
    const authStore = useAuthStore()

    const getHeaders = () => {
        return {
            Accept: 'application/json',
            Authorization: authStore.token ? `Bearer ${authStore.token}` : '',
        }
    }

    const getLoans = async (params: Record<string, unknown> = {}) => {
        return apiFetch<LoanListResponse>('/loans', {
            method: 'GET',
            headers: getHeaders(),
            params,
        })
    }

    const getLoan = async (id: number) => {
        return apiFetch<Loan>(`/loans/${id}`, {
            method: 'GET',
            headers: getHeaders(),
        })
    }

    const createLoan = async (payload: { member_id: number; book_copy_ids: number[]; notes?: string; borrowed_at?: string; loan_duration_days?: number }) => {
        return apiFetch<Loan>('/loans', {
            method: 'POST',
            headers: getHeaders(),
            body: payload,
        })
    }

    const returnItem = async (loanId: number, loanItemId: number, payload?: { returned_at?: string }) => {
        return apiFetch<LoanItem>(`/loans/${loanId}/items/${loanItemId}/return`, {
            method: 'POST',
            headers: getHeaders(),
            body: payload || {},
        })
    }

    const renewItem = async (loanId: number, loanItemId: number) => {
        return apiFetch<LoanItem>(`/loans/${loanId}/items/${loanItemId}/renew`, {
            method: 'POST',
            headers: getHeaders(),
        })
    }

    return {
        getLoans,
        getLoan,
        createLoan,
        returnItem,
        renewItem,
    }
}
