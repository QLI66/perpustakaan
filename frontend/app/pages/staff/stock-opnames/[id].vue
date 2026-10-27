<script setup lang="ts">
import { FetchError } from 'ofetch'
import { useStockOpnamesService } from '~/services/stock-opnames'
import type {
  BookCopyCondition,
  BookCopyStatus,
  StockOpname,
  StockOpnameItem,
  StockOpnameResult,
} from '~/services/stock-opnames'
import type { ApiError } from '~/types/api'

definePageMeta({
  middleware: 'staff',
})

const route = useRoute()
const router = useRouter()
const {
  getStockOpname,
  startStockOpname,
  checkItem,
  completeStockOpname,
  cancelStockOpname,
  deleteStockOpname,
} = useStockOpnamesService()

const id = computed(() => String(route.params.id))

const loading = ref(true)
const actionLoading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const opname = ref<StockOpname | null>(null)

const barcodeInput = ref('')
const barcodeInputElement = ref<HTMLInputElement | null>(null)
const bookSearch = ref('')
const selectedItemId = ref<number | null>(null)

const selectedResult = ref<StockOpnameResult>('FOUND')
const actualStatus = ref<BookCopyStatus | null>(null)
const actualCondition = ref<BookCopyCondition | null>(null)
const itemNotes = ref('')

const statusOptions: BookCopyStatus[] = [
  'AVAILABLE',
  'BORROWED',
  'RESERVED',
  'LOST',
  'DAMAGED',
  'MAINTENANCE',
]

const conditionOptions: BookCopyCondition[] = [
  'NEW',
  'GOOD',
  'FAIR',
  'POOR',
]

const resultLabel = (value: StockOpnameResult) => {
  switch (value) {
    case 'NOT_CHECKED':
      return 'Belum Dicek'
    case 'FOUND':
      return 'Ditemukan'
    case 'MISSING':
      return 'Tidak Ditemukan'
    case 'DIFFERENT':
      return 'Berbeda'
    default:
      return value
  }
}

const resultClass = (value: StockOpnameResult) => {
  switch (value) {
    case 'NOT_CHECKED':
      return 'bg-slate-100 text-slate-700'
    case 'FOUND':
      return 'bg-green-100 text-green-700'
    case 'MISSING':
      return 'bg-red-100 text-red-700'
    case 'DIFFERENT':
      return 'bg-amber-100 text-amber-700'
    default:
      return 'bg-slate-100 text-slate-700'
  }
}

const opnameStatusLabel = (value: string) => {
  switch (value) {
    case 'DRAFT':
      return 'Draft'
    case 'IN_PROGRESS':
      return 'Sedang Berjalan'
    case 'COMPLETED':
      return 'Selesai'
    case 'CANCELLED':
      return 'Dibatalkan'
    default:
      return value
  }
}

const opnameStatusClass = (value: string) => {
  switch (value) {
    case 'DRAFT':
      return 'bg-slate-100 text-slate-700'
    case 'IN_PROGRESS':
      return 'bg-blue-100 text-blue-700'
    case 'COMPLETED':
      return 'bg-green-100 text-green-700'
    case 'CANCELLED':
      return 'bg-red-100 text-red-700'
    default:
      return 'bg-slate-100 text-slate-700'
  }
}

const formatDate = (value: string | null) => {
  if (!value) return '-'

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(date)
}

const formatDateTime = (value: string | null) => {
  if (!value) return '-'

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(date)
}

const items = computed<StockOpnameItem[]>(() => {
  return opname.value?.items || []
})

const totalItems = computed(() => items.value.length)

const checkedItems = computed(() => {
  return items.value.filter(
    (item) => item.result !== 'NOT_CHECKED',
  ).length
})

const uncheckedItems = computed(() => {
  return items.value.filter(
    (item) => item.result === 'NOT_CHECKED',
  ).length
})

const foundItems = computed(() => {
  return items.value.filter(
    (item) => item.result === 'FOUND',
  ).length
})

const missingItems = computed(() => {
  return items.value.filter(
    (item) => item.result === 'MISSING',
  ).length
})

const differentItems = computed(() => {
  return items.value.filter(
    (item) => item.result === 'DIFFERENT',
  ).length
})

const progressPercentage = computed(() => {
  if (totalItems.value === 0) {
    return 0
  }

  return Math.round(
    (checkedItems.value / totalItems.value) * 100,
  )
})

const selectedItem = computed(() => {
  if (selectedItemId.value === null) {
    return null
  }

  return (
    items.value.find(
      (item) => item.id === selectedItemId.value,
    ) || null
  )
})

const focusBarcodeInput = async () => {
  await nextTick()

  barcodeInputElement.value?.focus()
  barcodeInputElement.value?.select()
}

const bookSearchResults = computed(() => {
  const query = bookSearch.value.trim().toLowerCase()

  if (!query) {
    return []
  }

  return items.value
    .filter((item) => {
      const title =
        item.book_copy?.book?.title?.toLowerCase() || ''

      const barcode =
        item.book_copy?.barcode?.toLowerCase() || ''

      const inventory =
        item.book_copy?.inventory_number?.toLowerCase() || ''

      return (
        title.includes(query) ||
        barcode.includes(query) ||
        inventory.includes(query)
      )
    })
    .slice(0, 10)
})

const selectBookFromSearch = (item: StockOpnameItem) => {
  selectItem(item)
  bookSearch.value = ''
}

const canEditItems = computed(() => {
  return opname.value?.status === 'IN_PROGRESS'
})

const canComplete = computed(() => {
  return (
    opname.value?.status === 'IN_PROGRESS' &&
    uncheckedItems.value === 0
  )
})

const isApiError = (value: unknown): value is Pick<ApiError, 'message'> =>
  typeof value === 'object' &&
  value !== null &&
  'message' in value &&
  typeof value.message === 'string'

const loadData = async () => {
  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const response = await getStockOpname(id.value)

    opname.value = response.data
  } catch (error: unknown) {
    const payload: unknown =
      error instanceof FetchError ? error.data : undefined

    errorMessage.value =
      (isApiError(payload) ? payload.message : undefined) ||
      (error instanceof Error ? error.message : undefined) ||
      'Gagal memuat detail Stock Opname.'
  } finally {
    loading.value = false
  }
}

const backToList = () => {
  router.push('/staff/stock-opnames')
}

const startOpname = async () => {
  if (!opname.value) return

  const confirmed = window.confirm(
    'Mulai Stock Opname sekarang? Setelah dimulai, daftar copy buku akan dibuat sebagai snapshot pemeriksaan.',
  )

  if (!confirmed) return

  actionLoading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const response = await startStockOpname(
      opname.value.id,
    )

    opname.value = response.data

    successMessage.value =
      'Stock Opname berhasil dimulai. Daftar buku siap diperiksa.'
  } catch (error: unknown) {
    const payload: unknown =
      error instanceof FetchError ? error.data : undefined

    errorMessage.value =
      (isApiError(payload) ? payload.message : undefined) ||
      (error instanceof Error ? error.message : undefined) ||
      'Gagal memulai Stock Opname.'
  } finally {
    actionLoading.value = false
  }
}

const selectItem = (item: StockOpnameItem) => {
  selectedItemId.value = item.id

  selectedResult.value =
    item.result === 'NOT_CHECKED'
      ? 'FOUND'
      : item.result

  actualStatus.value =
    item.actual_status ?? item.expected_status

  actualCondition.value =
    item.actual_condition ?? item.expected_condition

  itemNotes.value = item.notes || ''

  nextTick(() => {
    const element = document.getElementById(
      'item-check-form',
    )

    element?.scrollIntoView({
      behavior: 'smooth',
      block: 'start',
    })
  })
}

const findByBarcode = () => {
  const barcode = barcodeInput.value.trim()

  if (!barcode) {
    return
  }

  const item = items.value.find(
    (entry) =>
      entry.book_copy?.barcode?.toLowerCase() ===
      barcode.toLowerCase(),
  )

  if (!item) {
    errorMessage.value =
      'Barcode tidak ditemukan dalam snapshot Stock Opname ini.'
    successMessage.value = ''
    return
  }

  errorMessage.value = ''
  selectItem(item)
  barcodeInput.value = ''
}

const changeResult = (result: StockOpnameResult) => {
  selectedResult.value = result

  if (result === 'FOUND') {
    if (selectedItem.value) {
      actualStatus.value =
        selectedItem.value.expected_status

      actualCondition.value =
        selectedItem.value.expected_condition
    }
  }

  if (result === 'MISSING') {
    actualStatus.value = null
    actualCondition.value = null
  }

  if (result === 'DIFFERENT') {
    if (!actualStatus.value && selectedItem.value) {
      actualStatus.value =
        selectedItem.value.expected_status
    }

    if (!actualCondition.value && selectedItem.value) {
      actualCondition.value =
        selectedItem.value.expected_condition
    }
  }
}

const validateDifferent = () => {
  if (!selectedItem.value) {
    return 'Pilih item terlebih dahulu.'
  }

  const statusDifferent =
    actualStatus.value !==
    selectedItem.value.expected_status

  const conditionDifferent =
    actualCondition.value !==
    selectedItem.value.expected_condition

  if (!statusDifferent && !conditionDifferent) {
    return 'Untuk hasil BERBEDA, status atau kondisi aktual harus berbeda dari data sistem.'
  }

  return ''
}

const saveCheck = async () => {
  if (!opname.value || !selectedItem.value) {
    errorMessage.value = 'Pilih item yang akan diperiksa.'
    return
  }

  if (selectedResult.value === 'FOUND') {
    actualStatus.value =
      selectedItem.value.expected_status

    actualCondition.value =
      selectedItem.value.expected_condition
  }

  if (selectedResult.value === 'MISSING') {
    actualStatus.value = null
    actualCondition.value = null
  }

  if (selectedResult.value === 'DIFFERENT') {
    const validationMessage = validateDifferent()

    if (validationMessage) {
      errorMessage.value = validationMessage
      return
    }
  }

  actionLoading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const response = await checkItem(
      opname.value.id,
      selectedItem.value.id,
      {
        result: selectedResult.value,
        actual_status: actualStatus.value,
        actual_condition: actualCondition.value,
        notes: itemNotes.value || null,
      },
    )

    const updatedItem = response.data.item

    const index = items.value.findIndex(
      (item) => item.id === updatedItem.id,
    )

    if (
      index !== -1 &&
      opname.value?.items
    ) {
      opname.value.items[index] = updatedItem
    }

    successMessage.value =
      'Pemeriksaan item berhasil disimpan.'

    const nextUnchecked = items.value.find(
      (item) => item.result === 'NOT_CHECKED',
    )

    if (nextUnchecked) {
      selectedItemId.value = null
    } else {
      selectedItemId.value = null
    }

    await focusBarcodeInput()
  } catch (error: unknown) {
    const payload: unknown =
      error instanceof FetchError ? error.data : undefined

    errorMessage.value =
      (isApiError(payload) ? payload.message : undefined) ||
      (error instanceof Error ? error.message : undefined) ||
      'Gagal menyimpan hasil pemeriksaan.'
  } finally {
    actionLoading.value = false
  }
}

const complete = async () => {
  if (!opname.value) return

  if (uncheckedItems.value > 0) {
    errorMessage.value =
      `Masih ada ${uncheckedItems.value} item yang belum diperiksa.`
    return
  }

  const confirmed = window.confirm(
    'Selesaikan Stock Opname ini? Setelah selesai, pemeriksaan tidak dapat dilanjutkan.',
  )

  if (!confirmed) return

  actionLoading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const response = await completeStockOpname(
      opname.value.id,
    )

    opname.value = response.data

    selectedItemId.value = null

    successMessage.value =
      'Stock Opname berhasil diselesaikan.'
  } catch (error: unknown) {
    const payload: unknown =
      error instanceof FetchError ? error.data : undefined

    errorMessage.value =
      (isApiError(payload) ? payload.message : undefined) ||
      (error instanceof Error ? error.message : undefined) ||
      'Gagal menyelesaikan Stock Opname.'
  } finally {
    actionLoading.value = false
  }
}

const cancel = async () => {
  if (!opname.value) return

  const confirmed = window.confirm(
    'Batalkan Stock Opname ini?',
  )

  if (!confirmed) return

  actionLoading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const response = await cancelStockOpname(
      opname.value.id,
    )

    opname.value = response.data
    selectedItemId.value = null

    successMessage.value =
      'Stock Opname berhasil dibatalkan.'
  } catch (error: unknown) {
    const payload: unknown =
      error instanceof FetchError ? error.data : undefined

    errorMessage.value =
      (isApiError(payload) ? payload.message : undefined) ||
      (error instanceof Error ? error.message : undefined) ||
      'Gagal membatalkan Stock Opname.'
  } finally {
    actionLoading.value = false
  }
}

const remove = async () => {
  if (!opname.value) return

  const confirmed = window.confirm(
    'Hapus Stock Opname DRAFT ini? Data yang dihapus tidak dapat dikembalikan.',
  )

  if (!confirmed) return

  actionLoading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    await deleteStockOpname(opname.value.id)

    router.push('/staff/stock-opnames')
  } catch (error: unknown) {
    const payload: unknown =
      error instanceof FetchError ? error.data : undefined

    errorMessage.value =
      (isApiError(payload) ? payload.message : undefined) ||
      (error instanceof Error ? error.message : undefined) ||
      'Gagal menghapus Stock Opname.'
  } finally {
    actionLoading.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <StaffShell active="stock-opnames">
        <div class="mb-4 flex justify-start">
            <NuxtLink
                to="/staff/stock-opnames"
                aria-label="Kembali ke daftar Stock Opname"
                class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 hover:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-300"
            >
                ← Kembali
            </NuxtLink>
        </div>
  <div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
      <div>
        <button
          type="button"
          class="mb-3 text-sm font-medium text-slate-500 hover:text-slate-700"
          @click="backToList"
        >
          ← Kembali ke Stock Opname
        </button>

        <div class="flex flex-wrap items-center gap-3">
          <h1 class="text-2xl font-bold text-slate-900">
            {{ opname?.opname_number || 'Detail Stock Opname' }}
          </h1>

          <span
            v-if="opname"
            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
            :class="opnameStatusClass(opname.status)"
          >
            {{ opnameStatusLabel(opname.status) }}
          </span>
        </div>

        <p
          v-if="opname"
          class="mt-1 text-sm text-slate-500"
        >
          Tanggal {{ formatDate(opname.opname_date) }}
          <span v-if="opname.location">
            · {{ opname.location.name }}
          </span>
          <span v-else>
            · Semua Lokasi
          </span>
        </p>
      </div>

      <div class="flex flex-wrap gap-2">
        <button
          v-if="opname?.status === 'DRAFT'"
          type="button"
          class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="actionLoading"
          @click="startOpname"
        >
          {{ actionLoading ? 'Memproses...' : 'Mulai Opname' }}
        </button>

        <div
  v-if="opname?.status === 'IN_PROGRESS'"
  class="flex flex-col items-end gap-1"
>
  <div class="flex flex-wrap items-center justify-end gap-2">
    <button
      type="button"
      class="rounded-lg bg-green-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50"
      :disabled="actionLoading || !canComplete"
      :title="
        canComplete
          ? 'Selesaikan Stock Opname'
          : `${uncheckedItems} item belum diperiksa`
      "
      @click="complete"
    >
      {{ actionLoading ? 'Memproses...' : 'Selesaikan Opname' }}
    </button>

    <button
      type="button"
      class="rounded-lg border border-red-300 px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
      :disabled="actionLoading"
      @click="cancel"
    >
      Batalkan
    </button>
  </div>

  <p
    v-if="!canComplete"
    class="text-xs font-medium text-amber-600"
  >
    Masih ada {{ uncheckedItems }} item belum diperiksa.
  </p>

  <p
    v-else
    class="text-xs font-medium text-emerald-600"
  >
    Semua item sudah diperiksa. Opname siap diselesaikan.
  </p>
</div>

        <button
          v-if="opname?.status === 'DRAFT'"
          type="button"
          class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="actionLoading"
          @click="remove"
        >
          Hapus
        </button>
      </div>
    </div>

    <div
      v-if="loading"
      class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center text-slate-500 shadow-sm"
    >
      Memuat detail Stock Opname...
    </div>

    <template v-else-if="opname">
      <div
        v-if="errorMessage"
        class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
      >
        {{ errorMessage }}
      </div>

      <div
        v-if="successMessage"
        class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"
      >
        {{ successMessage }}
      </div>

      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
            Total
          </p>
          <p class="mt-2 text-2xl font-bold text-slate-900">
            {{ totalItems }}
          </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
            Diperiksa
          </p>
          <p class="mt-2 text-2xl font-bold text-blue-600">
            {{ checkedItems }}
          </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
            Ditemukan
          </p>
          <p class="mt-2 text-2xl font-bold text-green-600">
            {{ foundItems }}
          </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
            Tidak Ditemukan
          </p>
          <p class="mt-2 text-2xl font-bold text-red-600">
            {{ missingItems }}
          </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
            Berbeda
          </p>
          <p class="mt-2 text-2xl font-bold text-amber-600">
            {{ differentItems }}
          </p>
        </div>
      </div>

      <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="text-sm font-semibold text-slate-900">
              Progress Pemeriksaan
            </p>
            <p class="mt-1 text-sm text-slate-500">
              {{ checkedItems }} dari {{ totalItems }} item sudah diperiksa
            </p>
          </div>

          <p class="text-lg font-bold text-slate-900">
            {{ progressPercentage }}%
          </p>
        </div>

        <div class="mt-4 h-3 overflow-hidden rounded-full bg-slate-100">
          <div
            class="h-full rounded-full bg-blue-600 transition-all duration-300"
            :style="{ width: `${progressPercentage}%` }"
          />
        </div>
      </div>

      <div
        v-if="opname.notes"
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
      >
        <p class="text-sm font-semibold text-slate-900">
          Catatan Opname
        </p>
        <p class="mt-2 whitespace-pre-wrap text-sm text-slate-600">
          {{ opname.notes }}
        </p>
      </div>

      <div
        v-if="canEditItems"
        id="item-check-form"
        class="rounded-xl border border-blue-200 bg-blue-50/40 p-5 shadow-sm"
      >
        <div>
          <h2 class="text-lg font-bold text-slate-900">
            Pemeriksaan Item
          </h2>
          <p class="mt-1 text-sm text-slate-500">
            Scan barcode atau pilih item dari daftar di bawah.
          </p>
        </div>

        <div class="mt-5 flex flex-col gap-3 sm:flex-row">
          <input
            ref="barcodeInputElement"
            v-model="barcodeInput"
            type="text"
            autofocus
            placeholder="Scan / ketik barcode lalu tekan Enter..."
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            @keyup.enter="findByBarcode"
          />

          <button
            type="button"
            class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700"
            @click="findByBarcode"
          >
            Cari
          </button>
        </div>
          <div class="mt-5">
            <label
              for="book-search"
              class="mb-2 block text-sm font-semibold text-slate-900"
            >
              Cari Buku Berdasarkan Judul
            </label>

            <input
              id="book-search"
              v-model="bookSearch"
              type="text"
              autocomplete="off"
              placeholder="Ketik judul buku, barcode, atau nomor inventaris..."
              class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            >

            <div
              v-if="bookSearch.trim() && bookSearchResults.length"
              class="mt-2 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
            >
              <button
                v-for="item in bookSearchResults"
                :key="item.id"
                type="button"
                class="flex w-full items-start justify-between gap-4 border-b border-slate-100 px-4 py-3 text-left transition last:border-b-0 hover:bg-slate-50"
                @click="selectBookFromSearch(item)"
              >
                <div class="min-w-0">
                  <p class="truncate text-sm font-semibold text-slate-900">
                    {{ item.book_copy?.book?.title || 'Buku tanpa judul' }}
                  </p>

                  <p class="mt-1 text-xs text-slate-500">
                    Barcode:
                    {{ item.book_copy?.barcode || '-' }}
                    <span class="mx-1">•</span>
                    Inventaris:
                    {{ item.book_copy?.inventory_number || '-' }}
                  </p>
                </div>

                <span
                  class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600"
                >
                  {{ item.result === 'NOT_CHECKED' ? 'Belum Dicek' : item.result }}
                </span>
              </button>
            </div>

            <p
              v-else-if="bookSearch.trim()"
              class="mt-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500"
            >
              Buku tidak ditemukan dalam Stock Opname ini.
            </p>

            <p
              v-else
              class="mt-2 text-xs text-slate-400"
            >
              Pencarian dilakukan pada daftar copy buku yang sudah masuk snapshot Stock Opname.
            </p>
          </div>

        <div
          v-if="selectedItem"
          class="mt-5 rounded-xl border border-slate-200 bg-white p-5"
        >
          <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
              <p class="text-lg font-bold text-slate-900">
                {{
                  selectedItem.book_copy?.book?.title ||
                  'Judul tidak tersedia'
                }}
              </p>

              <div class="mt-2 grid gap-1 text-sm text-slate-600">
                <p>
                  <span class="font-medium">Barcode:</span>
                  {{ selectedItem.book_copy?.barcode || '-' }}
                </p>

                <p>
                  <span class="font-medium">No. Inventaris:</span>
                  {{
                    selectedItem.book_copy?.inventory_number ||
                    '-'
                  }}
                </p>

                <p>
                  <span class="font-medium">Status Sistem:</span>
                  {{ selectedItem.expected_status }}
                </p>

                <p>
                  <span class="font-medium">Kondisi Sistem:</span>
                  {{ selectedItem.expected_condition }}
                </p>
              </div>
            </div>

            <span
              class="inline-flex self-start rounded-full px-2.5 py-1 text-xs font-semibold"
              :class="resultClass(selectedItem.result)"
            >
              {{ resultLabel(selectedItem.result) }}
            </span>
          </div>

          <div class="mt-5">
            <p class="mb-2 text-sm font-semibold text-slate-700">
              Hasil Pemeriksaan
            </p>

            <div class="grid gap-2 sm:grid-cols-3">
              <button
                type="button"
                class="rounded-lg border px-4 py-3 text-sm font-semibold transition"
                :class="
                  selectedResult === 'FOUND'
                    ? 'border-green-500 bg-green-50 text-green-700'
                    : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                "
                @click="changeResult('FOUND')"
              >
                DITEMUKAN
              </button>

              <button
                type="button"
                class="rounded-lg border px-4 py-3 text-sm font-semibold transition"
                :class="
                  selectedResult === 'MISSING'
                    ? 'border-red-500 bg-red-50 text-red-700'
                    : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                "
                @click="changeResult('MISSING')"
              >
                TIDAK DITEMUKAN
              </button>

              <button
                type="button"
                class="rounded-lg border px-4 py-3 text-sm font-semibold transition"
                :class="
                  selectedResult === 'DIFFERENT'
                    ? 'border-amber-500 bg-amber-50 text-amber-700'
                    : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                "
                @click="changeResult('DIFFERENT')"
              >
                BERBEDA
              </button>
            </div>
          </div>

          <div class="mt-5 grid gap-4 md:grid-cols-2">
            <div>
              <label class="mb-1.5 block text-sm font-medium text-slate-700">
                Status Aktual
              </label>

              <select
                v-model="actualStatus"
                :disabled="
                  selectedResult === 'FOUND' ||
                  selectedResult === 'MISSING'
                "
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100"
              >
                <option :value="null">
                  Pilih Status
                </option>

                <option
                  v-for="option in statusOptions"
                  :key="option"
                  :value="option"
                >
                  {{ option }}
                </option>
              </select>
            </div>

            <div>
              <label class="mb-1.5 block text-sm font-medium text-slate-700">
                Kondisi Aktual
              </label>

              <select
                v-model="actualCondition"
                :disabled="
                  selectedResult === 'FOUND' ||
                  selectedResult === 'MISSING'
                "
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100"
              >
                <option :value="null">
                  Pilih Kondisi
                </option>

                <option
                  v-for="option in conditionOptions"
                  :key="option"
                  :value="option"
                >
                  {{ option }}
                </option>
              </select>
            </div>
          </div>

          <div class="mt-4">
            <label class="mb-1.5 block text-sm font-medium text-slate-700">
              Catatan Pemeriksaan
            </label>

            <textarea
              v-model="itemNotes"
              rows="3"
              maxlength="5000"
              placeholder="Catatan bila diperlukan..."
              class="w-full resize-y rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            />
          </div>

          <div class="mt-5 flex justify-end">
            <button
              type="button"
              class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="actionLoading"
              @click="saveCheck"
            >
              {{ actionLoading ? 'Menyimpan...' : 'Simpan Pemeriksaan' }}
            </button>
          </div>
        </div>
      </div>

      <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
          <h2 class="text-lg font-bold text-slate-900">
            Daftar Copy Buku
          </h2>
          <p class="mt-1 text-sm text-slate-500">
            Klik salah satu baris untuk melakukan pemeriksaan.
          </p>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full min-w-[720px] border-collapse text-sm">
            <thead class="border-b border-slate-200 bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left font-semibold text-slate-700">
                  Buku
                </th>
                <th class="px-4 py-3 text-left font-semibold text-slate-700">
                  Barcode / Inventaris
                </th>
                <th class="px-4 py-3 text-left font-semibold text-slate-700">
                  Status Sistem
                </th>
                <th class="px-4 py-3 text-left font-semibold text-slate-700">
                  Kondisi Sistem
                </th>
                <th class="px-4 py-3 text-left font-semibold text-slate-700">
                  Hasil
                </th>
                <th class="px-4 py-3 text-left font-semibold text-slate-700">
                  Diperiksa
                </th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
              <tr v-if="items.length === 0">
                <td
                  colspan="6"
                  class="px-4 py-10 text-center text-slate-500"
                >
                  Belum ada item dalam snapshot.
                </td>
              </tr>

              <tr
                v-for="item in items"
                :key="item.id"
                class="cursor-pointer transition hover:bg-slate-50"
                :class="
                  selectedItemId === item.id
                    ? 'bg-blue-50'
                    : ''
                "
                @click="canEditItems && selectItem(item)"
              >
                <td class="px-4 py-3">
                  <div class="font-medium text-slate-900">
                    {{
                      item.book_copy?.book?.title ||
                      '-'
                    }}
                  </div>
                </td>

                <td class="px-4 py-3 text-slate-600">
                  <p class="font-mono text-xs font-semibold text-slate-800">{{ item.book_copy?.barcode || '-' }}</p>
                  <p v-if="item.book_copy?.inventory_number" class="text-xs text-slate-500">{{ item.book_copy.inventory_number }}</p>
                </td>

                <td class="px-4 py-3 text-slate-600">
                  {{ item.expected_status }}
                </td>

                <td class="px-4 py-3 text-slate-600">
                  {{ item.expected_condition }}
                </td>

                <td class="px-4 py-3">
                  <span
                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                    :class="resultClass(item.result)"
                  >
                    {{ resultLabel(item.result) }}
                  </span>
                </td>

                <td class="px-4 py-3 text-slate-600">
                  {{ formatDateTime(item.checked_at) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <div
      v-else
      class="rounded-xl border border-red-200 bg-red-50 px-6 py-10 text-center text-red-700"
    >
      {{ errorMessage || 'Data Stock Opname tidak ditemukan.' }}
    </div>
  </div>
  </StaffShell>
</template>







