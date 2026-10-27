import type {
    CatalogBook,
    CatalogBookList,
    CatalogCategory,
    CatalogCollectionType,
    CatalogFilters,
    CatalogPerson,
    CatalogPublisher,
} from '~/types/catalog'

interface ApiEnvelope<T> {
    success: boolean
    message: string
    data: T
}

const normalizeArray = <T>(value: unknown): T[] => {
    if (Array.isArray(value)) {
        return value as T[]
    }

    return []
}

export const useCatalog = () => {
    const { apiFetch } = useApi()

    const getBooks = async (
        params: Partial<CatalogFilters> = {},
    ): Promise<CatalogBookList> => {
        const query = new URLSearchParams()

        const entries: Record<string, unknown> = {
            search: params.search,
            category_id: params.category_id,
            author_id: params.author_id,
            publisher_id: params.publisher_id,
            collection_type_id: params.collection_type_id,
            status: params.status
        ? String(params.status).toUpperCase()
        : undefined,
            page: params.page,
            per_page: params.per_page,
        }

        Object.entries(entries).forEach(([key, value]) => {
            if (
                value !== undefined &&
                value !== null &&
                value !== ''
            ) {
                query.set(key, String(value))
            }
        })

        const queryString = query.toString()
        const path = queryString
            ? `/catalog/books?${queryString}`
            : '/catalog/books'

        const response = await apiFetch<CatalogBookList>(path)
        const payload = response as unknown as ApiEnvelope<CatalogBookList>

        const data = payload.data

        return {
            items: Array.isArray(data?.items)
                ? data.items
                : [],
            pagination: data?.pagination ?? {
                current_page: 1,
                last_page: 1,
                per_page: 12,
                total: 0,
                from: null,
                to: null,
            },
        }
    }

    const getBook = async (
        id: number,
    ): Promise<CatalogBook> => {
        const response = await apiFetch<CatalogBook>(
            `/catalog/books/${id}`,
        )

        return response.data
    }

    const getCategories = async (): Promise<CatalogCategory[]> => {
        const response = await apiFetch<CatalogCategory[]>(
            '/catalog/categories',
        )

        return normalizeArray<CatalogCategory>(response.data)
    }

    const getAuthors = async (): Promise<CatalogPerson[]> => {
        const response = await apiFetch<CatalogPerson[]>(
            '/catalog/authors',
        )

        return normalizeArray<CatalogPerson>(response.data)
    }

    const getPublishers = async (): Promise<CatalogPublisher[]> => {
        const response = await apiFetch<CatalogPublisher[]>(
            '/catalog/publishers',
        )

        return normalizeArray<CatalogPublisher>(response.data)
    }

    const getCollectionTypes = async (): Promise<CatalogCollectionType[]> => {
        const response = await apiFetch<CatalogCollectionType[]>(
            '/catalog/collection-types',
        )

        return normalizeArray<CatalogCollectionType>(response.data)
    }

    return {
        getBooks,
        getBook,
        getCategories,
        getAuthors,
        getPublishers,
        getCollectionTypes,
    }
}
