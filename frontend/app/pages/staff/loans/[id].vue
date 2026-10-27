<script setup lang="ts">
import { getApiErrorMessage } from '~/composables/useApi'
import { useLoansService } from '~/services/loans'
import type { Loan, LoanItem } from '~/types/circulation'

definePageMeta({
    middleware: 'staff',
})

const route = useRoute()
const loansService = useLoansService()
const authStore = useAuthStore()

const loanId = computed(() => Number(route.params.id))
const loan = ref<Loan | null>(null)
const loading = ref(true)
const errorMessage = ref('')
const successMessage = ref('')

const canReturn = computed(() => authStore.hasPermission('loans.return') || authStore.hasRole('SUPER_ADMIN'))
const canRenew = computed(() => authStore.hasPermission('loans.renew') || authStore.hasRole('SUPER_ADMIN'))

const loadLoan = async () => {
    loading.value = true
    errorMessage.value = ''
    try {
        const result = await loansService.getLoan(loanId.value)
        loan.value = result.data || (result as unknown as Loan)
    } catch (error: unknown) {
        errorMessage.value = error instanceof Error ? error.message : 'Gagal memuat detail peminjaman.'
    } finally {
        loading.value = false
    }
}

const confirmReturn = async (item: LoanItem) => {
    const confirmed = window.confirm(`Kembalikan buku "${item.book_copy?.book?.title}"?`)
    if (!confirmed) return

    errorMessage.value = ''
    successMessage.value = ''
    try {
        await loansService.returnItem(loanId.value, item.id)
        successMessage.value = 'Buku berhasil dikembalikan.'
        await loadLoan()
    } catch (error: unknown) {
        errorMessage.value = getApiErrorMessage(
            error,
            'Gagal mengembalikan buku.',
        )
    }
}

const confirmRenew = async (item: LoanItem) => {
    const confirmed = window.confirm(`Perpanjang peminjaman buku "${item.book_copy?.book?.title}"?`)
    if (!confirmed) return

    errorMessage.value = ''
    successMessage.value = ''
    try {
        await loansService.renewItem(loanId.value, item.id)
        successMessage.value = 'Peminjaman berhasil diperpanjang.'
        await loadLoan()
    } catch (error: unknown) {
        errorMessage.value = getApiErrorMessage(
            error,
            'Gagal memperpanjang peminjaman.',
        )
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

const money = (value: number | string) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value || 0))

const getStatusClass = (status: string) => {
    switch (status) {
        case 'ACTIVE': return 'bg-amber-100 text-amber-800'
        case 'RETURNED': return 'bg-emerald-100 text-emerald-800'
        case 'COMPLETED': return 'bg-emerald-100 text-emerald-800'
        case 'LOST':
        case 'DAMAGED': return 'bg-rose-100 text-rose-800'
        case 'CANCELLED': return 'bg-slate-100 text-slate-800'
        default: return 'bg-slate-100 text-slate-800'
    }
}

const isItemOverdue = (item: LoanItem) => {
    return item.status === 'ACTIVE'
        && item.is_overdue
}

const getLoanItemStatusLabel = (item: LoanItem) => {
    switch (item.status) {
        case 'ACTIVE':
            return isItemOverdue(item) ? 'Terlambat' : 'Sedang Dipinjam'
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

const getLoanItemStatusClass = (item: LoanItem) => {
    if (item.status === 'ACTIVE' && isItemOverdue(item)) {
        return 'bg-rose-100 text-rose-800'
    }

    return getStatusClass(item.status)
}

onMounted(loadLoan)
</script>

<template>
    <StaffShell active="loans">
        <div class="space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Detail Peminjaman</p>
                        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">
                            {{ loan?.loan_number || 'Memuat...' }}
                        </h1>
                    </div>
                    <NuxtLink to="/staff/loans" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        ← Kembali
                    </NuxtLink>
                </div>
            </section>

            <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
                Memuat detail peminjaman...
            </div>

            <div v-else-if="errorMessage" class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-700">
                {{ errorMessage }}
            </div>

            <template v-else-if="loan">
                <div v-if="successMessage" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">
                    {{ successMessage }}
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-bold text-slate-900 mb-4">Informasi Peminjam</h2>
                        <dl class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs text-slate-500">Nama</dt>
                                <dd class="mt-1 font-semibold text-slate-900">{{ loan.member?.name }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Nomor Anggota</dt>
                                <dd class="mt-1 text-sm text-slate-900">{{ loan.member?.member_number }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-bold text-slate-900 mb-4">Informasi Transaksi</h2>
                        <dl class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs text-slate-500">Status</dt>
                                <dd class="mt-1">
                                    <span :class="['rounded-full px-3 py-1 text-xs font-semibold', getStatusClass(loan.status)]">
                                        {{ loan.status }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Tanggal Pinjam</dt>
                                <dd class="mt-1 text-sm text-slate-900">{{ formatDate(loan.borrowed_at) }}</dd>
                            </div>
                        </dl>
                    </section>
                </div>

                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-900 mb-4">Daftar Buku Dipinjam</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[650px] border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                                    <th class="px-4 py-3">Buku / Barcode</th>
                                    <th class="px-4 py-3">Tenggat Waktu</th>
                                    <th class="px-4 py-3">Perpanjangan</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in loan.items" :key="item.id" class="border-b border-slate-100 last:border-0">
                                    <td class="px-4 py-4">
                                        <p class="font-semibold text-slate-900">{{ item.book_copy?.book?.title || 'Unknown' }}</p>
                                        <p class="text-xs font-mono text-slate-500">{{ item.book_copy?.barcode || '-' }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-slate-600">
                                        {{ formatDate(item.due_at) }}
                                        <NuxtLink
                                            v-if="isItemOverdue(item)"
                                            :to="{ path: '/staff/fines', query: { loan_item_id: item.id } }"
                                            class="mt-1 block text-xs font-semibold text-rose-600 hover:underline"
                                        >
                                            Terlambat · Lihat denda berjalan
                                        </NuxtLink>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-slate-600">
                                        {{ item.renewal_count }}x
                                    </td>
                                    <td class="px-4 py-4">
                                        <span :class="['rounded-full px-3 py-1 text-xs font-semibold', getLoanItemStatusClass(item)]">
                                            {{ getLoanItemStatusLabel(item) }}
                                        </span>
                                        <div v-if="item.status === 'RETURNED'" class="mt-2 text-xs">
                                            <template v-if="item.fine">
                                                <p class="font-semibold text-slate-800">
                                                    Denda {{ money(item.fine.amount) }} · {{ item.fine.late_days }} hari
                                                </p>
                                                <p class="mt-1 text-slate-500">
                                                    Dibayar {{ money(item.fine.amount_paid) }} · {{ item.fine.status }}
                                                </p>
                                                <NuxtLink
                                                    :to="`/staff/fines/${item.fine.id}`"
                                                    class="mt-1 inline-block font-semibold text-indigo-700 hover:text-indigo-900"
                                                >
                                                    Detail denda
                                                </NuxtLink>
                                            </template>
                                            <p v-else class="mt-1 text-slate-500">Tidak ada denda.</p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-right space-x-2">
                                        <button
                                            v-if="canRenew && item.status === 'ACTIVE'"
                                            class="rounded-lg border border-indigo-200 text-indigo-700 px-3 py-1.5 text-xs font-semibold hover:bg-indigo-50"
                                            @click="confirmRenew(item)"
                                        >
                                            Perpanjang
                                        </button>
                                        <button
                                            v-if="canReturn && item.status === 'ACTIVE'"
                                            class="rounded-lg border border-emerald-200 text-emerald-700 px-3 py-1.5 text-xs font-semibold hover:bg-emerald-50"
                                            @click="confirmReturn(item)"
                                        >
                                            Kembalikan
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </template>
        </div>
    </StaffShell>
</template>
