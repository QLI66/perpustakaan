<script setup lang="ts">
import { useIncidentsService, type StaffIncident } from '~/services/incidents'

definePageMeta({ middleware: 'staff' })

const incidentsService = useIncidentsService()

const incidents = ref<StaffIncident[]>([])
const loading = ref(true)
const search = ref('')
const typeFilter = ref('')
const statusFilter = ref('')
const currentPage = ref(1)
const perPage = 15
const total = ref(0)
const lastPage = ref(1)
const errorMessage = ref('')

let searchTimer: ReturnType<typeof setTimeout> | null = null
let incidentsRequestId = 0

const money = (v: string | number | null) =>
    v === null ? '-' : new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v))

const formatDate = (d: string | null) => {
    if (!d) return '-'
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const typeClass = (type: string) =>
    type === 'LOST' ? 'bg-rose-100 text-rose-800' : 'bg-orange-100 text-orange-800'

const statusClass = (status: string) =>
    status === 'OPEN' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'

const load = async (showLoading = true) => {
    const requestId = ++incidentsRequestId

    if (showLoading) {
        loading.value = true
        errorMessage.value = ''
    }
    try {
        const result = await incidentsService.getIncidents({
            page: currentPage.value,
            per_page: perPage,
            search: search.value || undefined,
            type: typeFilter.value || undefined,
            status: statusFilter.value || undefined,
        })
        if (requestId !== incidentsRequestId) {
            return
        }

        errorMessage.value = ''
        incidents.value = result.data.items || []
        total.value = result.data.pagination.total
        lastPage.value = result.data.pagination.last_page
    } catch (e: unknown) {
        if (requestId !== incidentsRequestId) {
            return
        }

        errorMessage.value = e instanceof Error ? e.message : 'Gagal memuat data insiden.'
    } finally {
        if (requestId === incidentsRequestId) {
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

usePageRefresh(() => load(false))
onMounted(() => load())
onUnmounted(() => { if (searchTimer) clearTimeout(searchTimer) })
</script>

<template>
    <StaffShell active="incidents">
        <div class="space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <p class="text-sm font-medium text-slate-500">Sirkulasi</p>
                <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">Insiden Buku</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-500">Kelola laporan buku hilang dan buku rusak.</p>
            </section>

            <div v-if="errorMessage" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                {{ errorMessage }}
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Daftar Insiden</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ total }} insiden ditemukan</p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <select
                            v-model="typeFilter"
                            class="rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            @change="onFilter"
                        >
                            <option value="">Semua Jenis</option>
                            <option value="LOST">Hilang</option>
                            <option value="DAMAGED">Rusak</option>
                        </select>

                        <select
                            v-model="statusFilter"
                            class="rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            @change="onFilter"
                        >
                            <option value="">Semua Status</option>
                            <option value="OPEN">Terbuka</option>
                            <option value="RESOLVED">Selesai</option>
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

                <div v-else-if="incidents.length === 0" class="mt-5 rounded-2xl border border-dashed border-slate-300 p-10 text-center">
                    <p class="font-semibold text-slate-900">Tidak ada data insiden</p>
                    <p class="mt-1 text-sm text-slate-500">Coba ubah filter atau kata kunci pencarian.</p>
                </div>

                <div v-else class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[720px] border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-3 py-3">Anggota</th>
                                <th class="px-3 py-3">Buku / Barcode</th>
                                <th class="px-3 py-3">Jenis</th>
                                <th class="px-3 py-3">Status</th>
                                <th class="px-3 py-3 text-right">Tagihan / Bayar</th>
                                <th class="px-3 py-3">Resolusi</th>
                                <th class="px-3 py-3">Tanggal</th>
                                <th class="px-3 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="inc in incidents"
                                :key="inc.id"
                                class="border-b border-slate-100 last:border-0 hover:bg-slate-50"
                            >
                                <td class="px-3 py-4">
                                    <p class="font-semibold text-slate-900">{{ inc.member?.name || '-' }}</p>
                                    <p class="text-xs text-slate-500">{{ inc.member?.member_number || '-' }}</p>
                                </td>
                                <td class="px-3 py-4">
                                    <p class="max-w-[180px] truncate font-medium text-slate-900">{{ inc.book?.title || '-' }}</p>
                                    <p class="text-xs text-slate-500">{{ inc.book_copy?.barcode || '-' }}</p>
                                </td>
                                <td class="px-3 py-4">
                                    <span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', typeClass(inc.type)]">
                                        {{ inc.type === 'LOST' ? 'Hilang' : 'Rusak' }}
                                    </span>
                                </td>
                                <td class="px-3 py-4">
                                    <span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', statusClass(inc.status)]">
                                        {{ inc.status === 'OPEN' ? 'Terbuka' : 'Selesai' }}
                                    </span>
                                </td>
                                <td class="px-3 py-4 text-right">
                                    <p class="font-semibold text-rose-600">{{ money(inc.amount_due) }}</p>
                                    <p class="text-xs text-emerald-700">Dibayar: {{ money(inc.amount_paid) }}</p>
                                </td>
                                <td class="px-3 py-4 text-slate-600 text-xs">
                                    {{ inc.resolution_type === 'PAID_MONEY' ? 'Bayar Ganti Rugi' : inc.resolution_type === 'REPLACED_BOOK' ? 'Ganti Buku' : '-' }}
                                </td>
                                <td class="px-3 py-4 text-slate-600">{{ formatDate(inc.opened_at) }}</td>
                                <td class="px-3 py-4 text-right">
                                    <NuxtLink
                                        :to="`/staff/incidents/${inc.id}`"
                                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                    >
                                        Detail
                                    </NuxtLink>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

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
