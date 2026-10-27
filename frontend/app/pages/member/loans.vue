<script setup lang="ts">
import { getApiErrorMessage } from '~/composables/useApi'
import type {
    LoanItemRecord,
    LoanRecord,
    MemberProfile,
} from '~/types/member'

definePageMeta({
    middleware: 'auth',
})

const {
    getProfile,
    getLoans,
    renewLoanItem,
} = useMember()

const profile = ref<MemberProfile | null>(null)
const loans = ref<LoanRecord[]>([])
const loading = ref(true)
const refreshing = ref(false)
const errorMessage = ref('')
const actionMessage = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
let loansRequestId = 0

const confirmRenewal = ref<{
    loan: LoanRecord
    item: LoanItemRecord
} | null>(null)

const renewingKey = ref('')

const toRecord = (value: unknown): Record<string, unknown> => {
    return value && typeof value === 'object'
        ? (value as Record<string, unknown>)
        : {}
}

const getBookTitle = (item: LoanItemRecord) => {
    const record = toRecord(item)
    const directBook = toRecord(record.book)
    const copyBook = toRecord(toRecord(record.book_copy).book)
    const camelCopyBook = toRecord(toRecord(record.bookCopy).book)

    return String(
        directBook.title ??
            copyBook.title ??
            camelCopyBook.title ??
            'Buku',
    )
}

const getBarcode = (item: LoanItemRecord) => {
    const record = toRecord(item)
    const copy = toRecord(record.book_copy)
    const camelCopy = toRecord(record.bookCopy)

    return String(
        copy.barcode ??
            camelCopy.barcode ??
            record.barcode ??
            '-',
    )
}

const overdue = (item: LoanItemRecord) => {
    return (
        item.status === 'ACTIVE' &&
        item.is_overdue
    )
}

const money = (value: number | string) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value || 0))

const loanItemStatusLabel = (item: LoanItemRecord) => {
    switch (item.status) {
        case 'ACTIVE':
            return overdue(item) ? 'Terlambat' : 'Sedang Dipinjam'
        case 'RETURNED':
            return 'Dikembalikan'
        case 'LOST':
            return 'Hilang'
        case 'DAMAGED':
            return 'Rusak'
        default:
            return item.status
                ? `Status tidak dikenal (${item.status})`
                : 'Status tidak dikenal'
    }
}

const loanItemStatusClass = (item: LoanItemRecord) => {
    switch (item.status) {
        case 'ACTIVE':
            return overdue(item)
                ? 'bg-rose-50 text-rose-700'
                : 'bg-emerald-50 text-emerald-700'
        case 'RETURNED':
            return 'bg-slate-100 text-slate-600'
        case 'LOST':
        case 'DAMAGED':
            return 'bg-rose-50 text-rose-700'
        default:
            return 'bg-slate-100 text-slate-600'
    }
}

const dateTime = (value: string | null) => {
    if (!value) return '-'

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value))
}

const dateOnly = (value: string | null) => {
    if (!value) return '-'

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
    }).format(new Date(value))
}

const loadLoans = async (
    page = 1,
    showLoading = true,
) => {
    const requestId = ++loansRequestId
    loading.value = showLoading && page === 1 && loans.value.length === 0
    refreshing.value = true
    if (showLoading) {
        errorMessage.value = ''
    }

    try {
        const [profileResult, result] = await Promise.all([
            showLoading ? getProfile() : Promise.resolve(null),
            getLoans(page, 15),
        ])

        if (requestId !== loansRequestId) {
            return
        }

        if (profileResult) {
            profile.value = profileResult
        }
        errorMessage.value = ''
        loans.value = result.items
        currentPage.value = result.meta?.current_page ?? page
        lastPage.value = result.meta?.last_page ?? 1
        total.value = result.meta?.total ?? result.items.length
    } catch (error: unknown) {
        if (requestId !== loansRequestId) {
            return
        }

        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Data peminjaman gagal dimuat.'
    } finally {
        if (requestId === loansRequestId) {
            loading.value = false
            refreshing.value = false
        }
    }
}

const openRenewal = (
    loan: LoanRecord,
    item: LoanItemRecord,
) => {
    confirmRenewal.value = {
        loan,
        item,
    }

    actionMessage.value = ''
}

const executeRenewal = async () => {
    if (!confirmRenewal.value) {
        return
    }

    const { loan, item } = confirmRenewal.value
    const key = `${loan.id}-${item.id}`

    renewingKey.value = key
    actionMessage.value = ''

    try {
        await renewLoanItem(loan.id, item.id)

        actionMessage.value =
            'Perpanjangan berhasil. Tanggal jatuh tempo telah diperbarui.'

        confirmRenewal.value = null

        await loadLoans(currentPage.value)
    } catch (error: unknown) {
        actionMessage.value = getApiErrorMessage(
            error,
            'Perpanjangan gagal. Silakan coba lagi.',
        )
    } finally {
        renewingKey.value = ''
    }
}

const canRenew = (item: LoanItemRecord) => {
    return (
        item.status === 'ACTIVE' &&
        item.renewal_count < 1
    )
}

usePageRefresh(() => loadLoans(currentPage.value, false), 30_000)
onMounted(() => loadLoans())
</script>

<template>
    <MemberShell :profile="profile">
        <div class="space-y-6">
            <section class="flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:flex-row sm:items-end sm:justify-between sm:p-8">
                <div>
                    <p class="text-sm font-medium text-slate-500">
                        Aktivitas Anggota
                    </p>
                    <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">
                        Peminjaman Saya
                    </h1>
                    <p class="mt-2 text-sm text-slate-500">
                        Pantau buku, jatuh tempo, dan gunakan perpanjangan ketika tersedia.
                    </p>
                </div>

                <div class="rounded-xl bg-slate-100 px-4 py-3 text-right">
                    <p class="text-xs text-slate-500">Total transaksi</p>
                    <p class="text-xl font-bold text-slate-900">
                        {{ total }}
                    </p>
                </div>
            </section>

            <div
                v-if="errorMessage"
                class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
            >
                {{ errorMessage }}
            </div>

            <div
                v-if="actionMessage"
                class="rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-700 shadow-sm"
            >
                {{ actionMessage }}
            </div>

            <div
                v-if="loading"
                class="grid gap-4"
            >
                <div
                    v-for="i in 3"
                    :key="i"
                    class="animate-pulse rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"
                >
                    <div class="h-5 w-48 rounded bg-slate-200"/>
                    <div class="mt-3 h-4 w-32 rounded bg-slate-200"/>
                    <div class="mt-6 h-24 rounded-xl bg-slate-100"/>
                </div>
            </div>

            <div
                v-else-if="loans.length === 0"
                class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center"
            >
                <p class="font-semibold text-slate-900">
                    Belum ada transaksi peminjaman
                </p>
                <p class="mt-1 text-sm text-slate-500">
                    Saat Anda meminjam buku, detail transaksi akan muncul di sini.
                </p>
            </div>

            <div v-else class="space-y-4">
                <article
                    v-for="loan in loans"
                    :key="loan.id"
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                >
                    <div class="border-b border-slate-200 px-6 py-5">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-lg font-bold text-slate-900">
                                    {{ loan.loan_number }}
                                </p>
                                <p class="mt-1 text-sm text-slate-500">
                                    Dipinjam {{ dateTime(loan.borrowed_at) }}
                                </p>
                            </div>

                            <span class="inline-flex w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                {{ loan.status }}
                            </span>
                        </div>

                        <div class="mt-5 grid gap-3 sm:grid-cols-4">
                            <div class="rounded-xl bg-slate-50 p-4">
                                <p class="text-xs text-slate-500">Total Item</p>
                                <p class="mt-1 text-xl font-bold text-slate-900">
                                    {{ loan.summary?.total_items ?? 0 }}
                                </p>
                            </div>

                            <div class="rounded-xl bg-slate-50 p-4">
                                <p class="text-xs text-slate-500">Aktif</p>
                                <p class="mt-1 text-xl font-bold text-slate-900">
                                    {{ loan.summary?.active_items ?? 0 }}
                                </p>
                            </div>

                            <div class="rounded-xl bg-slate-50 p-4">
                                <p class="text-xs text-slate-500">Kembali</p>
                                <p class="mt-1 text-xl font-bold text-slate-900">
                                    {{ loan.summary?.returned_items ?? 0 }}
                                </p>
                            </div>

                            <div class="rounded-xl bg-rose-50 p-4">
                                <p class="text-xs text-rose-600">Terlambat</p>
                                <p class="mt-1 text-xl font-bold text-rose-700">
                                    {{ loan.summary?.overdue_items ?? 0 }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="loan.items?.length"
                        class="divide-y divide-slate-100"
                    >
                        <div
                            v-for="item in loan.items"
                            :key="item.id"
                            class="p-6"
                        >
                            <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                                <div class="min-w-0 flex-1">
                                    <p class="text-base font-bold text-slate-900">
                                        {{ getBookTitle(item) }}
                                    </p>

                                    <p class="mt-1 text-xs text-slate-500">
                                        Barcode: {{ getBarcode(item) }}
                                    </p>

                                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                                        <div class="rounded-xl bg-slate-50 p-3">
                                            <p class="text-[11px] uppercase tracking-wide text-slate-500">
                                                Dipinjam
                                            </p>
                                            <p class="mt-1 text-sm font-semibold text-slate-900">
                                                {{ dateOnly(item.borrowed_at) }}
                                            </p>
                                        </div>

                                        <div
                                            class="rounded-xl p-3"
                                            :class="overdue(item)
                                                ? 'bg-rose-50'
                                                : 'bg-slate-50'"
                                        >
                                            <p
                                                class="text-[11px] uppercase tracking-wide"
                                                :class="overdue(item)
                                                    ? 'text-rose-600'
                                                    : 'text-slate-500'"
                                            >
                                                Jatuh Tempo
                                            </p>
                                            <p
                                                class="mt-1 text-sm font-semibold"
                                                :class="overdue(item)
                                                    ? 'text-rose-700'
                                                    : 'text-slate-900'"
                                            >
                                                {{ dateOnly(item.due_at) }}
                                            </p>
                                        </div>

                                        <div class="rounded-xl bg-slate-50 p-3">
                                            <p class="text-[11px] uppercase tracking-wide text-slate-500">
                                                Perpanjangan
                                            </p>
                                            <p class="mt-1 text-sm font-semibold text-slate-900">
                                                {{ item.renewal_count }}/1
                                            </p>
                                        </div>
                                    </div>

                                    <p
                                        v-if="item.returned_at"
                                        class="mt-4 text-xs text-slate-500"
                                    >
                                        Dikembalikan {{ dateTime(item.returned_at) }}
                                    </p>
                                    <div
                                        v-if="item.status === 'RETURNED'"
                                        class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm"
                                    >
                                        <template v-if="item.fine">
                                            <p class="font-semibold text-slate-900">
                                                Denda {{ money(item.fine.amount) }} · {{ item.fine.late_days }} hari terlambat
                                            </p>
                                            <p class="mt-1 text-slate-600">
                                                Dibayar {{ money(item.fine.amount_paid) }} · Status {{ item.fine.status }}
                                            </p>
                                            <NuxtLink
                                                to="/member/fines"
                                                class="mt-2 inline-block font-semibold text-indigo-700 hover:text-indigo-900"
                                            >
                                                Lihat denda dan pembayaran
                                            </NuxtLink>
                                        </template>
                                        <p v-else class="text-slate-600">Tidak ada denda untuk buku ini.</p>
                                    </div>
                                </div>

                                <div class="flex shrink-0 flex-col gap-2 xl:w-44">
                                    <span
                                        class="inline-flex justify-center rounded-full px-3 py-2 text-xs font-semibold"
                                        :class="loanItemStatusClass(item)"
                                    >
                                        {{ loanItemStatusLabel(item) }}
                                    </span>

                                    <button
                                        v-if="canRenew(item)"
                                        type="button"
                                        class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="refreshing || renewingKey === `${loan.id}-${item.id}`"
                                        @click="openRenewal(loan, item)"
                                    >
                                        {{
                                            renewingKey === `${loan.id}-${item.id}`
                                                ? 'Memproses...'
                                                : 'Perpanjang'
                                        }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="p-6 text-sm text-slate-500"
                    >
                        Detail item peminjaman belum tersedia.
                    </div>
                </article>
            </div>

            <div
                v-if="lastPage > 1"
                class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
            >
                <button
                    type="button"
                    class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="currentPage <= 1 || refreshing"
                    @click="loadLoans(currentPage - 1)"
                >
                    Sebelumnya
                </button>

                <span class="text-sm text-slate-500">
                    Halaman {{ currentPage }} / {{ lastPage }}
                </span>

                <button
                    type="button"
                    class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="currentPage >= lastPage || refreshing"
                    @click="loadLoans(currentPage + 1)"
                >
                    Berikutnya
                </button>
            </div>

            <div
                v-if="confirmRenewal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4"
                @click.self="confirmRenewal = null"
            >
                <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                    <h2 class="text-xl font-bold text-slate-900">
                        Konfirmasi Perpanjangan
                    </h2>

                    <p class="mt-2 text-sm leading-6 text-slate-500">
                        Anda akan memperpanjang peminjaman
                        <strong class="text-slate-900">
                            {{ getBookTitle(confirmRenewal.item) }}
                        </strong>.
                        Sistem akan memvalidasi seluruh aturan perpustakaan.
                    </p>

                    <div class="mt-5 rounded-xl bg-slate-50 p-4">
                        <p class="text-xs text-slate-500">
                            Jatuh tempo saat ini
                        </p>
                        <p class="mt-1 font-semibold text-slate-900">
                            {{ dateOnly(confirmRenewal.item.due_at) }}
                        </p>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700"
                            @click="confirmRenewal = null"
                        >
                            Batal
                        </button>

                        <button
                            type="button"
                            class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
                            :disabled="Boolean(renewingKey)"
                            @click="executeRenewal"
                        >
                            Konfirmasi
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </MemberShell>
</template>