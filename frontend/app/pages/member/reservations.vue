<script setup lang="ts">
import { createVisibilityPolling } from '~/utils/visibility-polling'
import type {
    MemberProfile,
    ReservationRecord,
} from '~/types/member'

interface CatalogBookResult {
    id: number
    title: string
    isbn: string | null
    publication_year: number | null
    authors?: Array<{
        id: number
        name: string
    }>
    availability?: {
        total_copies: number
        available_copies: number
        is_available: boolean
    }
}

interface CatalogSearchPayload {
    items: CatalogBookResult[]
    pagination: {
        total: number
        current_page: number
        last_page: number
    }
}

definePageMeta({
    middleware: 'auth',
})

const {
    getProfile,
    getReservations,
    createReservation,
    cancelReservation,
    restoreReservation,
} = useMember()

const { apiFetch } = useApi()

const profile = ref<MemberProfile | null>(null)
const reservations = ref<ReservationRecord[]>([])
const loading = ref(true)
const searchingBooks = ref(false)
const submitting = ref(false)
const cancellingId = ref<number | null>(null)
const restoringId = ref<number | null>(null)
const errorMessage = ref('')
const actionMessage = ref('')

const showCreateModal = ref(false)
const bookSearch = ref('')
const notes = ref('')
const searchResults = ref<CatalogBookResult[]>([])
const bookSearchError = ref('')
let bookSearchRequestId = 0
let reservationRequestId = 0
let reservationRequestInFlight = false
let activeReservationController: AbortController | null = null
let reservationsPageUnmounted = false

const dateTime = (value: string | null) => {
    if (!value) return '-'

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value))
}

const reservationStatusLabel = (reservation: ReservationRecord) => {
    if (
        reservation.status === 'FULFILLED' &&
        reservation.book_returned_at
    ) {
        return 'BUKU TELAH DIKEMBALIKAN'
    }

    return reservation.status
}
const statusClass = (status: string) => {
    switch (status) {
        case 'PENDING':
            return 'bg-amber-50 text-amber-700'
        case 'READY':
            return 'bg-emerald-50 text-emerald-700'
        case 'FULFILLED':
            return 'bg-slate-100 text-slate-700'
        case 'CANCELLED':
            return 'bg-rose-50 text-rose-700'
        case 'EXPIRED':
            return 'bg-slate-100 text-slate-600'
        default:
            return 'bg-slate-100 text-slate-600'
    }
}

const searchBooks = async () => {
    const term = bookSearch.value.trim()
    const requestId = ++bookSearchRequestId
    bookSearchError.value = ''

    if (!term) {
        searchResults.value = []
        searchingBooks.value = false
        return
    }

    searchResults.value = []
    searchingBooks.value = true

    try {
        const response = await apiFetch<CatalogSearchPayload>(
            '/catalog/books',
            {
                method: 'GET',
                query: {
                    search: term,
                    per_page: 8,
                },
            },
        )

        if (requestId !== bookSearchRequestId) {
            return
        }

        searchResults.value = response.data.items
    } catch (error: unknown) {
        if (requestId !== bookSearchRequestId) {
            return
        }

        searchResults.value = []
        bookSearchError.value = error instanceof Error
            ? error.message
            : 'Gagal mencari buku. Silakan coba lagi.'
    } finally {
        if (requestId === bookSearchRequestId) {
            searchingBooks.value = false
        }
    }
}

const invalidateBookSearch = () => {
    bookSearchRequestId++
    searchingBooks.value = false
    searchResults.value = []
    bookSearchError.value = ''
}

const openCreateModal = () => {
    invalidateBookSearch()
    bookSearch.value = ''
    notes.value = ''
    searchResults.value = []
    searchingBooks.value = false
    actionMessage.value = ''
    showCreateModal.value = true
}

const submitReservation = async (book: CatalogBookResult) => {
    submitting.value = true
    errorMessage.value = ''
    actionMessage.value = ''

    try {
        await createReservation(book.id, notes.value)

        actionMessage.value =
            `Reservasi untuk "${book.title}" berhasil dibuat.`

        showCreateModal.value = false

        await loadReservations(false)
    } catch (error: unknown) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Reservasi gagal dibuat.'
    } finally {
        submitting.value = false
    }
}

const canCancel = (reservation: ReservationRecord) => {
    return ['PENDING', 'READY'].includes(reservation.status)
}

const canRestore = (reservation: ReservationRecord) => {
    return reservation.status === 'CANCELLED'
}
const executeCancel = async (
    reservation: ReservationRecord,
) => {
    const confirmed = window.confirm(
        'Batalkan reservasi ini?',
    )

    if (!confirmed) {
        return
    }

    cancellingId.value = reservation.id
    errorMessage.value = ''
    actionMessage.value = ''

    try {
        await cancelReservation(reservation.id)

        actionMessage.value = 'Reservasi berhasil dibatalkan.'

        await loadReservations(false)
    } catch (error: unknown) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Reservasi gagal dibatalkan.'
    } finally {
        cancellingId.value = null
    }
}

const executeRestore = async (
    reservation: ReservationRecord,
) => {
    const confirmed = window.confirm(
        'Pulihkan reservasi ini?',
    )

    if (!confirmed) {
        return
    }

    restoringId.value = reservation.id
    errorMessage.value = ''
    actionMessage.value = ''

    try {
        await restoreReservation(reservation.id)

        actionMessage.value =
            'Reservasi berhasil dikembalikan ke status PENDING.'

        await loadReservations(false)
    } catch (error: unknown) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Reservasi gagal dikembalikan.'
    } finally {
        restoringId.value = null
    }
}
const countdownNow = ref(Date.now())

let countdownTimer: ReturnType<typeof setInterval> | null = null

const getCountdown = (expiresAt: string | null) => {
    if (!expiresAt) {
        return {
            totalMs: 0,
            days: 0,
            hours: 0,
            minutes: 0,
            seconds: 0,
        }
    }

    const difference = Math.max(
        0,
        new Date(expiresAt).getTime() - countdownNow.value,
    )

    const totalSeconds = Math.floor(difference / 1000)

    const days = Math.floor(totalSeconds / 86400)
    const hours = Math.floor((totalSeconds % 86400) / 3600)
    const minutes = Math.floor((totalSeconds % 3600) / 60)
    const seconds = totalSeconds % 60

    return {
        totalMs: difference,
        days,
        hours,
        minutes,
        seconds,
    }
}

const padCountdown = (value: number) => {
    return String(value).padStart(2, '0')
}

const countdownText = (expiresAt: string | null) => {
    const countdown = getCountdown(expiresAt)

    return `${countdown.days} Hari ${padCountdown(countdown.hours)} Jam ${padCountdown(countdown.minutes)} Menit ${padCountdown(countdown.seconds)} Detik`
}

const isCountdownExpired = (expiresAt: string | null) => {
    return getCountdown(expiresAt).totalMs <= 0
}

const isCountdownUrgent = (expiresAt: string | null) => {
    const totalMs = getCountdown(expiresAt).totalMs

    return totalMs > 0 && totalMs <= 24 * 60 * 60 * 1000
}
const loadReservations = async (
    showLoading = true,
    replaceCurrent = true,
    includeProfile = false,
) => {
    if (reservationRequestInFlight && !replaceCurrent) {
        return
    }

    if (reservationRequestInFlight) {
        activeReservationController?.abort()
    }

    const requestId = ++reservationRequestId
    const controller = new AbortController()
    activeReservationController = controller
    reservationRequestInFlight = true

    if (showLoading) {
        loading.value = true
        errorMessage.value = ''
    }

    try {
        const [profileResult, result] = await Promise.all([
            includeProfile ? getProfile() : Promise.resolve(null),
            getReservations(1, 50, controller.signal),
        ])

        if (reservationsPageUnmounted || requestId !== reservationRequestId) {
            return
        }

        if (profileResult) {
            profile.value = profileResult
        }
        errorMessage.value = ''
        reservations.value = result.items
    } catch (error: unknown) {
        if (
            reservationsPageUnmounted ||
            controller.signal.aborted ||
            requestId !== reservationRequestId
        ) {
            return
        }

        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Data reservasi gagal dimuat.'
    } finally {
        if (requestId === reservationRequestId) {
            reservationRequestInFlight = false
            activeReservationController = null
            loading.value = false
        }
    }
}

let reservationPolling: ReturnType<typeof createVisibilityPolling> | null =
    null

onMounted(() => {
    reservationPolling = createVisibilityPolling(
        document,
        () => loadReservations(false, true),
        () => {
            void loadReservations(false, false)
        },
    )
    void reservationPolling.start(
        () => loadReservations(true, true, true),
    )

    countdownTimer = setInterval(() => {
        countdownNow.value = Date.now()
    }, 1000)
})

onUnmounted(() => {
    reservationsPageUnmounted = true
    reservationRequestId++
    activeReservationController?.abort()
    activeReservationController = null
    reservationRequestInFlight = false
    reservationPolling?.dispose()

    if (countdownTimer) {
        clearInterval(countdownTimer)
        countdownTimer = null
    }
})
</script>

<template>
    <MemberShell :profile="profile">
        <div class="space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">
                            Koleksi
                        </p>

                        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">
                            Reservasi Saya
                        </h1>

                        <p class="mt-2 max-w-2xl text-sm text-slate-500">
                            Buat reservasi buku dan pantau statusnya sampai siap diambil.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
                        @click="openCreateModal"
                    >
                        + Buat Reservasi
                    </button>
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
                class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700"
            >
                {{ actionMessage }}
            </div>

            <div
                v-if="loading"
                class="grid gap-4 lg:grid-cols-2"
            >
                <div
                    v-for="i in 4"
                    :key="i"
                    class="animate-pulse rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"
                >
                    <div class="h-5 w-2/3 rounded bg-slate-200"/>
                    <div class="mt-3 h-4 w-1/3 rounded bg-slate-200"/>
                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <div class="h-12 rounded bg-slate-100"/>
                        <div class="h-12 rounded bg-slate-100"/>
                    </div>
                </div>
            </div>

            <div
                v-else-if="reservations.length === 0"
                class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center"
            >
                <p class="font-semibold text-slate-900">
                    Belum ada reservasi
                </p>

                <p class="mt-1 text-sm text-slate-500">
                    Gunakan tombol "Buat Reservasi" untuk mencari buku.
                </p>
            </div>

            <div
                v-else
                class="grid gap-4 lg:grid-cols-2"
            >
                <article
                    v-for="reservation in reservations"
                    :key="reservation.id"
                    class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-lg font-bold text-slate-900">
                                {{ reservation.book?.title || 'Buku' }}
                            </p>

                            <p class="mt-1 text-sm text-slate-500">
                                {{ reservation.reservation_number }}
                            </p>
                        </div>

                        <span
                            class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold"
                            :class="statusClass(reservation.status)"
                        >
                            {{ reservationStatusLabel(reservation) }}
                        </span>
                    </div>

                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-xs text-slate-500">
                                Direservasi
                            </p>

                            <p class="mt-1 text-sm font-semibold text-slate-900">
                                {{ dateTime(reservation.reserved_at) }}
                            </p>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-xs text-slate-500">
                                Siap Diambil
                            </p>

                            <p class="mt-1 text-sm font-semibold text-slate-900">
                                {{ dateTime(reservation.ready_at) }}
                            </p>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-xs text-slate-500">
                                Batas Pengambilan
                            </p>

                            <p class="mt-1 text-sm font-semibold text-slate-900">
                                {{ dateTime(reservation.expires_at) }}
                            </p>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-xs text-slate-500">
                                Copy
                            </p>

                            <p class="mt-1 text-sm font-semibold text-slate-900">
                                {{
                                    reservation.assigned_copy?.barcode ||
                                    'Belum ditetapkan'
                                }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="canCancel(reservation)"
                        class="mt-5 border-t border-slate-100 pt-5"
                    >
                        <button
                            type="button"
                            class="rounded-xl border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="cancellingId === reservation.id"
                            @click="executeCancel(reservation)"
                        >
                            {{
                                cancellingId === reservation.id
                                    ? 'Membatalkan...'
                                    : 'Batalkan Reservasi'
                            }}
                        </button>
                    </div>
                    <div
                        v-if="canRestore(reservation)"
                        class="mt-5 border-t border-slate-100 pt-5"
                    >
                        <button
                            type="button"
                            class="rounded-xl border border-emerald-200 px-4 py-2.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="restoringId === reservation.id"
                            @click="executeRestore(reservation)"
                        >
                            {{
                                restoringId === reservation.id
                                    ? 'Mengembalikan...'
                                    : 'Kembalikan Reservasi'
                            }}
                        </button>
                    </div>

                                        <div

                                            v-if="reservation.status === 'READY' && reservation.expires_at"

                                            class="mt-5 rounded-2xl border p-5"

                                            :class="isCountdownExpired(reservation.expires_at)

                                                ? 'border-rose-200 bg-rose-50'

                                                : isCountdownUrgent(reservation.expires_at)

                                                    ? 'border-amber-200 bg-amber-50'

                                                    : 'border-emerald-200 bg-emerald-50'"

                                        >

                                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

                                                <div>

                                                    <p

                                                        class="text-xs font-semibold uppercase tracking-wide"

                                                        :class="isCountdownExpired(reservation.expires_at)

                                                            ? 'text-rose-700'

                                                            : isCountdownUrgent(reservation.expires_at)

                                                                ? 'text-amber-700'

                                                                : 'text-emerald-700'"

                                                    >

                                                        Batas Pengambilan

                                                    </p>

                    

                                                    <p class="mt-1 text-sm text-slate-600">

                                                        Buku sudah disiapkan dan harus segera diambil.

                                                    </p>

                                                </div>

                    

                                                <div

                                                    class="rounded-xl bg-white px-4 py-3 text-center"

                                                >

                                                    <p

                                                        v-if="!isCountdownExpired(reservation.expires_at)"

                                                        class="text-lg font-bold tabular-nums"

                                                        :class="isCountdownUrgent(reservation.expires_at)

                                                            ? 'text-amber-700'

                                                            : 'text-emerald-700'"

                                                    >

                                                        {{ countdownText(reservation.expires_at) }}

                                                    </p>

                    

                                                    <p

                                                        v-else

                                                        class="text-sm font-bold text-rose-700"

                                                    >

                                                        Waktu pengambilan telah habis

                                                    </p>

                                                </div>

                                            </div>

                    

                                            <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-xs text-slate-500">

                                                <span>

                                                    Siap sejak:

                                                    <strong class="text-slate-700">

                                                        {{ dateTime(reservation.ready_at) }}

                                                    </strong>

                                                </span>

                    

                                                <span>

                                                    Batas:

                                                    <strong class="text-slate-700">

                                                        {{ dateTime(reservation.expires_at) }}

                                                    </strong>

                                                </span>

                                            </div>

                    

                                            <p

                                                v-if="isCountdownUrgent(reservation.expires_at)"

                                                class="mt-3 text-xs font-semibold text-amber-700"

                                            >

                                                Perhatian: waktu pengambilan kurang dari 24 jam.

                                            </p>

                    

                                            <p

                                                v-if="isCountdownExpired(reservation.expires_at)"

                                                class="mt-3 text-xs font-medium text-rose-700"

                                            >

                                                Waktu pengambilan telah mencapai batas.

                                            </p>

                                        </div>

                    
                    <p
                        v-if="reservation.notes"
                        class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600"
                    >
                        {{ reservation.notes }}
                    </p>
                </article>
            </div>

            <div
                v-if="showCreateModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4"
                @click.self="showCreateModal = false"
            >
                <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="text-xl font-bold text-slate-900">
                                    Buat Reservasi
                                </h2>
                                <p class="mt-1 text-sm text-slate-500">
                                    Cari judul buku yang ingin Anda reservasi.
                                </p>
                            </div>

                            <button
                                type="button"
                                class="text-slate-400 hover:text-slate-700"
                                @click="showCreateModal = false"
                            >
                                âœ•
                            </button>
                        </div>
                    </div>

                    <div class="space-y-5 p-6">
                        <div>
                            <label
                                for="book-search"
                                class="mb-2 block text-sm font-semibold text-slate-700"
                            >
                                Cari Buku
                            </label>

                            <div class="flex gap-2">
                                <input
                                    id="book-search"
                                    v-model="bookSearch"
                                    type="text"
                                    placeholder="Masukkan judul atau ISBN..."
                                    class="min-w-0 flex-1 rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                                    @keyup.enter="searchBooks"
                                    @input="invalidateBookSearch"
                                >

                                <button
                                    type="button"
                                    class="rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                                    :disabled="searchingBooks || !bookSearch.trim()"
                                    @click="searchBooks"
                                >
                                    {{ searchingBooks ? 'Mencari...' : 'Cari' }}
                                </button>
                            </div>
                            <p
                                v-if="bookSearchError"
                                class="mt-2 text-sm text-rose-700"
                                role="alert"
                            >
                                {{ bookSearchError }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="reservation-notes"
                                class="mb-2 block text-sm font-semibold text-slate-700"
                            >
                                Catatan
                            </label>

                            <textarea
                                id="reservation-notes"
                                v-model="notes"
                                rows="3"
                                placeholder="Catatan tambahan (opsional)"
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            />
                        </div>

                        <div class="space-y-3">
                            <p class="text-sm font-semibold text-slate-900">
                                Hasil Pencarian
                            </p>

                            <div
                                v-if="searchingBooks"
                                class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-500"
                            >
                                Mencari buku...
                            </div>

                            <div
                                v-else-if="bookSearch.trim() && searchResults.length === 0"
                                class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500"
                            >
                                Buku tidak ditemukan.
                            </div>

                            <div
                                v-else-if="searchResults.length === 0"
                                class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500"
                            >
                                Masukkan kata pencarian terlebih dahulu.
                            </div>

                            <div
                                v-for="book in searchResults"
                                :key="book.id"
                                class="rounded-xl border border-slate-200 p-4"
                            >
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-slate-900">
                                            {{ book.title }}
                                        </p>

                                        <p class="mt-1 text-xs text-slate-500">
                                            ISBN: {{ book.isbn || '-' }}
                                        </p>

                                        <p class="mt-1 text-xs text-slate-500">
                                            Ketersediaan:
                                            {{
                                                book.availability?.available_copies ??
                                                '-'
                                            }}
                                            tersedia dari
                                            {{
                                                book.availability?.total_copies ??
                                                '-'
                                            }}
                                        </p>
                                    </div>

                                    <button
                                        type="button"
                                        class="shrink-0 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="submitting"
                                        @click="submitReservation(book)"
                                    >
                                        {{
                                            submitting
                                                ? 'Memproses...'
                                                : 'Reservasi'
                                        }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
                            <button
                                type="button"
                                class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700"
                                @click="showCreateModal = false"
                            >
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </MemberShell>
</template>
