<script setup lang="ts">
import { useReservationsService } from '~/services/reservations'
import type { Reservation } from '~/types/circulation'

definePageMeta({
    middleware: 'staff',
})

const route = useRoute()
const reservationsService = useReservationsService()
const { apiFetch } = useApi()
const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()

const reservationId = computed(() => Number(route.params.id))
const reservation = ref<Reservation | null>(null)
const loading = ref(true)
const errorMessage = ref('')
const successMessage = ref('')

const canManage = computed(() => authStore.hasPermission('reservations.manage') || authStore.hasRole('SUPER_ADMIN'))
const assignBookCopyId = ref<number | null>(null)
type AvailableBookCopy = {
    id: number
    barcode: string
    inventory_number: string | null
}
const availableBookCopies = ref<AvailableBookCopy[]>([])
const loadingAvailableCopies = ref(false)
const availableCopiesError = ref('')


const loadAvailableBookCopies = async () => {
    const currentReservation = reservation.value
    const currentBookId = currentReservation?.book?.id

    availableCopiesError.value = ''

    if (!currentBookId || !canManage.value || currentReservation?.status !== 'PENDING') {
        availableBookCopies.value = []
        assignBookCopyId.value = null
        return
    }

    loadingAvailableCopies.value = true

    try {
        const response = await apiFetch<{ items?: AvailableBookCopy[] }>('/book-copies', {
            method: 'GET',
            query: {
                page: 1,
                per_page: 50,
                book_id: currentBookId,
                status: 'AVAILABLE',
            },
            headers: {
                Accept: 'application/json',
                Authorization: authStore.token ? `Bearer ${authStore.token}` : '',
            },
        })

        availableBookCopies.value = response.data?.items ?? []

        if (!availableBookCopies.value.some((copy) => copy.id === assignBookCopyId.value)) {
            assignBookCopyId.value = null
        }
    } catch (error: unknown) {
        availableBookCopies.value = []
        assignBookCopyId.value = null
        availableCopiesError.value = error instanceof Error
            ? error.message
            : 'Gagal memuat copy buku yang tersedia.'
    } finally {
        loadingAvailableCopies.value = false
    }
}

const loadReservation = async () => {
    loading.value = true
    errorMessage.value = ''
    try {
        const result = await reservationsService.getReservation(reservationId.value)
        reservation.value = result.data || (result as unknown as Reservation)
        await loadAvailableBookCopies()
    } catch (error: unknown) {
        errorMessage.value = error instanceof Error ? error.message : 'Gagal memuat detail reservasi.'
    } finally {
        loading.value = false
    }
}

const confirmCancel = async () => {
    const confirmed = window.confirm(`Batalkan reservasi ini?`)
    if (!confirmed) return

    errorMessage.value = ''
    successMessage.value = ''
    try {
        await reservationsService.cancelReservation(reservationId.value)
        successMessage.value = 'Reservasi berhasil dibatalkan.'
        await loadReservation()
    } catch (error: unknown) {
        errorMessage.value = error instanceof Error ? error.message : 'Gagal membatalkan reservasi.'
    }
}

const submitAssign = async () => {
    if (!assignBookCopyId.value) {
        errorMessage.value = 'Pilih copy buku terlebih dahulu.'
        return
    }

    const selectedCopy = availableBookCopies.value.find(
        (copy) => copy.id === assignBookCopyId.value,
    )

    if (!selectedCopy) {
        errorMessage.value = 'Copy buku yang dipilih sudah tidak tersedia.'
        await loadAvailableBookCopies()
        return
    }

    const confirmed = window.confirm(
        `Tugaskan copy ${selectedCopy.barcode} ke reservasi ini?`,
    )

    if (!confirmed) return

    errorMessage.value = ''
    successMessage.value = ''

    try {
        await reservationsService.assignReservation(
            reservationId.value,
            assignBookCopyId.value,
        )

        successMessage.value = `Copy ${selectedCopy.barcode} berhasil ditugaskan untuk reservasi ini.`
        toast.add({
            severity: 'success',
            summary: 'Reservasi ditugaskan',
            detail: `Copy ${selectedCopy.barcode} siap diproses sebagai peminjaman.`,
            life: 6000,
        })
        assignBookCopyId.value = null
        await router.push(
            `/staff/loans/create?reservation_id=${reservationId.value}`,
        )
    } catch (error: unknown) {
        errorMessage.value = error instanceof Error
            ? error.message
            : 'Gagal menugaskan buku.'
        await loadAvailableBookCopies()
    }
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

onMounted(loadReservation)
</script>

<template>
    <StaffShell active="reservations">
        <div class="space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Detail Reservasi</p>
                        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">
                            {{ reservation?.reservation_number || 'Memuat...' }}
                        </h1>
                    </div>
                    <NuxtLink to="/staff/reservations" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        &larr; Kembali
                    </NuxtLink>
                </div>
            </section>

            <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
                Memuat detail reservasi...
            </div>

            <div v-else-if="errorMessage" class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-700">
                {{ errorMessage }}
            </div>

            <template v-else-if="reservation">
                <div v-if="successMessage" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">
                    {{ successMessage }}
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-bold text-slate-900 mb-4">Informasi Peminjam</h2>
                        <dl class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs text-slate-500">Nama</dt>
                                <dd class="mt-1 font-semibold text-slate-900">{{ reservation.member?.name }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Nomor Anggota</dt>
                                <dd class="mt-1 text-sm text-slate-900">{{ reservation.member?.member_number }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-bold text-slate-900 mb-4">Informasi Reservasi</h2>
                        <dl class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs text-slate-500">Status</dt>
                                <dd class="mt-1">
                                    <span :class="['rounded-full px-3 py-1 text-xs font-semibold', getStatusClass(reservation.status)]">
                                        {{ reservation.status }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Tanggal Reservasi</dt>
                                <dd class="mt-1 text-sm text-slate-900">{{ formatDate(reservation.reserved_at) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Buku Yang Dipesan</dt>
                                <dd class="mt-1 font-semibold text-slate-900">{{ reservation.book?.title }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Copy Buku Ditugaskan</dt>
                                <dd class="mt-1 text-sm font-mono text-slate-900">{{ reservation.assigned_copy?.barcode || '-' }}</dd>
                            </div>
                        </dl>
                    </section>
                </div>

                <section v-if="canManage && reservation.status === 'PENDING'" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
            <h2 class="text-lg font-bold text-slate-900">Tindakan Reservasi</h2>
            <p class="mt-1 text-sm text-slate-500">
                Pilih copy buku yang tersedia untuk reservasi ini.
            </p>
        </div>

        <div class="w-full lg:max-w-xl">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-bold text-slate-900">Copy Buku Tersedia</h3>
                    <button
                        type="button"
                        class="text-xs font-semibold text-slate-600 hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="loadingAvailableCopies"
                        @click="loadAvailableBookCopies"
                    >
                        Refresh
                    </button>
                </div>

                <div v-if="loadingAvailableCopies" class="mt-3 text-sm text-slate-500">
                    Memuat copy buku yang tersedia...
                </div>

                <div
                    v-else-if="availableCopiesError"
                    class="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700"
                    role="alert"
                >
                    {{ availableCopiesError }}
                </div>

                <div
                    v-else-if="availableBookCopies.length === 0"
                    class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800"
                >
                    Tidak ada copy buku yang tersedia saat ini.
                    Reservasi akan tetap berstatus PENDING.
                </div>

                <div v-else class="mt-3 space-y-2">
                    <label
                        v-for="copy in availableBookCopies"
                        :key="copy.id"
                        class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 transition hover:border-slate-400"
                        :class="assignBookCopyId === copy.id ? 'border-emerald-500 ring-2 ring-emerald-100' : ''"
                    >
                        <input
                            v-model="assignBookCopyId"
                            type="radio"
                            name="reservation-book-copy"
                            :value="copy.id"
                            class="h-4 w-4"
                        >

                        <div class="min-w-0">
                            <div class="font-mono text-sm font-bold text-slate-900">
                                {{ copy.barcode }}
                            </div>

                            <div
                                v-if="copy.inventory_number"
                                class="text-xs text-slate-500"
                            >
                                Inventaris: {{ copy.inventory_number }}
                            </div>
                        </div>
                    </label>
                </div>

                <button
                    type="button"
                    class="mt-4 w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!assignBookCopyId || loadingAvailableCopies"
                    @click="submitAssign"
                >
                    Tugaskan Copy Terpilih
                </button>
            </div>

            <button
                type="button"
                class="mt-3 w-full rounded-lg border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50"
                @click="confirmCancel"
            >
                Batalkan Reservasi
            </button>
        </div>
    </div>
</section>
            </template>
        </div>
    </StaffShell>
</template>
