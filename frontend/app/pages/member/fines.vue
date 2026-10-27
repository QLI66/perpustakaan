<script setup lang="ts">
import type {
    FineRecord,
    FineSummary,
    MemberProfile,
} from '~/types/member'

definePageMeta({
    middleware: 'auth',
})

const {
    getProfile,
    getFines,
} = useMember()

const profile = ref<MemberProfile | null>(null)
const fines = ref<FineRecord[]>([])
const fineSummary = ref<FineSummary | null>(null)
const loading = ref(true)
const errorMessage = ref('')
let finesRequestId = 0

const money = (value: number) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value)

const dateTime = (value: string | null) => {
    if (!value) return '-'

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value))
}

const totalAmount = computed(() =>
    Number(fineSummary.value?.total_amount ?? 0),
)

const totalPaid = computed(() =>
    Number(fineSummary.value?.total_paid ?? 0),
)

const outstanding = computed(() =>
    Number(fineSummary.value?.outstanding_amount ?? 0),
)

const loadFines = async (showLoading = true) => {
    const requestId = ++finesRequestId

    if (showLoading) {
        loading.value = true
        errorMessage.value = ''
    }

    try {
        const [profileResult, result] = await Promise.all([
            showLoading ? getProfile() : Promise.resolve(null),
            getFines(1, 50),
        ])

        if (requestId !== finesRequestId) {
            return
        }

        if (profileResult) {
            profile.value = profileResult
        }
        errorMessage.value = ''
        fines.value = result.items
        fineSummary.value = result.summary ?? null
    } catch (error: unknown) {
        if (requestId !== finesRequestId) {
            return
        }

        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Data denda gagal dimuat.'
    } finally {
        if (requestId === finesRequestId) {
            loading.value = false
        }
    }
}

usePageRefresh(() => loadFines(false), 30_000)
onMounted(() => loadFines())
</script>

<template>
    <MemberShell :profile="profile">
        <div class="space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <p class="text-sm font-medium text-slate-500">
                    Keuangan
                </p>
                <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">
                    Denda Saya
                </h1>
                <p class="mt-2 text-sm text-slate-500">
                    Riwayat denda berdasarkan transaksi peminjaman Anda.
                </p>
            </section>

            <div
                v-if="errorMessage"
                class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
            >
                {{ errorMessage }}
            </div>

            <section class="grid gap-4 md:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-slate-500">Total Denda</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">
                        {{ money(totalAmount) }}
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-slate-500">Sudah Dibayar</p>
                    <p class="mt-2 text-2xl font-bold text-emerald-600">
                        {{ money(totalPaid) }}
                    </p>
                </div>

                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                    <p class="text-sm text-amber-700">Belum Lunas</p>
                    <p class="mt-2 text-2xl font-bold text-amber-700">
                        {{ money(outstanding) }}
                    </p>
                </div>
            </section>

            <div
                v-if="loading"
                class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500 shadow-sm"
            >
                Memuat data denda...
            </div>

            <div
                v-else-if="fines.length === 0"
                class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center"
            >
                <p class="font-semibold text-slate-900">
                    Tidak ada denda
                </p>
                <p class="mt-1 text-sm text-slate-500">
                    Akun Anda belum memiliki catatan denda.
                </p>
            </div>

            <div
                v-else
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
            >
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] border-collapse">
                        <thead class="bg-slate-50">
                            <tr class="border-b border-slate-200">
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Buku
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Terlambat
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Denda
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Dibayar
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Sisa
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Status
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="fine in fines"
                                :key="fine.id"
                                class="hover:bg-slate-50"
                            >
                                <td class="px-4 py-3.5">
                                    <p class="font-semibold text-slate-900">
                                        {{ fine.book?.title || '-' }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ fine.is_accruing ? 'Denda berjalan' : dateTime(fine.assessed_at) }}
                                    </p>
                                </td>

                                <td class="px-4 py-3.5 text-sm text-slate-700">
                                    {{ fine.late_units ?? fine.late_days }} {{ fine.late_unit === 'hour' ? 'jam' : 'hari' }}
                                </td>

                                <td class="px-4 py-3.5 text-sm font-semibold text-slate-900">
                                    {{ money(Number(fine.amount || 0)) }}
                                </td>

                                <td class="px-4 py-3.5 text-sm text-slate-700">
                                    {{ money(Number(fine.amount_paid || 0)) }}
                                </td>

                                <td class="px-4 py-3.5 text-sm font-bold text-amber-600">
                                    {{ money(Number(fine.outstanding_amount || 0)) }}
                                </td>

                                <td class="px-4 py-3.5">
                                    <span
                                        class="rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="fine.status === 'PAID'
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : fine.status === 'PARTIAL'
                                                ? 'bg-amber-50 text-amber-700'
                                                : 'bg-rose-50 text-rose-700'"
                                    >
                                        {{ fine.is_accruing ? 'BERJALAN' : fine.status }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </MemberShell>
</template>