import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'

export interface BookCopyListItem {
    id: number
    barcode: string
    inventory_number?: string | null
    status: string
    book?: {
        id: number
        title: string
    } | null
    location?: {
        id: number
        name: string
    } | null
}

export interface BookCopiesQueryParams {
    search?: string
    status?: string
    page?: number
    per_page?: number
}

export interface PaginatedResponse<T> {
    items: T[]
    pagination: {
        total: number
        per_page: number
        current_page: number
        last_page: number
    }
}

export const useBookCopiesService = () => {
    const { apiFetch } = useApi()
    const authStore = useAuthStore()

    const headers = () => ({
        Accept: 'application/json',
        Authorization: authStore.token ? `Bearer ${authStore.token}` : '',
    })

    const getBookCopies = async (params?: BookCopiesQueryParams) => {
        const query = new URLSearchParams()

        if (params?.search) query.append('search', params.search)
        if (params?.status) query.append('status', params.status)
        if (params?.page) query.append('page', params.page.toString())
        if (params?.per_page) query.append('per_page', params.per_page.toString())

        const queryString = query.toString()
        const url = `/book-copies${queryString ? `?${queryString}` : ''}`

        return await apiFetch<PaginatedResponse<BookCopyListItem>>(url, {
            method: 'GET',
            headers: headers(),
        })
    }

    const getCopyByBarcode = async (barcode: string) => {
        return await apiFetch<BookCopyListItem>(
            `/book-copies/barcode/${encodeURIComponent(barcode)}`,
            {
                method: 'GET',
                headers: headers(),
            }
        )
    }

    return {
        getBookCopies,
        getCopyByBarcode,
    }
}
