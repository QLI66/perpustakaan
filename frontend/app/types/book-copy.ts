export type BookCopyStatus =
    | 'AVAILABLE'
    | 'BORROWED'
    | 'RESERVED'
    | 'LOST'
    | 'DAMAGED'
    | 'MAINTENANCE'

export type BookCopyCondition =
    | 'NEW'
    | 'GOOD'
    | 'FAIR'
    | 'POOR'

export type AcquisitionSource =
    | 'PURCHASE'
    | 'DONATION'
    | 'GRANT'
    | 'GIFT'
    | 'OTHER'

export interface BookCopyBook {
    id: number
    title: string
    isbn: string | null
}

export interface BookCopyLocation {
    id: number
    code: string
    name: string
}

export interface BookCopy {
    id: number
    barcode: string
    inventory_number: string | null
    status: BookCopyStatus
    condition: BookCopyCondition | null
    acquisition_date: string | null
    acquisition_source: AcquisitionSource | null
    acquisition_price: number | string | null
    notes: string | null
    book: BookCopyBook | null
    location: BookCopyLocation | null
    created_at: string | null
    updated_at: string | null
}

export interface BookCopyForm {
    book_id: number | null
    barcode: string
    inventory_number: string
    location_id: number | null
    acquisition_date: string
    acquisition_source: AcquisitionSource
    acquisition_price: number | null
    condition: BookCopyCondition | null
    notes: string
}

export interface BookCopyBatchForm {
    book_id: number | null
    copies: BookCopyForm[]
}

export interface CreateLocationForm {
    code: string
    name: string
}

export interface BookCopyPagination {
    current_page: number
    per_page: number
    total: number
    last_page: number
}