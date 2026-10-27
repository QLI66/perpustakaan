<script setup lang="ts">
import { FetchError } from 'ofetch'
import type { ApiError } from '~/types/api'
import { useReportsService } from '~/services/reports'
import type { ReportOverview } from '~/services/reports'

definePageMeta({
    middleware: 'staff',
})

const { getOverview, exportReport } = useReportsService()

const overview = ref<ReportOverview | null>(null)
const loading = ref(true)
const errorMessage = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const exportLoading = ref<string | null>(null)

const reportTypes = [
    { id: 'loans', name: 'Peminjaman' },
    { id: 'returns', name: 'Pengembalian' },
    { id: 'fines', name: 'Denda' },
    { id: 'incidents', name: 'Insiden' },
    { id: 'reservations', name: 'Reservasi' },
    { id: 'members', name: 'Anggota' },
    { id: 'catalog', name: 'Katalog' },
    { id: 'acquisitions', name: 'Akuisisi' },
    { id: 'stock_opnames', name: 'Stock Opname' },
]

const load = async () => {
    loading.value = true
    errorMessage.value = ''

    try {
        const params: Record<string, string> = {}
        if (dateFrom.value) params.date_from = dateFrom.value
        if (dateTo.value) params.date_to = dateTo.value

        const result = await getOverview(params)
        overview.value = result.data
    } catch (error: unknown) {
        const payload = error instanceof FetchError
            ? error.data as ApiError | undefined
            : undefined

        errorMessage.value = payload?.message || 'Gagal memuat ringkasan laporan.'
    } finally {
        loading.value = false
    }
}

const downloadReport = async (reportId: string) => {
    exportLoading.value = reportId
    try {
        const params: Record<string, string> = {}
        if (dateFrom.value) params.date_from = dateFrom.value
        if (dateTo.value) params.date_to = dateTo.value

        const blob = await exportReport(reportId, params)
        const url = window.URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = `${reportId}-report.csv`
        document.body.appendChild(a)
        a.click()
        document.body.removeChild(a)
        window.URL.revokeObjectURL(url)
    } catch {
        alert('Gagal mengunduh laporan.')
    } finally {
        exportLoading.value = null
    }
}

onMounted(() => {
    load()
})
</script>

<template>
    <StaffShell active="reports">
        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="sm:flex-auto">
                    <h1 class="text-xl font-bold text-slate-900">Laporan Perpustakaan</h1>
                    <p class="mt-2 text-sm text-slate-500">
                        Lihat ringkasan berbagai laporan dan unduh ringkasan dalam format CSV.
                    </p>
                </div>

                <NuxtLink
                    to="/staff"
                    aria-label="Kembali ke Dashboard"
                    class="inline-flex w-fit items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 hover:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-300"
                >
                    ← Kembali
                </NuxtLink>
            </div>

            <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-end">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Tanggal Mulai</label>
                    <input v-model="dateFrom" type="date" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Tanggal Selesai</label>
                    <input v-model="dateTo" type="date" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                </div>
                <div>
                    <button
                        @click="load"
                        :disabled="loading"
                        class="inline-flex items-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50"
                    >
                        Terapkan Filter
                    </button>
                </div>
            </div>

            <div v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 p-4">
                <p class="text-sm text-red-700">{{ errorMessage }}</p>
            </div>

            <div v-if="loading" class="mt-8 text-center text-slate-500">
                Memuat data laporan...
            </div>

            <div v-else-if="overview" class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="type in reportTypes" :key="type.id" class="overflow-hidden rounded-lg bg-white shadow ring-1 ring-slate-200">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="w-0 flex-1">
                                <dl>
                                    <dt class="truncate text-sm font-medium text-slate-500">{{ type.name }}</dt>
                                    <dd>
                                        <div class="mt-2 text-xs text-slate-600 space-y-1">
                                            <div v-for="(val, key) in overview.reports[type.id as keyof typeof overview.reports]" :key="key" class="flex justify-between">
                                                <span class="capitalize">{{ key.replace(/_/g, ' ') }}</span>
                                                <span class="font-semibold">{{ val }}</span>
                                            </div>
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-slate-50 px-5 py-3">
                        <div class="text-sm">
                            <button
                                @click="downloadReport(type.id)"
                                :disabled="exportLoading === type.id"
                                class="font-medium text-indigo-700 hover:text-indigo-900 flex items-center"
                            >
                                <span v-if="exportLoading === type.id">Mengunduh...</span>
                                <span v-else>Unduh CSV &rarr;</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </StaffShell>
</template>
