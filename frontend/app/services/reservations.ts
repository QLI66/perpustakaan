import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'
import type { ReservationCollectionResponse, Reservation } from '~/types/circulation'

export const useReservationsService = () => {
    const { apiFetch } = useApi()
    const authStore = useAuthStore()

    const getHeaders = () => {
        return {
            Accept: 'application/json',
            Authorization: authStore.token ? `Bearer ${authStore.token}` : '',
        }
    }

    const getReservations = async (
        params: Record<string, unknown> = {},
        signal?: AbortSignal,
    ) => {
        return apiFetch<ReservationCollectionResponse>('/reservations', {
            method: 'GET',
            headers: getHeaders(),
            params,
            signal,
        })
    }

    const getReservation = async (id: number) => {
        return apiFetch<Reservation>(`/reservations/${id}`, {
            method: 'GET',
            headers: getHeaders(),
        })
    }

    const cancelReservation = async (id: number) => {
        return apiFetch<Reservation>(`/reservations/${id}/cancel`, {
            method: 'POST',
            headers: getHeaders(),
        })
    }

    const assignReservation = async (id: number, book_copy_id: number) => {
        return apiFetch<Reservation>(`/reservations/${id}/assign`, {
            method: 'POST',
            headers: getHeaders(),
            body: { book_copy_id },
        })
    }

    return {
        getReservations,
        getReservation,
        cancelReservation,
        assignReservation,
    }
}
