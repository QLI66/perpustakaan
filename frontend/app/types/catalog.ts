export interface CatalogPerson {
    id: number
    name: string
    biography?: string | null
}

export interface CatalogCategory {
    id: number
    code?: string | null
    name: string
    parent_id?: number | null
    description?: string | null
    sort_order?: number
}

export interface CatalogPublisher {
    id: number
    name: string
    address?: string | null
    website?: string | null
}

export interface CatalogCollectionType {
    id: number
    code?: string | null
    name: string
    description?: string | null
}

export interface CatalogBookCopy {
    id?: number
    barcode?: string
    inventory_number?: string | null
    location_id?: number | null
    status?: string
    condition?: string
}

export interface CatalogAvailability {
    total_copies: number
    available_copies: number
    borrowed_copies: number
    reserved_copies: number
    lost_copies: number
    damaged_copies: number
    maintenance_copies: number
    is_available: boolean
}

export interface CatalogLocation {
    id: number
    code: string
    name: string
}

export interface CatalogTarget {
    id: number
    class_level?: string | null
    major?: string | null
}

export interface CatalogBook {
    id: number
    isbn?: string | null
    title: string
    subtitle?: string | null
    description?: string | null
    publication_year?: number | null
    edition?: string | null
    language?: string | null

    collection_type?: CatalogCollectionType | null
    publisher?: CatalogPublisher | null
    authors?: CatalogPerson[]
    categories?: CatalogCategory[]
    targets?: CatalogTarget[]

    availability?: CatalogAvailability
    locations?: CatalogLocation[]

    book_copies?: CatalogBookCopy[]
    copies?: CatalogBookCopy[]

    available_count?: number | null
    available_copies?: number | null
    total_copies?: number | null
    copies_count?: number | null
    book_copies_count?: number | null
}

export interface CatalogPagination {
    current_page: number
    per_page: number
    total: number
    last_page: number
    from?: number | null
    to?: number | null
}

export interface CatalogBookList {
    items: CatalogBook[]
    pagination: CatalogPagination
}

export interface CatalogFilters {
    search: string
    category_id: number | null
    author_id: number | null
    publisher_id: number | null
    collection_type_id: number | null
    status: string
    page: number
    per_page: number
}
