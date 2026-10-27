import type {
    BookCopy,
    BookCopyForm,
    BookCopyPagination,
    CreateLocationForm,
} from '~/types/book-copy'

interface ListPayload {
    items?: BookCopy[]
    pagination?: BookCopyPagination
}

interface LookupBook {
    id: number
    title: string
    isbn?: string | null
}

interface LookupLocation {
    id: number
    code: string
    name: string
}

export const useBookCopies = () => {
    const { apiFetch } = useApi()
    const authStore = useAuthStore()

    const headers = () => ({
        Accept: 'application/json',
        ...(authStore.token
            ? {
                  Authorization: `Bearer ${authStore.token}`,
              }
            : {}),
    })

    const listCopies = async (
        page = 1,
        perPage = 15,
        search = '',
        status = '',
    ) => {
        const response = await apiFetch<ListPayload>(
            '/book-copies',
            {
                method: 'GET',
                query: {
                    page,
                    per_page: perPage,
                    ...(search.trim()
                        ? {
                              search: search.trim(),
                          }
                        : {}),
                    ...(status
                        ? {
                              status,
                          }
                        : {}),
                },
                headers: headers(),
            },
        )

        return {
            items: response.data.items ?? [],
            pagination: response.data.pagination ?? null,
        }
    }

    const getCopy = async (
        id: number,
    ): Promise<BookCopy> => {
        const response = await apiFetch<BookCopy>(
            `/book-copies/${id}`,
            {
                method: 'GET',
                headers: headers(),
            },
        )

        return response.data
    }

    const createCopy = async (
        payload: BookCopyForm,
    ): Promise<BookCopy> => {
        if (!payload.book_id) {
            throw new Error('Buku wajib dipilih.')
        }

        if (!payload.location_id) {
            throw new Error('Lokasi wajib dipilih.')
        }

        if (!payload.barcode.trim()) {
            throw new Error('Barcode wajib diisi.')
        }

        const response = await apiFetch<BookCopy>(
            '/book-copies',
            {
                method: 'POST',
                body: {
                    book_id: payload.book_id,
                    barcode: payload.barcode.trim(),
                    inventory_number:
                        payload.inventory_number.trim() || null,
                    location_id: payload.location_id,
                    acquisition_date:
                        payload.acquisition_date || null,
                    acquisition_source:
                        payload.acquisition_source || 'PURCHASE',
                    acquisition_price:
                        payload.acquisition_price ?? null,
                    condition:
                        payload.condition || null,
                    notes:
                        payload.notes.trim() || null,
                },
                headers: headers(),
            },
        )

        return response.data
    }

    const updateCopy = async (
        id: number,
        payload: BookCopyForm,
    ): Promise<BookCopy> => {
        const response = await apiFetch<BookCopy>(
            `/book-copies/${id}`,
            {
                method: 'PATCH',
                body: {
                    book_id: payload.book_id,
                    barcode: payload.barcode.trim(),
                    inventory_number:
                        payload.inventory_number.trim() || null,
                    location_id: payload.location_id,
                    acquisition_date:
                        payload.acquisition_date || null,
                    acquisition_source:
                        payload.acquisition_source || 'PURCHASE',
                    acquisition_price:
                        payload.acquisition_price ?? null,
                    condition:
                        payload.condition || null,
                    notes:
                        payload.notes.trim() || null,
                },
                headers: headers(),
            },
        )

        return response.data
    }

    const deleteCopy = async (
        id: number,
    ): Promise<void> => {
        await apiFetch<null>(
            `/book-copies/${id}`,
            {
                method: 'DELETE',
                headers: headers(),
            },
        )
    }

    const listBooks = async (): Promise<LookupBook[]> => {
        const response = await apiFetch<{
            items?: LookupBook[]
            pagination?: unknown
        }>(
            '/books',
            {
                method: 'GET',
                query: {
                    per_page: 50,
                },
                headers: headers(),
            },
        )

        return response.data.items ?? []
    }

    const listLocations = async (): Promise<LookupLocation[]> => {
        const response = await apiFetch<{
            items?: LookupLocation[]
            pagination?: unknown
        }>(
            '/locations',
            {
                method: 'GET',
                query: {
                    per_page: 50,
                },
                headers: headers(),
            },
        )

        return response.data.items ?? []
    }

    const createLocation = async (
        payload: CreateLocationForm,
    ): Promise<LookupLocation> => {
        const code = payload.code.trim()
        const name = payload.name.trim()

        if (!code) {
            throw new Error('Kode rak wajib diisi.')
        }

        if (!name) {
            throw new Error('Nama rak wajib diisi.')
        }

        const response = await apiFetch<LookupLocation>(
            '/locations',
            {
                method: 'POST',
                body: {
                    code,
                    name,
                },
                headers: headers(),
            },
        )

        return response.data
    }

    return {
        listCopies,
        getCopy,
        createCopy,
        updateCopy,
        deleteCopy,
        listBooks,
        listLocations,
        createLocation,
    }
}