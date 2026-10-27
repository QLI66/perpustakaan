<script setup lang="ts">
import { useReservationsService } from '~/services/reservations'
import { createVisibilityPolling } from '~/utils/visibility-polling'
import type { Reservation } from '~/types/circulation'

definePageMeta({
    middleware: 'staff',
})


const reservationsService = useReservationsService()

const reservations = ref<Reservation[]>([])
const loading = ref(true)
const search = ref('')
const statusFilter = ref('')
const currentPage = ref(1)
const perPage = 15
const total = ref(0)
const lastPage = ref(1)
const errorMessage = ref('')

let searchTimer: ReturnType<typeof setTimeout> | null = null
let reservationRequestId = 0
let reservationRequestInFlight = false
let activeReservationController: AbortController | null = null
let reservationsPageUnmounted = false

const loadReservations = async (
    showLoading = true,
    replaceCurrent = true,
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
        const result = await reservationsService.getReservations({
            page: currentPage.value,
            per_page: perPage,
            search: search.value,
            status: statusFilter.value || undefined,
        }, controller.signal)

        if (reservationsPageUnmounted || requestId !== reservationRequestId) {
            return
        }

        errorMessage.value = ''
        reservations.value = result.data.data || []
        const meta = result.data.meta
        total.value = meta?.total ?? 0
        lastPage.value = meta?.last_page ?? 1
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
                : 'Gagal memuat data reservasi.'
    } finally {
        if (requestId === reservationRequestId) {
            reservationRequestInFlight = false
            activeReservationController = null
            loading.value = false
        }
    }
}

const onSearchInput = () => {
    if (searchTimer) {
        clearTimeout(searchTimer)
    }

    searchTimer = setTimeout(() => {
        currentPage.value = 1
        loadReservations()
    }, 350)
}

const onFilterChange = () => {
    currentPage.value = 1
    loadReservations()
}

const goToPage = (page: number) => {
    currentPage.value = page
    loadReservations()
}

const formatDate = (dateString: string | null) => {
    if (!dateString) return '-'
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    })
}

const getStatusClass = (status: string) => {
    switch (status) {
        case 'PENDING': return 'bg-amber-100 text-amber-800'
        case 'FULFILLED': return 'bg-emerald-100 text-emerald-800'
        case 'CANCELLED': return 'bg-slate-100 text-slate-800'
        case 'EXPIRED': return 'bg-rose-100 text-rose-800'
        default: return 'bg-slate-100 text-slate-800'
    }
}

let reservationPolling: ReturnType<typeof createVisibilityPolling> | null =
    null

onMounted(() => {
    reservationPolling = createVisibilityPolling(
        document,
        () => loadReservations(false),
        () => {
            void loadReservations(false, false)
        },
    )
    void reservationPolling.start()
})

onUnmounted(() => {
    reservationsPageUnmounted = true
    reservationRequestId++
    activeReservationController?.abort()
    activeReservationController = null
    reservationRequestInFlight = false
    reservationPolling?.dispose()

    if (searchTimer) {
        clearTimeout(searchTimer)
    }

})
</script>

<template>
    <StaffShell active="reservations">
        <div class="space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">
                            Sirkulasi
                        </p>

                        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">
                            Reservasi (Reservations)
                        </h1>

                        <p class="mt-2 max-w-2xl text-sm text-slate-500">
                            Kelola pemesanan buku oleh anggota.
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

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">
                            Daftar Reservasi
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ total }} reservasi ditemukan
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <select
                            v-model="statusFilter"
                            class="rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            @change="onFilterChange"
                        >
                            <option value="">Semua Status</option>
                            <option value="PENDING">Menunggu (Pending)</option>
                            <option value="FULFILLED">Dipenuhi (Fulfilled)</option>
                            <option value="CANCELLED">Dibatalkan</option>
                            <option value="EXPIRED">Kadaluarsa</option>
                        </select>

                        <input
                            v-model="search"
                            type="search"
                            placeholder="Cari ID, Buku, Anggota..."
                            class="rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            @input="onSearchInput"
                        >
                    </div>
                </div>

                <div v-if="loading" class="mt-5 space-y-3">
                    <div v-for="i in 5" :key="i" class="h-20 animate-pulse rounded-xl bg-slate-100"/>
                </div>

                <div v-else-if="reservations.length === 0" class="mt-5 rounded-2xl border border-dashed border-slate-300 p-10 text-center">
                    <p class="font-semibold text-slate-900">Tidak ada data reservasi</p>
                </div>

                <div v-else class="mt-5 overflow-x-auto">
                    <table class="w-full min-w-[700px] border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-4 py-3">ID Reservasi</th>
                                <th class="px-4 py-3">Peminjam</th>
                                <th class="px-4 py-3">Buku</th>
                                <th class="px-4 py-3">Tgl Reservasi</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="reservation in reservations" :key="reservation.id" class="border-b border-slate-100 last:border-0 hover:bg-slate-50">
                                <td class="px-4 py-4 font-mono text-sm text-slate-700">
                                    {{ reservation.reservation_number }}
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-900">{{ reservation.member?.name || '-' }}</p>
                                    <p class="text-xs text-slate-500">{{ reservation.member?.member_number || '-' }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="text-sm font-semibold text-slate-900 truncate max-w-[200px]">{{ reservation.book?.title || '-' }}</p>
                                </td>
                                <td class="px-4 py-4 text-sm text-slate-600">
                                    {{ formatDate(reservation.reserved_at) }}
                                </td>
                                <td class="px-4 py-4">
                                    <span :class="['rounded-full px-3 py-1 text-xs font-semibold', getStatusClass(reservation.status)]">
                                        {{ reservation.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <NuxtLink
                                        :to="`/staff/reservations/${reservation.id}`"
                                        class="inline-block rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                    >
                                        Detail
                                    </NuxtLink>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="lastPage > 1" class="mt-5 flex flex-wrap items-center justify-center gap-2">
                    <button
                        v-for="page in lastPage"
                        :key="page"
                        type="button"
                        class="h-9 min-w-9 rounded-lg border px-3 text-sm font-semibold"
                        :class="page === currentPage ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-700 hover:bg-slate-50'"
                        @click="goToPage(page)"
                    >
                        {{ page }}
                    </button>
                </div>
            </section>
        </div>
    </StaffShell>
</template>
