<script setup lang="ts">
import type {
    FineRecord,
    LoanItemRecord,
    LoanRecord,
    MemberProfile,
    ReservationRecord,
} from '~/types/member'

definePageMeta({
    middleware: 'auth',
})

const {
    getProfile,
    getLoans,
    getFines,
    getReservations,
} = useMember()

const profile = ref<MemberProfile | null>(null)
const loans = ref<LoanRecord[]>([])
const fines = ref<FineRecord[]>([])
const reservations = ref<ReservationRecord[]>([])
const loading = ref(true)
const errorMessage = ref('')
let dashboardRequestId = 0

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

const isOverdue = (item: LoanItemRecord) => {
    return item.status === 'ACTIVE' && item.is_overdue
}

const activeItems = computed<LoanItemRecord[]>(() => {
    return loans.value.flatMap((loan) =>
        (loan.items ?? []).filter((item) => item.status === 'ACTIVE'),
    )
})

const overdueItems = computed(() =>
    activeItems.value.filter(isOverdue),
)

const totalOutstandingFine = computed(() =>
    fines.value.reduce((sum, fine) => {
        const value = Number(fine.outstanding_amount ?? 0)
        return sum + (Number.isFinite(value) ? value : 0)
    }, 0),
)

const nextDueItem = computed(() => {
    return [...activeItems.value]
        .filter((item) => item.due_at)
        .sort(
            (a, b) =>
                new Date(a.due_at as string).getTime() -
                new Date(b.due_at as string).getTime(),
        )[0] ?? null
})

const money = (value: number) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value)

const formatDate = (value: string | null) => {
    if (!value) return '-'

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
    }).format(new Date(value))
}

const formatDue = (item: LoanItemRecord) => {
    if (!item.due_at) return '-'
    if (isOverdue(item)) {
        return item.overdue_days > 0
            ? `Terlambat ${item.overdue_days} hari`
            : 'Terlambat'
    }

    return `Jatuh tempo ${formatDate(item.due_at)}`
}

const loadDashboard = async (showLoading = true) => {
    const requestId = ++dashboardRequestId

    if (showLoading) {
        loading.value = true
        errorMessage.value = ''
    }

    try {
        const [
            profileResult,
            loansResult,
            finesResult,
            reservationsResult,
        ] = await Promise.all([
            getProfile(),
            getLoans(1, 50),
            getFines(1, 50),
            getReservations(1, 50),
        ])

        if (requestId !== dashboardRequestId) {
            return
        }

        errorMessage.value = ''
        profile.value = profileResult
        loans.value = loansResult.items
        fines.value = finesResult.items
        reservations.value = reservationsResult.items
    } catch (error: unknown) {
        if (requestId !== dashboardRequestId) {
            return
        }

        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Dashboard gagal dimuat.'
    } finally {
        if (requestId === dashboardRequestId) {
            loading.value = false
        }
    }
}

usePageRefresh(() => loadDashboard(false), 60_000)
onMounted(() => loadDashboard())
</script>

<template>
    <MemberShell :profile="profile">
        <div class="space-y-8">
            <section class="overflow-hidden rounded-3xl bg-slate-900 p-6 text-white shadow-sm sm:p-8">
                <div class="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-300">
                            Portal Anggota
                        </p>

                        <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                            Halo, {{ profile?.name || 'Member' }}
                        </h1>

                        <p class="mt-3 max-w-xl text-sm leading-6 text-slate-300">
                            Pantau buku yang sedang Anda pinjam, tanggal jatuh tempo,
                            reservasi, dan denda dari satu tempat.
                        </p>

                        <div class="mt-6 flex flex-wrap gap-3">
                            <NuxtLink
                                to="/catalog"
                                class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 transition hover:bg-slate-100"
                            >
                                Jelajahi Katalog
                            </NuxtLink>

                            <NuxtLink
                                to="/member/loans"
                                class="rounded-xl border border-slate-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
                            >
                                Lihat Peminjaman
                            </NuxtLink>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-700 bg-slate-800/80 p-5 lg:min-w-[270px]">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
                            Status Anggota
                        </p>

                        <p class="mt-2 text-xl font-bold">
                            {{ profile?.status || '-' }}
                        </p>

                        <p class="mt-1 text-sm text-slate-300">
                            {{ profile?.member_number || '-' }}
                        </p>

                        <div class="mt-4 h-px bg-slate-700"/>

                        <p class="mt-4 text-sm text-slate-300">
                            {{ profile?.class_level?.name || 'Kelas belum diatur' }}
                            <span v-if="profile?.major">
                                · {{ profile.major.name }}
                            </span>
                        </p>
                    </div>
                </div>
            </section>

            <div
                v-if="errorMessage"
                class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
            >
                {{ errorMessage }}
            </div>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">
                        Sedang Dipinjam
                    </p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">
                        {{ loading ? '—' : activeItems.length }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        dari maksimal 2 buku
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">
                        Terlambat
                    </p>
                    <p class="mt-2 text-3xl font-bold text-rose-600">
                        {{ loading ? '—' : overdueItems.length }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        perlu segera ditindaklanjuti
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">
                        Denda Belum Lunas
                    </p>
                    <p class="mt-2 text-2xl font-bold text-amber-600">
                        {{ loading ? '—' : money(totalOutstandingFine) }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        total saldo berjalan
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">
                        Reservasi
                    </p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">
                        {{ loading ? '—' : reservations.length }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        reservasi yang tercatat
                    </p>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-[1.5fr_1fr]">
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-bold text-slate-900">
                                    Buku yang Sedang Dipinjam
                                </h2>
                                <p class="mt-1 text-sm text-slate-500">
                                    Pantau semua eksemplar aktif dan jatuh temponya.
                                </p>
                            </div>

                            <NuxtLink
                                to="/member/loans"
                                class="text-sm font-semibold text-slate-700 hover:text-slate-950"
                            >
                                Lihat semua
                            </NuxtLink>
                        </div>
                    </div>

                    <div v-if="loading" class="space-y-3 p-6">
                        <div
                            v-for="item in 3"
                            :key="item"
                            class="animate-pulse rounded-2xl bg-slate-100 p-5"
                        >
                            <div class="h-4 w-1/2 rounded bg-slate-200"/>
                            <div class="mt-3 h-3 w-1/3 rounded bg-slate-200"/>
                            <div class="mt-5 h-9 w-full rounded bg-slate-200"/>
                        </div>
                    </div>

                    <div
                        v-else-if="activeItems.length === 0"
                        class="p-8 text-center"
                    >
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100">
                            <svg
                                class="h-7 w-7 text-slate-500"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.7"
                            >
                                <path d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5" />
                            </svg>
                        </div>

                        <p class="mt-4 font-semibold text-slate-900">
                            Belum ada buku yang sedang dipinjam
                        </p>

                        <p class="mt-1 text-sm text-slate-500">
                            Jelajahi katalog untuk menemukan buku yang tersedia.
                        </p>
                    </div>

                    <div v-else class="divide-y divide-slate-100">
                        <div
                            v-for="item in activeItems.slice(0, 5)"
                            :key="item.id"
                            class="p-6"
                        >
                            <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <p class="truncate text-base font-bold text-slate-900">
                                        {{ getBookTitle(item) }}
                                    </p>

                                    <p class="mt-1 text-xs text-slate-500">
                                        Barcode: {{ getBarcode(item) }}
                                    </p>
                                </div>

                                <span
                                    class="inline-flex w-fit shrink-0 rounded-full px-3 py-1 text-xs font-semibold"
                                    :class="isOverdue(item)
                                        ? 'bg-rose-50 text-rose-700'
                                        : 'bg-emerald-50 text-emerald-700'"
                                >
                                    {{ isOverdue(item) ? 'Terlambat' : 'Aktif' }}
                                </span>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-xl bg-slate-50 p-3">
                                    <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">
                                        Dipinjam
                                    </p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">
                                        {{ formatDate(item.borrowed_at) }}
                                    </p>
                                </div>

                                <div
                                    class="rounded-xl p-3"
                                    :class="isOverdue(item)
                                        ? 'bg-rose-50'
                                        : 'bg-slate-50'"
                                >
                                    <p
                                        class="text-[11px] font-medium uppercase tracking-wide"
                                        :class="isOverdue(item)
                                            ? 'text-rose-600'
                                            : 'text-slate-500'"
                                    >
                                        Jatuh Tempo
                                    </p>
                                    <p
                                        class="mt-1 text-sm font-semibold"
                                        :class="isOverdue(item)
                                            ? 'text-rose-700'
                                            : 'text-slate-900'"
                                    >
                                        {{ formatDue(item) }}
                                    </p>
                                </div>

                                <div class="rounded-xl bg-slate-50 p-3">
                                    <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">
                                        Perpanjangan
                                    </p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">
                                        {{ item.renewal_count }}/1
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">
                            Jatuh Tempo Terdekat
                        </p>

                        <template v-if="nextDueItem">
                            <p class="mt-3 text-lg font-bold text-slate-900">
                                {{ getBookTitle(nextDueItem) }}
                            </p>

                            <p class="mt-2 text-sm text-slate-500">
                                {{ formatDate(nextDueItem.due_at) }}
                            </p>

                            <div
                                class="mt-4 rounded-xl px-4 py-3 text-sm font-semibold"
                                :class="isOverdue(nextDueItem)
                                    ? 'bg-rose-50 text-rose-700'
                                    : 'bg-slate-100 text-slate-700'"
                            >
                                {{ formatDue(nextDueItem) }}
                            </div>

                            <NuxtLink
                                to="/member/loans"
                                class="mt-4 inline-flex text-sm font-semibold text-slate-900 hover:underline"
                            >
                                Kelola peminjaman
                            </NuxtLink>
                        </template>

                        <p
                            v-else
                            class="mt-3 text-sm text-slate-500"
                        >
                            Belum ada buku aktif.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-slate-500">
                                    Denda Belum Lunas
                                </p>
                                <p class="mt-1 text-2xl font-bold text-slate-900">
                                    {{ money(totalOutstandingFine) }}
                                </p>
                            </div>

                            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                                <svg
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path d="M12 4v16M16 7.5c-.8-.8-2-1.5-4-1.5-2.5 0-4 1.2-4 3s1.5 3 4 3 4 1.2 4 3-1.5 3-4 3c-2 0-3.2-.7-4-1.5" />
                                </svg>
                            </div>
                        </div>

                        <NuxtLink
                            to="/member/fines"
                            class="mt-5 inline-flex text-sm font-semibold text-slate-900 hover:underline"
                        >
                            Lihat rincian denda
                        </NuxtLink>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">
                            Reservasi
                        </p>

                        <p class="mt-2 text-2xl font-bold text-slate-900">
                            {{ reservations.length }}
                        </p>

                        <p class="mt-1 text-sm text-slate-500">
                            reservasi tercatat pada akun Anda.
                        </p>

                        <NuxtLink
                            to="/member/reservations"
                            class="mt-5 inline-flex text-sm font-semibold text-slate-900 hover:underline"
                        >
                            Kelola reservasi
                        </NuxtLink>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">
                            Akses Cepat
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Pilih aktivitas yang ingin Anda lakukan.
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <NuxtLink
                            to="/catalog"
                            class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                        >
                            Cari Buku
                        </NuxtLink>

                        <NuxtLink
                            to="/member/loans"
                            class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                        >
                            Peminjaman
                        </NuxtLink>

                        <NuxtLink
                            to="/member/profile"
                            class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                        >
                            Profil Saya
                        </NuxtLink>
                    </div>
                </div>
            </section>
        </div>
    </MemberShell>
</template>