export interface MemberType {
    id: number
    code: string
    name: string
}

export interface AcademicReference {
    id: number
    code: string
    name: string
}

export interface MemberUser {
    id: number
    name: string
    email: string
}

export interface MemberProfile {
    id: number
    member_number: string
    name: string
    identity_number: string | null
    member_type: MemberType | null
    class_level: AcademicReference | null
    major: AcademicReference | null
    user: MemberUser | null
    joined_at: string | null
    status: string
    notes: string | null
}

export interface LoanSummary {
    total_items: number
    active_items: number
    returned_items: number
    overdue_items: number
}

export interface LoanItemRecord {
    id: number
    borrowed_at: string | null
    due_at: string | null
    returned_at: string | null
    renewal_count: number
    status: string
    is_overdue: boolean
    overdue_days: number
    fine: {
        id: number
        late_days: number
        rate_per_day: number | string
        amount: number | string
        amount_paid: number | string
        status: string
    } | null
    book_copy?: {
        id: number
        barcode: string | null
        inventory_number: string | null
        book?: {
            id: number
            title: string
            isbn: string | null
        } | null
    } | null
    book?: {
        id: number
        title: string
        isbn: string | null
    } | null
    [key: string]: unknown
}

export interface LoanRecord {
    id: number
    loan_number: string
    member?: {
        id: number
        member_number: string
        name: string
        member_type?: MemberType | null
    } | null
    borrowed_at: string | null
    status: string
    notes: string | null
    items?: LoanItemRecord[]
    summary: LoanSummary
    created_at: string | null
}

export interface FineBook {
    id: number
    title: string
    isbn: string | null
}

export interface FineLoan {
    id: number
    loan_number: string
}

export interface FineRecord {
    id: number | string
    loan_item_id: number
    loan: FineLoan | null
    member?: {
        id: number
        member_number: string
        name: string
    } | null
    book: FineBook | null
    late_days: number
    rate_per_day: number | string
    late_unit?: 'day' | 'hour' | string | null
    late_units?: number | null
    rate_per_unit?: number | string | null
    amount: number | string
    amount_paid: number | string
    outstanding_amount: number | string
    status: string
    is_accruing?: boolean
    assessed_at: string | null
    paid_at: string | null
    notes: string | null
    payment_history?: unknown[]
}

export interface FineSummary {
    total_amount: number | string
    total_paid: number | string
    outstanding_amount: number | string
}

export interface ReservationBook {
    id: number
    title: string
    isbn: string | null
}

export interface AssignedCopy {
    id: number
    barcode: string
    inventory_number: string | null
}

export interface ReservationRecord {
    id: number
    reservation_number: string
    status: string
    reserved_at: string | null
    ready_at: string | null
    expires_at: string | null
    fulfilled_at: string | null
    book_returned_at: string | null
    cancelled_at: string | null
    notes: string | null
    book: ReservationBook | null
    member?: {
        id: number
        name: string
        member_number: string
    } | null
    assigned_copy: AssignedCopy | null
}

export interface BookIncidentRecord {
    id: number
    loan_item_id: number
    book_copy_id: number | null
    type: string
    status: string
    resolution_type: string | null
    opened_at: string | null
    resolved_at: string | null
    notes: string | null
    loan?: {
        id: number
        loan_number: string
    } | null
    book?: {
        id: number
        title: string
        isbn: string | null
    } | null
    book_copy?: {
        id: number
        barcode: string | null
        inventory_number: string | null
    } | null
    replacement_book_copy?: {
        id: number
        barcode: string | null
        inventory_number: string | null
    } | null
    resolved_by?: {
        id: number
        name: string
    } | null
}

export interface PaginationMeta {
    current_page: number
    from?: number | null
    last_page: number
    per_page: number
    to?: number | null
    total: number
}

export interface PaginatedResult<T> {
    items: T[]
    meta: PaginationMeta | null
    summary?: FineSummary
}