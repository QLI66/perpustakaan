import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'

export type StockOpnameStatus =
  | 'DRAFT'
  | 'IN_PROGRESS'
  | 'COMPLETED'
  | 'CANCELLED'

export type StockOpnameResult =
  | 'NOT_CHECKED'
  | 'FOUND'
  | 'MISSING'
  | 'DIFFERENT'

export type BookCopyCondition =
  | 'NEW'
  | 'GOOD'
  | 'FAIR'
  | 'POOR'

export type BookCopyStatus =
  | 'AVAILABLE'
  | 'BORROWED'
  | 'RESERVED'
  | 'LOST'
  | 'DAMAGED'
  | 'MAINTENANCE'

export interface StockOpnameLocation {
  id: number
  code: string
  name: string
}

export interface StockOpnameCreator {
  id: number
  name: string
  email: string
}

export interface StockOpnameBook {
  id: number
  title: string
}

export interface StockOpnameBookCopy {
  id: number
  barcode: string
  inventory_number: string | null
  status: BookCopyStatus
  condition: BookCopyCondition
  book?: StockOpnameBook | null
  location?: StockOpnameLocation | null
}

export interface StockOpnameChecker {
  id: number
  name: string
  email: string
}

export interface StockOpnameItem {
  id: number
  book_copy_id: number
  book_copy?: StockOpnameBookCopy | null
  expected_status: BookCopyStatus
  actual_status: BookCopyStatus | null
  expected_condition: BookCopyCondition
  actual_condition: BookCopyCondition | null
  result: StockOpnameResult
  checked_at: string | null
  checked_by: number | null
  checker?: StockOpnameChecker | null
  notes: string | null
  created_at: string
  updated_at: string
}

export interface StockOpname {
  id: number
  opname_number: string
  opname_date: string
  location_id: number | null
  location?: StockOpnameLocation | null
  status: StockOpnameStatus
  created_by: number | null
  creator?: StockOpnameCreator | null
  started_at: string | null
  completed_at: string | null
  notes: string | null
  items?: StockOpnameItem[]
}

export interface StockOpnameListResponse {
  items: StockOpname[]
  pagination: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export interface CreateStockOpnamePayload {
  opname_date: string
  location_id?: number | null
  notes?: string | null
}

export interface UpdateStockOpnamePayload {
  opname_date?: string
  location_id?: number | null
  notes?: string | null
}

export interface CheckStockOpnameItemPayload {
  result: StockOpnameResult
  actual_status?: BookCopyStatus | null
  actual_condition?: BookCopyCondition | null
  checked_at?: string | null
  notes?: string | null
}

export const useStockOpnamesService = () => {
  const { apiFetch } = useApi()
  const authStore = useAuthStore()

  const headers = () => ({
    Accept: 'application/json',
    Authorization: authStore.token
      ? `Bearer ${authStore.token}`
      : '',
  })

  const getStockOpnames = (
    params: Record<string, unknown> = {},
  ) => {
    return apiFetch<StockOpnameListResponse>(
      '/stock-opnames',
      {
        method: 'GET',
        headers: headers(),
        params,
      },
    )
  }

  const getStockOpname = (
    id: number | string,
  ) => {
    return apiFetch<StockOpname>(
      `/stock-opnames/${id}`,
      {
        method: 'GET',
        headers: headers(),
      },
    )
  }

  const createStockOpname = (
    payload: CreateStockOpnamePayload,
  ) => {
    return apiFetch<StockOpname>(
      '/stock-opnames',
      {
        method: 'POST',
        headers: headers(),
        body: payload,
      },
    )
  }

  const updateStockOpname = (
    id: number | string,
    payload: UpdateStockOpnamePayload,
  ) => {
    return apiFetch<StockOpname>(
      `/stock-opnames/${id}`,
      {
        method: 'PUT',
        headers: headers(),
        body: payload,
      },
    )
  }

  const deleteStockOpname = (
    id: number | string,
  ) => {
    return apiFetch<null>(
      `/stock-opnames/${id}`,
      {
        method: 'DELETE',
        headers: headers(),
      },
    )
  }

  const startStockOpname = (
    id: number | string,
  ) => {
    return apiFetch<StockOpname>(
      `/stock-opnames/${id}/start`,
      {
        method: 'POST',
        headers: headers(),
      },
    )
  }

  const checkItem = (
    stockOpnameId: number | string,
    itemId: number | string,
    payload: CheckStockOpnameItemPayload,
  ) => {
    return apiFetch<{ item: StockOpnameItem }>(
      `/stock-opnames/${stockOpnameId}/items/${itemId}/check`,
      {
        method: 'POST',
        headers: headers(),
        body: payload,
      },
    )
  }

  const completeStockOpname = (
    id: number | string,
  ) => {
    return apiFetch<StockOpname>(
      `/stock-opnames/${id}/complete`,
      {
        method: 'POST',
        headers: headers(),
      },
    )
  }

  const cancelStockOpname = (
    id: number | string,
  ) => {
    return apiFetch<StockOpname>(
      `/stock-opnames/${id}/cancel`,
      {
        method: 'POST',
        headers: headers(),
      },
    )
  }

  return {
    getStockOpnames,
    getStockOpname,
    createStockOpname,
    updateStockOpname,
    deleteStockOpname,
    startStockOpname,
    checkItem,
    completeStockOpname,
    cancelStockOpname,
  }
}

