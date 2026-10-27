<script setup lang="ts">
import { useFinanceService, type FinanceSummary, type FinanceTransaction } from '~/services/finance'
import * as XLSX from 'xlsx'
import jsPDF from 'jspdf'

definePageMeta({ middleware: 'staff' })

const financeService = useFinanceService()

const summary = ref<FinanceSummary | null>(null)
const transactions = ref<FinanceTransaction[]>([])
const loading = ref(true)
const errorMessage = ref('')

const search = ref('')
const typeFilter = ref('')
const periodFilter = ref('ALL') // ALL, TODAY, MONTH, YEAR, CUSTOM
const dateFrom = ref('')
const dateTo = ref('')

const currentPage = ref(1)
const perPage = 15
const total = ref(0)
const lastPage = ref(1)

let searchTimer: ReturnType<typeof setTimeout> | null = null
let financeRequestId = 0

const money = (v: string | number | null) =>
    v === null ? '-' : new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v))

const formatDateTime = (d: string | null) => {
    if (!d) return '-'
    // Jika string tidak memiliki info timezone (Z atau ±HH:MM), anggap UTC dan tambahkan Z
    // agar browser mengkonversi ke timezone lokal (WIB +7) dengan benar
    const dateStr = /Z$|[+-]\d{2}:?\d{2}$/.test(d) ? d : d.replace(' ', 'T') + 'Z'
    return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta' })
}

const toDateStr = (d: Date): string => {
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${y}-${m}-${day}`
}

const getPeriodDates = () => {
    let from = ''
    let to = ''
    const today = new Date()

    if (periodFilter.value === 'TODAY') {
        from = toDateStr(today)
        to = toDateStr(today)
    } else if (periodFilter.value === 'MONTH') {
        from = toDateStr(new Date(today.getFullYear(), today.getMonth(), 1))
        to = toDateStr(today)
    } else if (periodFilter.value === 'YEAR') {
        from = toDateStr(new Date(today.getFullYear(), 0, 1))
        to = toDateStr(today)
    } else if (periodFilter.value === 'CUSTOM') {
        from = dateFrom.value
        to = dateTo.value
    }

    return { from, to }
}

const loadData = async (showLoading = true) => {
    const requestId = ++financeRequestId

    if (showLoading) {
        loading.value = true
        errorMessage.value = ''
    }
    
    const { from, to } = getPeriodDates()

    const params: Record<string, unknown> = {
        date_from: from || undefined,
        date_to: to || undefined,
    }

    try {
        const summaryRes = await financeService.getSummary(params)
        if (requestId !== financeRequestId) {
            return
        }
        errorMessage.value = ''
        summary.value = summaryRes.data as FinanceSummary

        const transParams = {
            ...params,
            page: currentPage.value,
            per_page: perPage,
            type: typeFilter.value || undefined,
            search: search.value || undefined,
        }
        const transRes = await financeService.getTransactions(transParams)
        if (requestId !== financeRequestId) {
            return
        }
        transactions.value = transRes.data.items || []
        total.value = transRes.data.pagination.total
        lastPage.value = transRes.data.pagination.last_page
    } catch (e: unknown) {
        if (requestId !== financeRequestId) {
            return
        }

        errorMessage.value = e instanceof Error ? e.message : 'Gagal memuat data keuangan.'
    } finally {
        if (requestId === financeRequestId) {
            loading.value = false
        }
    }
}

const onSearch = () => {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = setTimeout(() => { currentPage.value = 1; loadData() }, 350)
}

const onFilter = () => {
    currentPage.value = 1
    if (periodFilter.value !== 'CUSTOM') {
        dateFrom.value = ''
        dateTo.value = ''
    }
    loadData()
}

const goTo = (page: number) => { currentPage.value = page; loadData() }

const exportLoading = ref<'excel' | 'pdf' | null>(null)

const getPeriodLabel = (): string => {
    switch (periodFilter.value) {
        case 'TODAY':
            return dateFrom.value || toDateStr(new Date())
        case 'MONTH':
            return `${new Date().getFullYear()}-${String(new Date().getMonth() + 1).padStart(2, '0')}`
        case 'YEAR':
            return String(new Date().getFullYear())
        case 'CUSTOM':
            return `${dateFrom.value || 'awal'} s/d ${dateTo.value || 'akhir'}`
        default:
            return 'Semua Waktu'
    }
}

const getExportFilePeriod = (): string => {
    switch (periodFilter.value) {
        case 'TODAY':
            return toDateStr(new Date())
        case 'MONTH':
            return `${new Date().getFullYear()}-${String(new Date().getMonth() + 1).padStart(2, '0')}`
        case 'YEAR':
            return String(new Date().getFullYear())
        case 'CUSTOM':
            return `${dateFrom.value || 'awal'}_sd_${dateTo.value || 'akhir'}`
        default:
            return 'Semua_Waktu'
    }
}

const getAllTransactionsForExport = async (): Promise<FinanceTransaction[]> => {
    const { from, to } = getPeriodDates()

    const baseParams: Record<string, unknown> = {
        date_from: from || undefined,
        date_to: to || undefined,
        type: typeFilter.value || undefined,
        search: search.value || undefined,
    }

    const firstResponse = await financeService.getTransactions({
        ...baseParams,
        page: 1,
        per_page: 100,
    })

    const allItems: FinanceTransaction[] = [
        ...(firstResponse.data.items || []),
    ]

    const lastPage = firstResponse.data.pagination.last_page

    if (lastPage > 1) {
        for (let page = 2; page <= lastPage; page++) {
            const response = await financeService.getTransactions({
                ...baseParams,
                page,
                per_page: 100,
            })

            allItems.push(...(response.data.items || []))
        }
    }

    return allItems
}

const normalizeExportRows = (items: FinanceTransaction[]) => {
    return items.map((item, index) => ({
        No: index + 1,
        Tanggal: item.date,
        Jenis: item.type,
        Anggota: item.member_name,
        'Nomor Anggota': item.member_number,
        Buku: item.book_title,
        Referensi: item.reference,
        Nominal: Number(item.amount),
        'Metode Pembayaran': item.payment_method,
        'Referensi Pembayaran': item.payment_reference ?? '-',
        Penerima: item.received_by_name ?? '-',
    }))
}

const exportExcel = async () => {
    exportLoading.value = 'excel'

    try {
        const { from, to } = getPeriodDates()

        const summaryResponse = await financeService.getSummary({
            date_from: from || undefined,
            date_to: to || undefined,
        })

        const exportSummary = summaryResponse.data as FinanceSummary
        const items = await getAllTransactionsForExport()
        const rows = normalizeExportRows(items)

        const summaryRows = [
            ['LAPORAN KEUANGAN PERPUSTAKAAN'],
            ['Periode', getPeriodLabel()],
            [],
            ['Total Denda Dibayar', exportSummary.fines.paid],
            ['Total Ganti Rugi Dibayar', exportSummary.incidents.paid],
            ['TOTAL UANG DITERIMA', exportSummary.total_received],
            ['Total Denda Terutang', exportSummary.fines.outstanding],
            ['Total Ganti Rugi Terutang', exportSummary.incidents.outstanding],
        ]

        const workbook = XLSX.utils.book_new()

        const summarySheet = XLSX.utils.aoa_to_sheet(summaryRows)

        summarySheet['!cols'] = [
            { wch: 30 },
            { wch: 28 },
        ]

        XLSX.utils.book_append_sheet(
            workbook,
            summarySheet,
            'Ringkasan',
        )

        const transactionSheet = XLSX.utils.json_to_sheet(rows)

        transactionSheet['!cols'] = [
            { wch: 6 },
            { wch: 22 },
            { wch: 16 },
            { wch: 24 },
            { wch: 18 },
            { wch: 30 },
            { wch: 28 },
            { wch: 16 },
            { wch: 20 },
            { wch: 24 },
            { wch: 24 },
        ]

        transactionSheet['!freeze'] = {
            xSplit: 0,
            ySplit: 1,
        }

        XLSX.utils.book_append_sheet(
            workbook,
            transactionSheet,
            'Transaksi',
        )

        const filename =
            `Laporan_Keuangan_${getExportFilePeriod()}.xlsx`

        XLSX.writeFile(workbook, filename)

        alert('Laporan Excel berhasil dibuat.')
    } catch (error: unknown) {
        const message =
            error instanceof Error
                ? error.message
                : 'Gagal membuat laporan Excel.'

        alert(message)
    } finally {
        exportLoading.value = null
    }
}

const drawPdfTableHeader = (
    doc: jsPDF,
    y: number,
): number => {
    doc.setFontSize(7)
    doc.setFont('helvetica', 'bold')

    const columns = [
        ['No', 10],
        ['Tanggal', 23],
        ['Jenis', 54],
        ['Anggota', 79],
        ['Buku', 116],
        ['Ref', 168],
        ['Nominal', 213],
        ['Metode', 244],
    ]

    for (const [label, x] of columns) {
        doc.text(String(label), Number(x), y)
    }

    doc.setFont('helvetica', 'normal')

    return y + 5
}

const drawPdfRow = (
    doc: jsPDF,
    row: ReturnType<typeof normalizeExportRows>[number],
    y: number,
): number => {
    doc.setFontSize(6.5)

    const values = [
        String(row.No),
        formatDateTime(row.Tanggal),
        row.Jenis,
        row.Anggota,
        row.Buku,
        row.Referensi,
        money(row.Nominal),
        row['Metode Pembayaran'],
    ]

    const xPositions = [
        10,
        23,
        54,
        79,
        116,
        168,
        213,
        244,
    ]

    const widths = [
        10,
        28,
        22,
        32,
        46,
        40,
        28,
        30,
    ]

    const wrapped = values.map((value, index) =>
        doc.splitTextToSize(
            String(value),
            widths[index] ?? 0,
        ),
    )

    const lineCount = Math.max(
        ...wrapped.map(lines => lines.length),
    )

    for (let index = 0; index < wrapped.length; index++) {
        doc.text(
            wrapped[index],
            xPositions[index] ?? 0,
            y,
            { baseline: 'top' },
        )
    }

    return y + Math.max(9, lineCount * 6)
}

const exportPdf = async () => {
    exportLoading.value = 'pdf'

    try {
        const { from, to } = getPeriodDates()

        const summaryResponse = await financeService.getSummary({
            date_from: from || undefined,
            date_to: to || undefined,
        })

        const exportSummary = summaryResponse.data as FinanceSummary
        const items = await getAllTransactionsForExport()
        const rows = normalizeExportRows(items)

        const doc = new jsPDF({
            orientation: 'landscape',
            unit: 'mm',
            format: 'a4',
        })

        const totalReceived = exportSummary.total_received

        const finePaid = exportSummary.fines.paid

        const compensationPaid = exportSummary.incidents.paid

        const fineOutstanding = exportSummary.fines.outstanding

        const compensationOutstanding = exportSummary.incidents.outstanding

        const pageWidth = 297
        const pageHeight = 210

        let y = 14

        doc.setFont('helvetica', 'bold')
        doc.setFontSize(14)
        doc.text(
            'PERPUSTAKAAN SEKOLAH',
            pageWidth / 2,
            y,
            { align: 'center' },
        )

        y += 8

        doc.setFontSize(12)
        doc.text(
            'LAPORAN KEUANGAN',
            pageWidth / 2,
            y,
            { align: 'center' },
        )

        y += 7

        doc.setFont('helvetica', 'normal')
        doc.setFontSize(8)
        doc.text(
            `Periode: ${getPeriodLabel()}`,
            pageWidth / 2,
            y,
            { align: 'center' },
        )

        y += 10

        doc.setFontSize(8)
        doc.text(
            `Denda Dibayar: ${money(finePaid)}`,
            15,
            y,
        )

        doc.text(
            `Ganti Rugi Dibayar: ${money(compensationPaid)}`,
            90,
            y,
        )

        doc.text(
            `TOTAL UANG DITERIMA: ${money(totalReceived)}`,
            190,
            y,
        )

        y += 6

        doc.text(
            `Denda Terutang: ${money(fineOutstanding)}`,
            15,
            y,
        )

        doc.text(
            `Ganti Rugi Terutang: ${money(compensationOutstanding)}`,
            90,
            y,
        )

        y += 10

        doc.setFont('helvetica', 'bold')
        doc.setFontSize(9)
        doc.text(
            `RINCIAN TRANSAKSI (${rows.length} transaksi)`,
            15,
            y,
        )

        y += 6

        y = drawPdfTableHeader(doc, y)

        for (const row of rows) {
            if (y > pageHeight - 15) {
                doc.addPage()

                y = 15
                y = drawPdfTableHeader(doc, y)
            }

            y = drawPdfRow(doc, row, y)
        }

        const pageCount = doc.getNumberOfPages()

        for (let page = 1; page <= pageCount; page++) {
            doc.setPage(page)
            doc.setFontSize(7)
            doc.setFont('helvetica', 'normal')
            doc.text(
                `Halaman ${page} / ${pageCount}`,
                pageWidth - 15,
                pageHeight - 7,
                { align: 'right' },
            )
        }

        const filename =
            `Laporan_Keuangan_${getExportFilePeriod()}.pdf`

        doc.save(filename)

        alert('Laporan PDF berhasil dibuat.')
    } catch (error: unknown) {
        const message =
            error instanceof Error
                ? error.message
                : 'Gagal membuat laporan PDF.'

        alert(message)
    } finally {
        exportLoading.value = null
    }
}

usePageRefresh(() => loadData(false))
onMounted(() => loadData())
onUnmounted(() => { if (searchTimer) clearTimeout(searchTimer) })
</script>

<template>
    <StaffShell active="finance">
        <div class="space-y-6">
            <!-- Header -->
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <p class="text-sm font-medium text-slate-500">Laporan</p>
                <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">Keuangan</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-500">
                    Rekapitulasi pemasukan riil dari denda keterlambatan dan pembayaran ganti rugi buku.
                </p>
            </section>

            <div v-if="errorMessage" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                {{ errorMessage }}
            </div>

            <!-- Filters -->
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-slate-700">Periode Pemasukan</label>
                            <select
                                v-model="periodFilter"
                                class="rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                                @change="onFilter"
                            >
                                <option value="ALL">Semua Waktu</option>
                                <option value="TODAY">Hari Ini</option>
                                <option value="MONTH">Bulan Ini</option>
                                <option value="YEAR">Tahun Ini</option>
                                <option value="CUSTOM">Custom...</option>
                            </select>

<div class="mt-3 flex flex-wrap gap-2">
    <button
        type="button"
        :disabled="exportLoading !== null"
        class="rounded-xl border border-emerald-600 bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
        @click="exportExcel"
    >
        {{ exportLoading === 'excel' ? 'Mengekspor...' : 'Export Excel' }}
    </button>

    <button
        type="button"
        :disabled="exportLoading !== null"
        class="rounded-xl border border-rose-600 bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50"
        @click="exportPdf"
    >
        {{ exportLoading === 'pdf' ? 'Mengekspor...' : 'Export PDF' }}
    </button>
</div>
                        </div>
                        <template v-if="periodFilter === 'CUSTOM'">
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-700">Mulai</label>
                                <input
                                    v-model="dateFrom"
                                    type="date"
                                    class="rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                                    @change="onFilter"
                                >
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-700">Sampai</label>
                                <input
                                    v-model="dateTo"
                                    type="date"
                                    class="rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                                    @change="onFilter"
                                >
                            </div>
                        </template>
                    </div>
                </div>
            </section>

            <!-- Loading Summary -->
            <div v-if="loading && !summary" class="grid gap-4 sm:grid-cols-3">
                <div v-for="i in 3" :key="i" class="h-32 animate-pulse rounded-2xl bg-slate-100" />
            </div>

            <!-- Summary Cards -->
            <template v-else-if="summary">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 shadow-sm">
                        <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">TOTAL UANG DITERIMA</p>
                        <p class="mt-2 text-4xl font-bold text-slate-900">{{ money(summary.total_received) }}</p>
                        <p class="mt-2 text-xs text-emerald-800">
                            Pemasukan riil berdasarkan tanggal bayar.
                        </p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Denda Dibayar</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900">{{ money(summary.fines.paid) }}</p>
                        <div class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-600 space-y-1">
                            <div class="flex justify-between"><span>Sisa Terutang:</span> <span class="font-semibold text-rose-600">{{ money(summary.fines.outstanding) }}</span></div>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ganti Rugi Dibayar</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900">{{ money(summary.incidents.paid) }}</p>
                        <div class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-600 space-y-1">
                            <div class="flex justify-between"><span>Sisa Terutang:</span> <span class="font-semibold text-rose-600">{{ money(summary.incidents.outstanding) }}</span></div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Transactions Table -->
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Rincian Transaksi</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ total }} transaksi uang masuk</p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <select
                            v-model="typeFilter"
                            class="rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            @change="onFilter"
                        >
                            <option value="">Semua Jenis</option>
                            <option value="DENDA">Denda</option>
                            <option value="GANTI_RUGI">Ganti Rugi Buku</option>
                        </select>

                        <input
                            v-model="search"
                            type="search"
                            placeholder="Cari anggota, buku, referensi..."
                            class="rounded-xl border border-slate-300 px-4 py-2 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            @input="onSearch"
                        >
                    </div>
                </div>

                <div v-if="loading && transactions.length === 0" class="mt-4 space-y-3">
                    <div v-for="i in 5" :key="i" class="h-16 animate-pulse rounded-xl bg-slate-100" />
                </div>

                <div v-else-if="transactions.length === 0" class="mt-5 rounded-2xl border border-dashed border-slate-300 p-10 text-center">
                    <p class="font-semibold text-slate-900">Tidak ada transaksi ditemukan</p>
                    <p class="mt-1 text-sm text-slate-500">Coba ubah filter periode atau kata kunci pencarian.</p>
                </div>

                <div v-else class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[720px] border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-3 py-3">Waktu Masuk</th>
                                <th class="px-3 py-3">Jenis</th>
                                <th class="px-3 py-3">Anggota</th>
                                <th class="px-3 py-3">Referensi Peminjaman</th>
                                <th class="px-3 py-3">Metode Bayar</th>
                                <th class="px-3 py-3 text-right">Nominal</th>
                                <th class="px-3 py-3">Penerima</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="t in transactions"
                                :key="t.id + t.type"
                                class="border-b border-slate-100 last:border-0 hover:bg-slate-50"
                            >
                                <td class="px-3 py-4 text-slate-600">{{ formatDateTime(t.date) }}</td>
                                <td class="px-3 py-4">
                                    <span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', t.type === 'DENDA' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800']">
                                        {{ t.type === 'DENDA' ? 'Denda' : 'Ganti Rugi' }}
                                    </span>
                                </td>
                                <td class="px-3 py-4">
                                    <p class="font-semibold text-slate-900">{{ t.member_name }}</p>
                                    <p class="text-xs text-slate-500">{{ t.member_number }}</p>
                                </td>
                                <td class="px-3 py-4">
                                    <p class="font-semibold text-slate-900">{{ t.reference }}</p>
                                    <p class="max-w-[180px] truncate text-xs text-slate-500">{{ t.book_title }}</p>
                                </td>
                                <td class="px-3 py-4">
                                    <p class="font-semibold text-slate-700">{{ t.payment_method }}</p>
                                    <p v-if="t.payment_reference" class="text-xs text-slate-500">{{ t.payment_reference }}</p>
                                </td>
                                <td class="px-3 py-4 text-right font-bold text-emerald-700">{{ money(t.amount) }}</td>
                                <td class="px-3 py-4 text-slate-600 text-xs">{{ t.received_by_name || '-' }}</td>
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
