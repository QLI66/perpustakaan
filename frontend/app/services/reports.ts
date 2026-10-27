import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'

export interface ReportOverview {
    period: {
        date_from: string | null
        date_to: string | null
    }
    reports: {
        loans: Record<string, unknown>
        returns: Record<string, unknown>
        fines: Record<string, unknown>
        incidents: Record<string, unknown>
        acquisitions: Record<string, unknown>
        stock_opnames: Record<string, unknown>
        reservations: Record<string, unknown>
        members: Record<string, unknown>
        catalog: Record<string, unknown>
    }
}

export interface ReportDetailResponse {
    summary: Record<string, unknown>
    items?: unknown[]
    details?: unknown[]
    period?: {
        date_from: string | null
        date_to: string | null
    }
}

export const useReportsService = () => {
    const { apiFetch } = useApi()
    const authStore = useAuthStore()

    const headers = () => ({
        Accept: 'application/json',
        Authorization: authStore.token ? `Bearer ${authStore.token}` : '',
    })

    const getOverview = async (params: Record<string, unknown> = {}) => {
        return apiFetch<ReportOverview>('/reports/overview', {
            method: 'GET',
            headers: headers(),
            params,
        })
    }

    const getReportDetail = async (reportType: string, params: Record<string, unknown> = {}) => {
        return apiFetch<ReportDetailResponse>(`/reports/${reportType}`, {
            method: 'GET',
            headers: headers(),
            params,
        })
    }

    const exportReport = async (reportType: string, params: Record<string, unknown> = {}) => {
        const query = new URLSearchParams()
        if (params.date_from) query.append('date_from', String(params.date_from))
        if (params.date_to) query.append('date_to', String(params.date_to))
        
        const queryString = query.toString()
        const url = `${getApiBaseUrl()}/reports/export/${reportType}${queryString ? '?' + queryString : ''}`

        const response = await $fetch<Blob>(url, {
            method: 'GET',
            headers: headers(),
            responseType: 'blob'
        })
        
        return response
    }

    return {
        getOverview,
        getReportDetail,
        exportReport
    }
}
