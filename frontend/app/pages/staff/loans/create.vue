<script setup lang="ts">
import {
    useMembersService,
    type MemberListItem,
} from '~/services/members'
import { useLoansService } from '~/services/loans'
import { useBookCopiesService, type BookCopyListItem } from '~/services/book-copies'
import { useReservationsService } from '~/services/reservations'
import { getApiErrorMessage } from '~/composables/useApi'
import type { Reservation } from '~/types/circulation'

definePageMeta({
    middleware: 'staff',
})

const router = useRouter()
const route = useRoute()
const toast = useToast()
const membersService = useMembersService()
const loansService = useLoansService()
const bookCopiesService = useBookCopiesService()
const reservationsService = useReservationsService()

const memberSearch = ref('')
const memberResults = ref<MemberListItem[]>([])
const selectedMember = ref<MemberListItem | null>(null)
const initializingReservation = ref(false)
const reservationCopyId = ref<number | null>(null)

const bookSearch = ref('')
const selectedCopies = ref<BookCopyListItem[]>([])
const bookResults = ref<BookCopyListItem[]>([])
const readyReservations = ref<Reservation[]>([])
const reservationLoading = ref(false)

const borrowedAt = ref('')
const loanDurationDays = ref(3)
const notes = ref('')

const computedDueDate = computed(() => {
    const base = borrowedAt.value
        ? new Date(borrowedAt.value)
        : new Date()
    const due = new Date(base)
    due.setDate(due.getDate() + loanDurationDays.value)
    return due.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    })
})

const setCurrentTime = () => {
    const now = new Date()
    const offset = now.getTimezoneOffset() * 60000
    const localISOTime = new Date(now.getTime() - offset).toISOString().slice(0, 16)
    borrowedAt.value = localISOTime
}

const memberLoading = ref(false)
const bookLoading = ref(false)
const saving = ref(false)

const errorMessage = ref('')
const successMessage = ref('')

let memberSearchTimer: ReturnType<typeof setTimeout> | null = null
let bookSearchTimer: ReturnType<typeof setTimeout> | null = null
let memberSearchRequestId = 0
let bookSearchRequestId = 0
let skipMemberSearchForSelection: string | null = null

const searchMembers = async (search: string, requestId: number) => {
    if (requestId !== memberSearchRequestId) {
        return
    }

    if (search.length < 2) {
        memberResults.value = []
        return
    }

    memberLoading.value = true
    errorMessage.value = ''

    try {
        const result = await membersService.getMembers({
            search,
            status: 'ACTIVE',
            per_page: 10,
        })

        if (requestId !== memberSearchRequestId) {
            return
        }

        memberResults.value =
            result.data.items || []
    } catch (error: unknown) {
        if (requestId !== memberSearchRequestId) {
            return
        }

        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Gagal mencari anggota.'

        memberResults.value = []
    } finally {
        if (requestId === memberSearchRequestId) {
            memberLoading.value = false
        }
    }
}

watch(memberSearch, (value) => {
    if (initializingReservation.value) {
        return
    }

    if (skipMemberSearchForSelection === value) {
        skipMemberSearchForSelection = null
        return
    }

    skipMemberSearchForSelection = null
    const requestId = ++memberSearchRequestId
    selectedMember.value = null
    memberResults.value = []

    if (memberSearchTimer) {
        clearTimeout(memberSearchTimer)
    }

    const search = value.trim()
    if (search.length < 2) {
        memberLoading.value = false
        return
    }

    memberSearchTimer = setTimeout(() => {
        searchMembers(search, requestId)
    }, 300)
})

const loadReadyReservations = async () => {
    readyReservations.value = []

    if (!selectedMember.value) {
        return
    }

    reservationLoading.value = true

    try {
        const result = await reservationsService.getReservations({
            member_id: selectedMember.value.id,
            status: 'PENDING',
            page: 1,
            per_page: 50,
        })

        const now = Date.now()
        readyReservations.value = (result.data.data || []).filter(
            reservation =>
                reservation.status === 'PENDING' &&
                Boolean(reservation.assigned_copy?.id) &&
                (
                    !reservation.expires_at ||
                    new Date(reservation.expires_at).getTime() > now
                ),
        )
    } catch (error: unknown) {
        readyReservations.value = []

        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Gagal memuat reservasi siap diambil.'
    } finally {
        reservationLoading.value = false
    }
}

const isReservedCopyForSelectedMember = (copyId: number) => {
    return readyReservations.value.some(
        reservation =>
            reservation.assigned_copy?.id === copyId &&
            (
                !reservation.expires_at ||
                new Date(reservation.expires_at).getTime() > Date.now()
            ),
    )
}

const isReservationCopySelected = (reservation: Reservation) => {
    const copyId = reservation.assigned_copy?.id

    return (
        selectedCopies.value.length >= 2 ||
        (copyId !== undefined &&
            selectedCopies.value.some(copy => copy.id === copyId))
    )
}

const selectReservationCopy = (reservation: Reservation) => {
    const assignedCopy = reservation.assigned_copy

    if (!assignedCopy) {
        return
    }

    selectBookCopy({
        id: assignedCopy.id,
        barcode: assignedCopy.barcode,
        inventory_number: assignedCopy.inventory_number,
        status: 'RESERVED',
        book: reservation.book,
    } as BookCopyListItem)
}

const selectMember = async (member: MemberListItem) => {
    memberSearchRequestId++
    if (memberSearchTimer) {
        clearTimeout(memberSearchTimer)
    }

    selectedMember.value = member
    if (memberSearch.value !== member.name) {
        skipMemberSearchForSelection = member.name
        memberSearch.value = member.name
    }
    memberResults.value = []
    memberLoading.value = false
    errorMessage.value = ''

    selectedCopies.value = []
    bookResults.value = []
    bookSearch.value = ''

    await loadReadyReservations()
}

const removeCopy = (copyId: number) => {
    selectedCopies.value =
        selectedCopies.value.filter(
            copy => copy.id !== copyId,
        )
}

const searchBooks = async (search: string, requestId: number) => {
    if (requestId !== bookSearchRequestId) {
        return
    }

    if (search.length < 2) {
        bookResults.value = []
        return
    }

    bookLoading.value = true
    errorMessage.value = ''

    try {
        const result = await bookCopiesService.getBookCopies({
            search,
            status: 'AVAILABLE',
            per_page: 10,
        })

        if (requestId !== bookSearchRequestId) {
            return
        }

        bookResults.value = result.data.items || []
    } catch (error: unknown) {
        if (requestId !== bookSearchRequestId) {
            return
        }

        errorMessage.value = error instanceof Error ? error.message : 'Gagal mencari buku.'
        bookResults.value = []
    } finally {
        if (requestId === bookSearchRequestId) {
            bookLoading.value = false
        }
    }
}

watch(bookSearch, () => {
    const requestId = ++bookSearchRequestId
    bookResults.value = []

    if (bookSearchTimer) clearTimeout(bookSearchTimer)
    
    const search = bookSearch.value.trim()
    if (search.length < 2) {
        bookLoading.value = false
        return
    }

    bookSearchTimer = setTimeout(() => {
        searchBooks(search, requestId)
    }, 300)
})

const selectBookCopy = (copy: BookCopyListItem) => {
    if (selectedCopies.value.length >= 2) {
        errorMessage.value = 'Maksimal 2 buku dalam satu peminjaman.'
        return
    }
    
    if (selectedCopies.value.some(item => item.id === copy.id)) {
        errorMessage.value = 'Maksimal satu kali untuk copy buku yang sama.'
        return
    }
    
    if (
        copy.status !== 'AVAILABLE' &&
        copy.id !== reservationCopyId.value &&
        !isReservedCopyForSelectedMember(copy.id)
    ) {
        errorMessage.value = `Copy buku berstatus ${copy.status} dan tidak dapat dipinjam.`
        return
    }

    selectedCopies.value.push(copy)
    bookSearch.value = ''
    bookResults.value = []
    errorMessage.value = ''
}

const findCopyByBarcode = async () => {
    const barcode = bookSearch.value.trim()

    if (!barcode) return

    if (selectedCopies.value.length >= 2) {
        errorMessage.value = 'Maksimal 2 buku dalam satu peminjaman.'
        return
    }

    if (selectedCopies.value.some(copy => copy.barcode === barcode)) {
        errorMessage.value = 'Maksimal satu kali untuk copy buku yang sama.'
        bookSearch.value = ''
        return
    }

    bookLoading.value = true
    errorMessage.value = ''

    try {
        const result = await bookCopiesService.getCopyByBarcode(barcode)
        const copy = result.data

        if (!copy) throw new Error('Copy buku tidak ditemukan.')
        if (
            copy.status !== 'AVAILABLE' &&
            copy.id !== reservationCopyId.value &&
            !isReservedCopyForSelectedMember(copy.id)
        ) {
            throw new Error(
                `Copy buku berstatus ${copy.status} dan tidak dapat dipinjam.`,
            )
        }
        
        selectBookCopy(copy)
    } catch (error: unknown) {
        errorMessage.value = error instanceof Error ? error.message : 'Gagal mencari barcode buku.'
    } finally {
        bookLoading.value = false
    }
}


const initializeReservationLoan = async () => {
    const reservationId = Number(route.query.reservation_id)

    if (!reservationId || Number.isNaN(reservationId)) {
        return
    }

    errorMessage.value = ''
    successMessage.value = ''
    initializingReservation.value = true

    try {
        const result = await reservationsService.getReservation(reservationId)
        const reservation = result.data

        if (!reservation) {
            throw new Error('Data reservasi tidak ditemukan.')
        }

        if (
            reservation.status !== 'PENDING' ||
            !reservation.assigned_copy?.id
        ) {
            throw new Error(
                `Reservasi belum memiliki copy yang ditugaskan. Status saat ini: ${reservation.status}.`,
            )
        }

        if (!reservation.member?.id) {
            throw new Error('Anggota pada reservasi tidak ditemukan.')
        }

        if (!reservation.assigned_copy?.id) {
            throw new Error(
                'Copy buku yang ditugaskan pada reservasi tidak ditemukan.',
            )
        }

        await selectMember(
            reservation.member as MemberListItem,
        )

        reservationCopyId.value = reservation.assigned_copy.id

        const reservedCopy = {
            id: reservation.assigned_copy.id,
            barcode: reservation.assigned_copy.barcode,
            inventory_number: reservation.assigned_copy.inventory_number,
            status: 'RESERVED',
            book: reservation.book,
        } as BookCopyListItem

        selectBookCopy(reservedCopy)

        successMessage.value =
            'Reservasi dan copy buku telah diisi ke form peminjaman. Silakan pilih durasi peminjaman.'
    } catch (error: unknown) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Gagal memuat data reservasi.'
    } finally {
        initializingReservation.value = false
    }
}
const submit = async () => {
    errorMessage.value = ''
    successMessage.value = ''

    if (!selectedMember.value) {
        errorMessage.value =
            'Pilih anggota terlebih dahulu.'

        return
    }

    if (selectedCopies.value.length === 0) {
        errorMessage.value =
            'Tambahkan minimal 1 copy buku.'

        return
    }

    if (selectedCopies.value.length > 2) {
        errorMessage.value =
            'Maksimal 2 copy buku.'

        return
    }

    saving.value = true

    try {
        const payload: {
            member_id: number
            book_copy_ids: number[]
            notes?: string
            borrowed_at?: string
            loan_duration_days: number
        } = {
            member_id:
                selectedMember.value.id,

            book_copy_ids:
                selectedCopies.value.map(
                    copy => copy.id,
                ),

            loan_duration_days: loanDurationDays.value,
        }

        if (notes.value.trim()) {
            payload.notes =
                notes.value.trim()
        }

        if (borrowedAt.value) {
            payload.borrowed_at =
                borrowedAt.value
        }

        const result =
            await loansService.createLoan(
                payload,
            )

        const newLoan =
            result.data as { id: number }

        toast.add({
            severity: 'success',
            summary: 'Peminjaman berhasil dibuat',
            detail: 'Data peminjaman tersimpan.',
            life: 6000,
        })

        await router.push(
            `/staff/loans/${newLoan.id}`,
        )
    } catch (error: unknown) {
        errorMessage.value = getApiErrorMessage(
            error,
            'Gagal membuat peminjaman.',
        )
    } finally {
        saving.value = false
    }
}

onMounted(initializeReservationLoan)

onBeforeUnmount(() => {
    memberSearchRequestId++
    bookSearchRequestId++
    if (memberSearchTimer) clearTimeout(memberSearchTimer)
    if (bookSearchTimer) clearTimeout(bookSearchTimer)
})
</script>

<template>
    <StaffShell active="loans">
        <div class="mx-auto max-w-4xl space-y-6">

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-500">
                            Peminjaman Baru
                        </p>

                        <h1 class="mt-1 text-3xl font-bold text-slate-900">
                            Buat Peminjaman
                        </h1>

                        <p class="mt-2 text-sm text-slate-500">
                            Pilih anggota dan buku yang akan dipinjam.
                        </p>
                    </div>

                    <NuxtLink
                        to="/staff/loans"
                        class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Batal
                    </NuxtLink>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">

                <div
                    v-if="errorMessage"
                    class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
                >
                    {{ errorMessage }}
                </div>

                <div
                    v-if="successMessage"
                    class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700"
                >
                    {{ successMessage }}
                </div>

                <div class="space-y-7">

                    <!-- ANGGOTA -->
                    <section>
                        <label class="mb-2 block text-sm font-semibold text-slate-900">
                            Anggota *
                        </label>

                        <div class="relative">
                            <input
                                v-model="memberSearch"
                                type="text"
                                autocomplete="off"
                                placeholder="Cari nama atau nomor anggota..."
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            >

                            <div
                                v-if="memberLoading"
                                class="mt-2 text-xs text-slate-500"
                            >
                                Mencari anggota...
                            </div>

                            <div
                                v-else-if="memberResults.length > 0 && !selectedMember"
                                class="mt-2 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
                            >
                                <button
                                    v-for="member in memberResults"
                                    :key="member.id"
                                    type="button"
                                    class="block w-full border-b border-slate-100 px-4 py-3 text-left last:border-b-0 hover:bg-slate-50"
                                    @click="selectMember(member)"
                                >
                                    <div class="font-semibold text-slate-900">
                                        {{ member.name }}
                                    </div>

                                    <div class="mt-1 text-xs text-slate-500">
                                        {{ member.member_number }}
                                        <span
                                            v-if="member.member_type"
                                        >
                                            &middot; {{ member.member_type.name }}
                                        </span>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="selectedMember"
                            class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">
                                        Anggota Terpilih
                                    </p>

                                    <p class="mt-1 font-semibold text-slate-900">
                                        {{ selectedMember.name }}
                                    </p>

                                    <p class="mt-1 text-sm text-slate-600">
                                        {{ selectedMember.member_number }}
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="text-sm font-semibold text-rose-600 hover:text-rose-700"
                                    @click="selectedMember = null; memberSearch = ''; readyReservations = []; selectedCopies = []"
                                >
                                    Ganti
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="selectedMember && (reservationLoading || readyReservations.length > 0)"
                            class="mt-4 rounded-2xl border border-indigo-200 bg-indigo-50 p-4"
                        >
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">
                                        Reservasi Siap Diambil
                                    </p>

                                    <p class="mt-1 text-sm text-indigo-900">
                                        Copy berikut khusus untuk anggota ini.
                                    </p>
                                </div>

                                <span
                                    v-if="reservationLoading"
                                    class="text-xs font-medium text-indigo-600"
                                >
                                    Memuat...
                                </span>
                            </div>

                            <div
                                v-if="readyReservations.length > 0"
                                class="space-y-3"
                            >
                                <div
                                    v-for="reservation in readyReservations"
                                    :key="reservation.id"
                                    class="rounded-xl border border-indigo-200 bg-white p-4"
                                >
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <p class="font-semibold text-slate-900">
                                                {{ reservation.book?.title || 'Buku' }}
                                            </p>

                                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                                <span v-if="reservation.assigned_copy?.barcode">
                                                    Barcode: {{ reservation.assigned_copy.barcode }}
                                                </span>

                                                <span v-if="reservation.assigned_copy?.inventory_number">
                                                    Inventaris: {{ reservation.assigned_copy.inventory_number }}
                                                </span>

                                                <span class="font-semibold text-indigo-600">
                                                    READY
                                                </span>
                                            </div>
                                        </div>

                                        <button
                                            v-if="reservation.assigned_copy"
                                            type="button"
                                            class="shrink-0 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="isReservationCopySelected(reservation)"
                                            @click="selectReservationCopy(reservation)"
                                        >
                                            {{
                                                reservation.assigned_copy &&
                                                selectedCopies.some(copy => copy.id === reservation.assigned_copy?.id)
                                                    ? 'Sudah Dipilih'
                                                    : 'Pilih'
                                            }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>


                    <!-- BARCODE -->
                    <section>
                        <label for="bookSearch" class="mb-2 block text-sm font-semibold text-slate-900">
                            Pilih Buku *
                        </label>

                        <div class="relative">
                            <div class="flex gap-2">
                                <input
                                    id="bookSearch"
                                    v-model="bookSearch"
                                    type="text"
                                    autocomplete="off"
                                    placeholder="Cari judul buku atau scan barcode..."
                                    class="min-w-0 flex-1 rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                                    @keyup.enter="findCopyByBarcode"
                                >

                                <button
                                    type="button"
                                    :disabled="bookLoading || selectedCopies.length >= 2"
                                    class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                                    @click="findCopyByBarcode"
                                >
                                    {{ bookLoading ? 'Mencari...' : 'Tambah' }}
                                </button>
                            </div>
                            
                            <div
                                v-if="bookResults.length > 0"
                                class="absolute z-10 mt-2 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
                            >
                                <button
                                    v-for="copy in bookResults"
                                    :key="copy.id"
                                    type="button"
                                    class="block w-full border-b border-slate-100 px-4 py-3 text-left last:border-b-0 hover:bg-slate-50"
                                    @click="selectBookCopy(copy)"
                                >
                                    <div class="font-semibold text-slate-900">
                                        {{ copy.book?.title || 'Buku' }}
                                    </div>

                                    <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                        <span>Barcode: {{ copy.barcode }}</span>
                                        <span v-if="copy.inventory_number">Inventaris: {{ copy.inventory_number }}</span>
                                        <span v-if="copy.location">Lokasi: {{ copy.location.name }}</span>
                                        <span class="font-semibold text-emerald-600" v-if="copy.status === 'AVAILABLE'">TERSEDIA</span>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <p class="mt-2 text-xs text-slate-500">
                            Maksimal 2 buku. Barcode dapat berasal dari scanner USB/kamera.
                        </p>

                        <div
                            v-if="selectedCopies.length > 0"
                            class="mt-4 space-y-3"
                        >
                            <div
                                v-for="copy in selectedCopies"
                                :key="copy.id"
                                class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4"
                            >
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-900">
                                        {{ copy.book?.title || 'Buku' }}
                                    </p>

                                    <p class="mt-1 text-sm text-slate-500">
                                        Barcode:
                                        {{ copy.barcode }}
                                    </p>

                                    <p
                                        v-if="copy.inventory_number"
                                        class="text-xs text-slate-500"
                                    >
                                        Inventaris:
                                        {{ copy.inventory_number }}
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="shrink-0 rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50"
                                    @click="removeCopy(copy.id)"
                                >
                                    Hapus
                                </button>
                            </div>
                        </div>
                    </section>


                    <!-- TANGGAL & WAKTU -->
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <label for="borrowedAt" class="block text-sm font-semibold text-slate-900">
                                Tanggal & Waktu Pinjam
                            </label>

                            <button
                                type="button"
                                class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 rounded"
                                @click="setCurrentTime"
                                aria-label="Gunakan Waktu Sekarang"
                            >
                                Gunakan Waktu Sekarang
                            </button>
                        </div>

                        <input
                            id="borrowedAt"
                            v-model="borrowedAt"
                            type="datetime-local"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >

                        <p class="mt-2 text-xs text-slate-500">
                            Kosongkan untuk menggunakan waktu dari server.
                        </p>
                    </div>


                    <!-- LAMA PEMINJAMAN -->
                    <div>
                        <label for="loanDuration" class="mb-2 block text-sm font-semibold text-slate-900">
                            Lama Peminjaman *
                        </label>

                        <select
                            id="loanDuration"
                            v-model="loanDurationDays"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option :value="1">1 hari</option>
                            <option :value="2">2 hari</option>
                            <option :value="3">3 hari</option>
                        </select>

                        <p class="mt-2 text-xs text-slate-500">
                            Durasi peminjaman maksimal 3 hari.
                        </p>
                    </div>


                    <!-- JATUH TEMPO -->
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-900">
                            Jatuh Tempo
                        </label>

                        <div class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                            {{ computedDueDate }}
                        </div>

                        <p class="mt-2 text-xs text-slate-500">
                            Dihitung otomatis. Backend menjadi sumber kebenaran.
                        </p>
                    </div>


                    <!-- CATATAN -->
                    <div>
                        <label for="notes" class="mb-2 block text-sm font-semibold text-slate-900">
                            Catatan
                        </label>

                        <textarea
                            id="notes"
                            v-model="notes"
                            rows="4"
                            placeholder="Catatan peminjaman..."
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        />
                    </div>


                    <!-- SUBMIT -->
                    <div class="border-t border-slate-100 pt-5">
                        <div class="mb-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
                            <div class="flex justify-between gap-4">
                                <span>Anggota</span>
                                <strong class="text-slate-900">
                                    {{ selectedMember?.name || 'Belum dipilih' }}
                                </strong>
                            </div>

                            <div class="mt-2 flex justify-between gap-4">
                                <span>Jumlah buku</span>
                                <strong class="text-slate-900">
                                    {{ selectedCopies.length }} / 2
                                </strong>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button
                                type="button"
                                :disabled="
                                    saving ||
                                    !selectedMember ||
                                    selectedCopies.length === 0
                                "
                                class="rounded-xl bg-slate-900 px-6 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                                @click="submit"
                            >
                                {{
                                    saving
                                        ? 'Menyimpan...'
                                        : 'Simpan Peminjaman'
                                }}
                            </button>
                        </div>
                    </div>

                </div>
            </section>
        </div>
    </StaffShell>
</template>
