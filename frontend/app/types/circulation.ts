export interface Member {
    id: number
    user_id: number
    member_type_id: number
    member_number: string
    name: string
    email: string
    phone: string | null
    address: string | null
    status: 'ACTIVE' | 'INACTIVE' | 'SUSPENDED'
    joined_date: string
    expiry_date: string | null
    created_at: string
    updated_at: string
}

export interface Book {
    id: number
    title: string
    isbn: string | null
    // Add other relevant book fields
}

export interface BookCopy {
    id: number
    book_id: number
    barcode: string
    inventory_number: string | null
    status: string
    book?: Book
}

export interface LoanItem {
    id: number
    loan_id: number
    book_copy_id: number
    status: 'ACTIVE' | 'RETURNED' | 'LOST' | 'DAMAGED'
    due_at: string
    returned_at: string | null
    is_overdue: boolean
    overdue_days: number
    fine_amount: number | null
    fine_status: string | null
    fine: {
        id: number
        late_days: number
        rate_per_day: number | string
        amount: number | string
        amount_paid: number | string
        status: string
    } | null
    renewal_count: number
    book_copy?: BookCopy
}

export interface Loan {
    id: number
    loan_number: string
    member_id: number
    status: 'ACTIVE' | 'COMPLETED' | 'CANCELLED'
    borrowed_at: string
    notes: string | null
    member?: {
        id: number
        member_number: string
        name: string
        member_type?: { id: number; code: string; name: string } | null
    }
    items?: LoanItem[]
    summary?: {
        total_items: number
        active_items: number
        returned_items: number
        overdue_items: number
    }
    created_at?: string
}

export interface Reservation {
    id: number
    reservation_number: string
    member_id: number
    book_id: number
    book_copy_id: number | null
    status: 'PENDING' | 'READY' | 'FULFILLED' | 'CANCELLED' | 'EXPIRED'
    reserved_at: string
    ready_at: string | null
    expires_at: string | null
    fulfilled_at: string | null
    book_returned_at: string | null
    cancelled_at: string | null
    notes: string | null
    member?: {
        id: number
        name: string
        member_number: string
    }
    book?: {
        id: number
        title: string
        isbn: string | null
    }
    assigned_copy?: {
        id: number
        barcode: string
        inventory_number: string | null
    }
}

export interface LoanListResponse {
    items: Loan[]
    pagination: {
        current_page: number
        per_page: number
        total: number
        last_page: number
    }
}

/**
 * Reservation index uses Laravel ResourceCollection::collection($paginator)->response()->getData(true)
 * which produces { data: [...], meta: { current_page, last_page, per_page, total, ... }, links: {...} }
 * wrapped in ApiResponse, so the full shape is:
 * ApiResponse<{ data: Reservation[], meta: { current_page, last_page, per_page, total } }>
 */
export interface ReservationCollectionResponse {
    data: Reservation[]
    meta: {
        current_page: number
        per_page: number
        total: number
        last_page: number
        from: number | null
        to: number | null
    }
    links: {
        first: string | null
        last: string | null
        prev: string | null
        next: string | null
    }
}
