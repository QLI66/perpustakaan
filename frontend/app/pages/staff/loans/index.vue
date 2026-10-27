<script setup lang="ts">
import { useLoansService } from '~/services/loans'
import type { Loan } from '~/types/circulation'

definePageMeta({
    middleware: 'staff',
})

const authStore = useAuthStore()
const loansService = useLoansService()

const loans = ref<Loan[]>([])
const loading = ref(true)
const search = ref('')
const statusFilter = ref('')
const currentPage = ref(1)
const perPage = 15
const total = ref(0)
const lastPage = ref(1)
const errorMessage = ref('')
const overdueFilter = ref(false)

let searchTimer: ReturnType<typeof setTimeout> | null = null
let loansRequestId = 0

const canCreate = computed(() =>
    authStore.hasPermission('loans.create') ||
    authStore.hasRole('SUPER_ADMIN'),
)

const loadLoans = async (showLoading = true) => {
    const requestId = ++loansRequestId

    if (showLoading) {
        loading.value = true
        errorMessage.value = ''
    }

    try {
        const result = await loansService.getLoans({
            page: currentPage.value,
            per_page: perPage,
            search: search.value,
            status: statusFilter.value || undefined,
            overdue: overdueFilter.value ? 1 : undefined,
        })

        if (requestId !== loansRequestId) {
            return
        }

        errorMessage.value = ''
        loans.value = result.data.items || []
        const pagination = result.data.pagination
        total.value = pagination?.total ?? 0
        lastPage.value = pagination?.last_page ?? 1
    } catch (error: unknown) {
        if (requestId !== loansRequestId) {
            return
        }

        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Gagal memuat data peminjaman.'
    } finally {
        if (requestId === loansRequestId) {
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
        loadLoans()
    }, 350)
}

const onFilterChange = () => {
    currentPage.value = 1
    loadLoans()
}

const goToPage = (page: number) => {
    currentPage.value = page
    loadLoans()
}

const formatDate = (dateString: string) => {
    if (!dateString) return '-'
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    })
}

const getStatusClass = (status: string) => {
    switch (status) {
        case 'ACTIVE': return 'bg-amber-100 text-amber-800'
        case 'COMPLETED': return 'bg-emerald-100 text-emerald-800'
        case 'CANCELLED': return 'bg-slate-100 text-slate-800'
        default: return 'bg-slate-100 text-slate-800'
    }
}

usePageRefresh(() => loadLoans(false))
onMounted(() => loadLoans())

onUnmounted(() => {
    if (searchTimer) {
        clearTimeout(searchTimer)
    }
})
</script>

<template>
    <StaffShell active="loans">
        <div class="space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">
                            Sirkulasi
                        </p>

                        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">
                            Peminjaman (Loans)
                        </h1>

                        <p class="mt-2 max-w-2xl text-sm text-slate-500">
                            Kelola data peminjaman buku oleh anggota.
                        </p>
                    </div>

                    <NuxtLink
                        v-if="canCreate"
                        to="/staff/loans/create"
                        class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800"
                    >
                        + Peminjaman Baru
                    </NuxtLink>
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
                            Daftar Peminjaman
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ total }} peminjaman ditemukan
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="overdueFilter" type="checkbox" class="rounded border-slate-300 text-slate-900 focus:ring-slate-900" @change="onFilterChange">
                            Terlambat
                        </label>
                        
                        <select
                            v-model="statusFilter"
                            class="rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            @change="onFilterChange"
                        >
                            <option value="">Semua Status</option>
                            <option value="ACTIVE">Aktif</option>
                            <option value="COMPLETED">Selesai</option>
                            <option value="CANCELLED">Dibatalkan</option>
                        </select>

                        <input
                            v-model="search"
                            type="search"
                            placeholder="Cari ID, Nama..."
                            class="rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            @input="onSearchInput"
                        >
                    </div>
                </div>

                <div v-if="loading" class="mt-5 space-y-3">
                    <div v-for="i in 5" :key="i" class="h-20 animate-pulse rounded-xl bg-slate-100"/>
                </div>

                <div v-else-if="loans.length === 0" class="mt-5 rounded-2xl border border-dashed border-slate-300 p-10 text-center">
                    <p class="font-semibold text-slate-900">Tidak ada data peminjaman</p>
                </div>

                <div v-else class="mt-5 overflow-x-auto">
                    <table class="w-full min-w-[700px] border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-4 py-3">ID Peminjaman</th>
                                <th class="px-4 py-3">Peminjam</th>
                                <th class="px-4 py-3">Tgl Pinjam</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="loan in loans" :key="loan.id" class="border-b border-slate-100 last:border-0 hover:bg-slate-50">
                                <td class="px-4 py-4 font-mono text-sm text-slate-700">
                                    {{ loan.loan_number }}
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-900">{{ loan.member?.name || '-' }}</p>
                                    <p class="text-xs text-slate-500">{{ loan.member?.member_number || '-' }}</p>
                                </td>
                                <td class="px-4 py-4 text-sm text-slate-600">
                                    {{ formatDate(loan.borrowed_at) }}
                                </td>
                                <td class="px-4 py-4">
                                    <span :class="['rounded-full px-3 py-1 text-xs font-semibold', getStatusClass(loan.status)]">
                                        {{ loan.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <NuxtLink
                                        :to="`/staff/loans/${loan.id}`"
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
