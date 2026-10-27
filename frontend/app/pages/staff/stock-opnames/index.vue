<script setup lang="ts">
import { FetchError } from 'ofetch'
import type {
  StockOpname,
  StockOpnameStatus,
} from '~/services/stock-opnames'
import type { ApiError } from '~/types/api'

definePageMeta({
  middleware: 'staff',
})

const { apiFetch } = useApi()
const router = useRouter()
const authStore = useAuthStore()

const loading = ref(false)
const errorMessage = ref('')
const items = ref<StockOpname[]>([])

const search = ref('')
const status = ref<StockOpnameStatus | ''>('')
const dateFrom = ref('')
const dateTo = ref('')

const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
})

const isApiError = (value: unknown): value is Pick<ApiError, 'message'> =>
  typeof value === 'object' &&
  value !== null &&
  'message' in value &&
  typeof value.message === 'string'

const loadData = async (page = 1) => {
  loading.value = true
  errorMessage.value = ''

  try {
    const response = await apiFetch<{
      items: StockOpname[]
      pagination: {
        current_page: number
        last_page: number
        per_page: number
        total: number
      }
    }>('/stock-opnames', {
      method: 'GET',
      headers: {
        Authorization: authStore.token
          ? `Bearer ${authStore.token}`
          : '',
        Accept: 'application/json',
      },
      query: {
        page,
        per_page: pagination.value.per_page,
        search: search.value || undefined,
        status: status.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        sort: 'opname_date',
        direction: 'desc',
      },
    })

    items.value = response.data.items
    pagination.value = response.data.pagination
  } catch (error: unknown) {
    const payload: unknown =
      error instanceof FetchError ? error.data : undefined

    errorMessage.value =
      (isApiError(payload) ? payload.message : undefined) ||
      (error instanceof Error ? error.message : undefined) ||
      'Gagal memuat data Stock Opname.'
  } finally {
    loading.value = false
  }
}

const resetFilter = () => {
  search.value = ''
  status.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  loadData(1)
}

const openDetail = (id: number) => {
  router.push(`/staff/stock-opnames/${id}`)
}

const createOpname = () => {
  router.push('/staff/stock-opnames/create')
}

const statusLabel = (value: StockOpnameStatus) => {
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

const statusClass = (value: StockOpnameStatus) => {
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

const paginationLabel = computed(() => {
  if (pagination.value.total === 0) {
    return 'Tidak ada data'
  }

  const start =
    (pagination.value.current_page - 1) * pagination.value.per_page + 1

  const end = Math.min(
    pagination.value.current_page * pagination.value.per_page,
    pagination.value.total,
  )

  return `Menampilkan ${start}–${end} dari ${pagination.value.total} data`
})

onMounted(() => {
  loadData(1)
})
</script>

<template>
  <StaffShell active="stock-opnames">
  <div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        
        <div class="mb-4">
            <NuxtLink
                to="/staff"
                aria-label="Kembali ke Dashboard"
                class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300"
            >
                ← Kembali ke Dashboard
            </NuxtLink>
        </div>
        <h1 class="text-2xl font-bold text-slate-900">
          Stock Opname
        </h1>
        <p class="mt-1 text-sm text-slate-500">
          Pencatatan dan pemeriksaan fisik seluruh koleksi buku.
        </p>
      </div>

      <button
        type="button"
        class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
        @click="createOpname"
      >
        + Buat Stock Opname
      </button>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <div class="lg:col-span-2">
          <label class="mb-1.5 block text-sm font-medium text-slate-700">
            Cari
          </label>
          <input
            v-model="search"
            type="text"
            placeholder="Nomor opname atau catatan..."
            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            @keyup.enter="loadData(1)"
          />
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-slate-700">
            Status
          </label>
          <select
            v-model="status"
            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            @change="loadData(1)"
          >
            <option value="">Semua Status</option>
            <option value="DRAFT">Draft</option>
            <option value="IN_PROGRESS">Sedang Berjalan</option>
            <option value="COMPLETED">Selesai</option>
            <option value="CANCELLED">Dibatalkan</option>
          </select>
        </div>

        <div class="flex items-end">
          <button
            type="button"
            class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            @click="loadData(1)"
          >
            Cari
          </button>
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-slate-700">
            Dari Tanggal
          </label>
          <input
            v-model="dateFrom"
            type="date"
            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
          />
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-slate-700">
            Sampai Tanggal
          </label>
          <input
            v-model="dateTo"
            type="date"
            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
          />
        </div>

        <div class="flex items-end md:col-span-2 lg:col-span-2">
          <button
            type="button"
            class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            @click="resetFilter"
          >
            Reset Filter
          </button>
        </div>
      </div>
    </div>

    <div
      v-if="errorMessage"
      class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      {{ errorMessage }}
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[680px] border-collapse text-sm">
          <thead class="border-b border-slate-200 bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-slate-700">
                Nomor Opname
              </th>
              <th class="px-4 py-3 text-left font-semibold text-slate-700">
                Tanggal
              </th>
              <th class="px-4 py-3 text-left font-semibold text-slate-700">
                Lokasi
              </th>
              <th class="px-4 py-3 text-left font-semibold text-slate-700">
                Status
              </th>
              <th class="px-4 py-3 text-left font-semibold text-slate-700">
                Dibuat Oleh
              </th>
              <th class="px-4 py-3 text-right font-semibold text-slate-700">
                Aksi
              </th>
            </tr>
          </thead>

          <tbody class="divide-y divide-slate-100">
            <tr v-if="loading">
              <td
                colspan="6"
                class="px-4 py-10 text-center text-slate-500"
              >
                Memuat data...
              </td>
            </tr>

            <tr v-else-if="items.length === 0">
              <td
                colspan="6"
                class="px-4 py-10 text-center text-slate-500"
              >
                Belum ada data Stock Opname.
              </td>
            </tr>

            <tr
              v-for="item in items"
              :key="item.id"
              class="transition hover:bg-slate-50"
            >
              <td class="px-4 py-3">
                <button
                  type="button"
                  class="font-semibold text-blue-600 hover:text-blue-800 hover:underline"
                  @click="openDetail(item.id)"
                >
                  {{ item.opname_number }}
                </button>
              </td>

              <td class="px-4 py-3 text-slate-600">
                {{ formatDate(item.opname_date) }}
              </td>

              <td class="px-4 py-3 text-slate-600">
                {{ item.location?.name || 'Semua Lokasi' }}
              </td>

              <td class="px-4 py-3">
                <span
                  class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                  :class="statusClass(item.status)"
                >
                  {{ statusLabel(item.status) }}
                </span>
              </td>

              <td class="px-4 py-3 text-slate-600">
                {{ item.creator?.name || '-' }}
              </td>

              <td class="px-4 py-3 text-right">
                <button
                  type="button"
                  class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                  @click="openDetail(item.id)"
                >
                  Detail
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-slate-500">
          {{ paginationLabel }}
        </p>

        <div class="flex items-center gap-2">
          <button
            type="button"
            class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="loading || pagination.current_page <= 1"
            @click="loadData(pagination.current_page - 1)"
          >
            Sebelumnya
          </button>

          <span class="min-w-20 text-center text-sm text-slate-600">
            {{ pagination.current_page }} / {{ pagination.last_page }}
          </span>

          <button
            type="button"
            class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="
              loading ||
              pagination.current_page >= pagination.last_page
            "
            @click="loadData(pagination.current_page + 1)"
          >
            Berikutnya
          </button>
        </div>
      </div>
    </div>
  </div>
  </StaffShell>
</template>

