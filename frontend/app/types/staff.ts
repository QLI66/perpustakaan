export interface StaffLookupItem {
    id: number
    code?: string
    name: string
}

export interface StaffBook {
    id: number
    isbn: string | null
    title: string
    subtitle: string | null
    description: string | null
    publication_year: number | null
    edition: string | null
    language: string | null
    cover: string | null

    collection_type: StaffLookupItem | null

    publisher: {
        id: number
        name: string
    } | null

    authors: StaffLookupItem[]
    categories: StaffLookupItem[]

    copies_count: number
    available_copies_count: number

    created_at: string | null
    updated_at: string | null
}

export interface StaffBookForm {
    isbn: string
    title: string
    subtitle: string
    description: string
    publication_year: number | null
    edition: string
    language: string
    collection_type_id: number | null
    publisher_id: number | null
    cover: string
    author_ids: number[]
    category_ids: number[]
}

export interface StaffPagination {
    current_page: number
    per_page: number
    total: number
    last_page: number
}

export interface StaffPaginated<T> {
    items: T[]
    pagination: StaffPagination | null
}