import type {
    StaffBook,
    StaffBookForm,
    StaffLookupItem,
    StaffPaginated,
} from '~/types/staff'

interface ApiResponse<T> {
    success: boolean
    message: string
    data: T
}

interface RawPaginatedPayload {
    items?: unknown
    pagination?: unknown
    data?: unknown
    meta?: unknown
}

const unwrapPaginated = <T>(
    response: ApiResponse<RawPaginatedPayload>,
): StaffPaginated<T> => {
    const payload = response.data

    if (!payload) {
        return {
            items: [],
            pagination: null,
        }
    }

    if (Array.isArray(payload.items)) {
        return {
            items: payload.items as T[],
            pagination: (payload.pagination ?? null) as StaffPaginated<T>['pagination'],
        }
    }

    if (Array.isArray(payload.data)) {
        return {
            items: payload.data as T[],
            pagination: (payload.meta ?? null) as StaffPaginated<T>['pagination'],
        }
    }

    return {
        items: [],
        pagination: null,
    }
}

export const useStaffBooks = () => {
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

    const listBooks = async (
        page = 1,
        perPage = 15,
        search = '',
    ): Promise<StaffPaginated<StaffBook>> => {
        const response = await apiFetch<RawPaginatedPayload>(
            '/books',
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
                },
                headers: headers(),
            },
        )

        return unwrapPaginated<StaffBook>(response)
    }

    const getBook = async (
        id: number,
    ): Promise<StaffBook> => {
        const response = await apiFetch<StaffBook>(
            `/books/${id}`,
            {
                method: 'GET',
                headers: headers(),
            },
        )

        return response.data
    }

    const createBook = async (
        payload: StaffBookForm,
    ): Promise<StaffBook> => {
        const response = await apiFetch<StaffBook>(
            '/books',
            {
                method: 'POST',
                body: {
                    isbn: payload.isbn || null,
                    title: payload.title,
                    subtitle: payload.subtitle || null,
                    description: payload.description || null,
                    publication_year:
                        payload.publication_year || null,
                    edition: payload.edition || null,
                    language: payload.language || 'id',
                    collection_type_id:
                        payload.collection_type_id,
                    publisher_id:
                        payload.publisher_id || null,
                    cover: payload.cover || null,
                    author_ids: payload.author_ids,
                    category_ids: payload.category_ids,
                },
                headers: headers(),
            },
        )

        return response.data
    }

    const updateBook = async (
        id: number,
        payload: StaffBookForm,
    ): Promise<StaffBook> => {
        const response = await apiFetch<StaffBook>(
            `/books/${id}`,
            {
                method: 'PATCH',
                body: {
                    isbn: payload.isbn || null,
                    title: payload.title,
                    subtitle: payload.subtitle || null,
                    description: payload.description || null,
                    publication_year:
                        payload.publication_year || null,
                    edition: payload.edition || null,
                    language: payload.language || 'id',
                    collection_type_id:
                        payload.collection_type_id,
                    publisher_id:
                        payload.publisher_id || null,
                    cover: payload.cover || null,
                    author_ids: payload.author_ids,
                    category_ids: payload.category_ids,
                },
                headers: headers(),
            },
        )

        return response.data
    }

    const deleteBook = async (
        id: number,
    ): Promise<void> => {
        await apiFetch<null>(
            `/books/${id}`,
            {
                method: 'DELETE',
                headers: headers(),
            },
        )
    }

    const listLookup = async (
        path: string,
    ): Promise<StaffLookupItem[]> => {
        const response = await apiFetch<unknown>(
            path,
            {
                method: 'GET',
                headers: headers(),
            },
        )

        const payload = response.data as
            | {
                  items?: unknown
                  data?: unknown
              }
            | unknown[]

        if (Array.isArray(payload)) {
            return payload as StaffLookupItem[]
        }

        if (
            payload &&
            typeof payload === 'object' &&
            Array.isArray(payload.items)
        ) {
            return payload.items as StaffLookupItem[]
        }

        if (
            payload &&
            typeof payload === 'object' &&
            Array.isArray(payload.data)
        ) {
            return payload.data as StaffLookupItem[]
        }

        return []
    }

    const createAuthor = async (
        name: string,
    ): Promise<StaffLookupItem> => {
        const response = await apiFetch<StaffLookupItem>(
            '/catalog/authors',
            {
                method: 'POST',
                body: { name },
                headers: headers(),
            },
        )

        return response.data
    }

    const createCategory = async (
        name: string,
    ): Promise<StaffLookupItem> => {
        const response = await apiFetch<StaffLookupItem>(
            '/catalog/categories',
            {
                method: 'POST',
                body: { name },
                headers: headers(),
            },
        )

        return response.data
    }

    const listCategories = () =>
        listLookup('/catalog/categories')

    const listAuthors = () =>
        listLookup('/catalog/authors')

    const listPublishers = () =>
        listLookup('/catalog/publishers')

    const listCollectionTypes = () =>
        listLookup('/catalog/collection-types')

    return {
        listBooks,
        getBook,
        createBook,
        updateBook,
        deleteBook,
        createAuthor,
        createCategory,
        listCategories,
        listAuthors,
        listPublishers,
        listCollectionTypes,
    }
}