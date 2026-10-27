<script setup lang="ts">
import { useFinesService, type StaffFine } from '~/services/fines'

definePageMeta({ middleware: 'staff' })

const finesService = useFinesService()
const route = useRoute()
const filteredLoanItemId = computed(() => {
    const queryValue = route.query.loan_item_id
    const loanItemId = Number(Array.isArray(queryValue) ? queryValue[0] : queryValue)

    return Number.isInteger(loanItemId) && loanItemId > 0 ? loanItemId : undefined
})

const fines = ref<StaffFine[]>([])
const loading = ref(true)
const search = ref('')
const statusFilter = ref('')
const currentPage = ref(1)
const perPage = 15
const total = ref(0)
const lastPage = ref(1)
const errorMessage = ref('')

let searchTimer: ReturnType<typeof setTimeout> | null = null
let finesRequestId = 0

const money = (v: string | number | null) =>
    v === null ? '-' : new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v))

const formatDate = (d: string | null) => {
    if (!d) return '-'
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const statusClass = (status: string) => {
    switch (status) {
        case 'PAID': return 'bg-emerald-100 text-emerald-800'
        case 'PARTIAL': return 'bg-amber-100 text-amber-800'
        case 'UNPAID': return 'bg-rose-100 text-rose-800'
        default: return 'bg-slate-100 text-slate-700'
    }
}

const statusLabel = (status: string) => {
    switch (status) {
        case 'PAID': return 'Lunas'
        case 'PARTIAL': return 'Sebagian'
        case 'UNPAID': return 'Belum Dibayar'
        default: return status
    }
}

const load = async (showLoading = true) => {
    const requestId = ++finesRequestId

    if (showLoading) {
        loading.value = true
        errorMessage.value = ''
    }
    try {
        const result = await finesService.getFines({
            page: currentPage.value,
            per_page: perPage,
            search: search.value || undefined,
            status: statusFilter.value || undefined,
            loan_item_id: filteredLoanItemId.value,
        })
        if (requestId !== finesRequestId) {
            return
        }

        errorMessage.value = ''
        fines.value = result.data.items || []
        total.value = result.data.pagination.total
        lastPage.value = result.data.pagination.last_page
    } catch (e: unknown) {
        if (requestId !== finesRequestId) {
            return
        }

        errorMessage.value = e instanceof Error ? e.message : 'Gagal memuat data denda.'
    } finally {
        if (requestId === finesRequestId) {
            loading.value = false
        }
    }
}

const onSearch = () => {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = setTimeout(() => { currentPage.value = 1; load() }, 350)
}

const onFilter = () => { currentPage.value = 1; load() }

const goTo = (page: number) => { currentPage.value = page; load() }

const clearLoanItemFilter = async () => {
    await navigateTo('/staff/fines')
}

watch(filteredLoanItemId, () => {
    currentPage.value = 1
    load()
})

usePageRefresh(() => load(false))
onMounted(() => load())
onUnmounted(() => { if (searchTimer) clearTimeout(searchTimer) })
</script>

<template>
    <StaffShell active="fines">
        <div class="space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <p class="text-sm font-medium text-slate-500">Keuangan</p>
                <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">Denda</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-500">Kelola denda keterlambatan pengembalian buku.</p>
            </section>

            <div
                v-if="filteredLoanItemId"
                class="flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between"
            >
                <p>
                    Filter item pinjaman #{{ filteredLoanItemId }} aktif. Daftar hanya menampilkan denda untuk item ini.
                </p>
                <button
                    type="button"
                    class="shrink-0 font-semibold underline underline-offset-2"
                    @click="clearLoanItemFilter"
                >
                    Tampilkan semua denda
                </button>
            </div>

            <div v-if="errorMessage" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                {{ errorMessage }}
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Daftar Denda</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ total }} denda ditemukan</p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <select
                            v-model="statusFilter"
                            class="rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            @change="onFilter"
                        >
                            <option value="">Semua Status</option>
                            <option value="UNPAID">Belum Dibayar</option>
                            <option value="PARTIAL">Sebagian</option>
                            <option value="PAID">Lunas</option>
                        </select>

                        <input
                            v-model="search"
                            type="search"
                            placeholder="Cari anggota, buku, barcode..."
                            class="rounded-xl border border-slate-300 px-4 py-2 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            @input="onSearch"
                        >
                    </div>
                </div>

                <div v-if="loading" class="mt-4 space-y-3">
                    <div v-for="i in 5" :key="i" class="h-16 animate-pulse rounded-xl bg-slate-100" />
                </div>

                <div v-else-if="fines.length === 0" class="mt-5 rounded-2xl border border-dashed border-slate-300 p-10 text-center">
                    <p class="font-semibold text-slate-900">Tidak ada data denda</p>
                    <p class="mt-1 text-sm text-slate-500">Coba ubah filter atau kata kunci pencarian.</p>
                </div>

                <div v-else class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[720px] border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-3 py-3">Anggota</th>
                                <th class="px-3 py-3">Buku</th>
                                <th class="px-3 py-3 text-right">Denda / Sisa</th>
                                <th class="px-3 py-3">Keterlambatan</th>
                                <th class="px-3 py-3">Status</th>
                                <th class="px-3 py-3">Tanggal</th>
                                <th class="px-3 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="fine in fines"
                                :key="fine.id"
                                class="border-b border-slate-100 last:border-0 hover:bg-slate-50"
                            >
                                <td class="px-3 py-4">
                                    <p class="font-semibold text-slate-900">{{ fine.member?.name || '-' }}</p>
                                    <p class="text-xs text-slate-500">{{ fine.member?.member_number || '-' }}</p>
                                </td>
                                <td class="px-3 py-4">
                                    <p class="max-w-[180px] truncate font-medium text-slate-900">{{ fine.book?.title || '-' }}</p>
                                    <p class="text-xs text-slate-500">{{ fine.loan?.loan_number || '-' }}</p>
                                </td>
                                <td class="px-3 py-4 text-right">
                                    <p class="font-semibold text-slate-900">{{ money(fine.amount) }}</p>
                                    <p class="text-xs font-semibold text-rose-600">Sisa: {{ money(fine.outstanding_amount) }}</p>
                                    <p v-if="Number(fine.amount_paid) > 0" class="text-xs text-emerald-700">Bayar: {{ money(fine.amount_paid) }}</p>
                                </td>
                                <td class="px-3 py-4 text-slate-600">
                                    {{ fine.late_units ?? fine.late_days }} {{ fine.late_unit === 'hour' ? 'jam' : 'hari' }}
                                    <span v-if="fine.is_accruing" class="block text-xs font-semibold text-amber-700">
                                        Berjalan
                                    </span>
                                </td>
                                <td class="px-3 py-4">
                                    <span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', statusClass(fine.status)]">
                                        {{ statusLabel(fine.status) }}
                                    </span>
                                </td>
                                <td class="px-3 py-4 text-slate-600">{{ formatDate(fine.assessed_at) }}</td>
                                <td class="px-3 py-4 text-right">
                                    <NuxtLink
                                        :to="fine.is_accruing
                                            ? `/staff/loans/${fine.loan?.id}`
                                            : `/staff/fines/${fine.id}`"
                                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                    >
                                        {{ fine.is_accruing ? 'Lihat pinjaman' : 'Detail' }}
                                    </NuxtLink>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="lastPage > 1" class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4 text-sm">
                    <p class="text-slate-500">Halaman {{ currentPage }} dari {{ lastPage }}</p>
                    <div class="flex gap-2">
                        <button
                            :disabled="currentPage <= 1"
                            class="rounded-xl border border-slate-300 px-4 py-2 font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-40 hover:bg-slate-50"
                            @click="goTo(currentPage - 1)"
                        >
                            ← Sebelumnya
                        </button>
                        <button
                            :disabled="currentPage >= lastPage"
                            class="rounded-xl border border-slate-300 px-4 py-2 font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-40 hover:bg-slate-50"
                            @click="goTo(currentPage + 1)"
                        >
                            Berikutnya →
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </StaffShell>
</template>
