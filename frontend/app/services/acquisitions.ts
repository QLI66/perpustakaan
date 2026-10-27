import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'

export type AcquisitionSource = 'PURCHASE' | 'DONATION' | 'GRANT' | 'GIFT' | 'OTHER'

export interface AcquisitionBook {
    id: number
    title: string
    isbn: string | null
}

export interface AcquisitionBookCopy {
    id: number
    barcode: string
    inventory_number: string | null
    location_id: number | null
    status: string
    condition: string
}

export interface AcquisitionItem {
    id: number
    book_id: number
    quantity: number
    unit_price: number | null
    notes: string | null
    book?: AcquisitionBook | null
    book_copies?: AcquisitionBookCopy[] | null
}

export interface Acquisition {
    id: number
    acquisition_number: string
    acquisition_date: string
    source_type: AcquisitionSource
    source_name: string | null
    reference_number: string | null
    total_amount: number | string | null
    created_by: number | null
    notes: string | null
    items?: AcquisitionItem[]
}

export interface AcquisitionListResponse {
    items: Acquisition[]
    pagination: {
        total: number
        per_page: number
        current_page: number
        last_page: number
    }
}

export interface StoreAcquisitionItemPayload {
    book_id: number
    quantity: number
    unit_price?: number | null
    notes?: string | null
}

export interface StoreAcquisitionPayload {
    acquisition_date: string
    source_type: AcquisitionSource
    source_name?: string | null
    reference_number?: string | null
    notes?: string | null
    items: StoreAcquisitionItemPayload[]
}

export interface UpdateAcquisitionPayload {
    acquisition_date?: string
    source_type?: AcquisitionSource
    source_name?: string | null
    reference_number?: string | null
    notes?: string | null
}

export const useAcquisitionsService = () => {
    const { apiFetch } = useApi()
    const authStore = useAuthStore()

    const headers = () => ({
        Accept: 'application/json',
        Authorization: authStore.token ? `Bearer ${authStore.token}` : '',
    })

    const getAcquisitions = async (params: Record<string, unknown> = {}) => {
        return apiFetch<AcquisitionListResponse>('/acquisitions', {
            method: 'GET',
            headers: headers(),
            params,
        })
    }

    const getAcquisition = async (id: number | string) => {
        return apiFetch<Acquisition>(`/acquisitions/${id}`, {
            method: 'GET',
            headers: headers(),
        })
    }

    const createAcquisition = async (payload: StoreAcquisitionPayload) => {
        return apiFetch<Acquisition>('/acquisitions', {
            method: 'POST',
            headers: headers(),
            body: payload,
        })
    }

    const updateAcquisition = async (id: number | string, payload: UpdateAcquisitionPayload) => {
        return apiFetch<Acquisition>(`/acquisitions/${id}`, {
            method: 'PATCH',
            headers: headers(),
            body: payload,
        })
    }

    const deleteAcquisition = async (id: number | string) => {
        return apiFetch<null>(`/acquisitions/${id}`, {
            method: 'DELETE',
            headers: headers(),
        })
    }

    return {
        getAcquisitions,
        getAcquisition,
        createAcquisition,
        updateAcquisition,
        deleteAcquisition,
    }
}
