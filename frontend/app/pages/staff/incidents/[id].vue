<script setup lang="ts">
import { useIncidentsService, type StaffIncident } from '~/services/incidents'

definePageMeta({ middleware: 'staff' })

const route = useRoute()
const incidentsService = useIncidentsService()
const authStore = useAuthStore()

const incidentId = computed(() => Number(route.params.id))
const incident = ref<StaffIncident | null>(null)
const loading = ref(true)
const errorMessage = ref('')
const successMessage = ref('')

const canManage = computed(() =>
    authStore.hasPermission('book_incidents.resolve') || authStore.hasRole('SUPER_ADMIN')
)

// Resolve payment
const showPayResolve = ref(false)
const resolvePayNotes = ref('')
const resolvingPay = ref(false)

// Resolve replacement
const showReplaceResolve = ref(false)
const replacementCopyId = ref<number | null>(null)
const resolveReplaceNotes = ref('')
const resolvingReplace = ref(false)

const money = (v: string | number | null) =>
    v === null ? '-' : new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v))

const formatDate = (d: string | null) => {
    if (!d) return '-'
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const typeClass = (t: string) =>
    t === 'LOST' ? 'bg-rose-100 text-rose-800' : 'bg-orange-100 text-orange-800'

const statusClass = (s: string) =>
    s === 'OPEN' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'

const load = async () => {
    loading.value = true
    errorMessage.value = ''
    try {
        const result = await incidentsService.getIncident(incidentId.value)
        incident.value = result.data as StaffIncident
    } catch (e: unknown) {
        errorMessage.value = e instanceof Error ? e.message : 'Gagal memuat detail insiden.'
    } finally {
        loading.value = false
    }
}

const submitResolvePayment = async () => {
    const confirmed = window.confirm(
        `Tandai insiden ini sebagai SELESAI dengan metode Bayar Ganti Rugi (${money(incident.value?.amount_due ?? null)})?`
    )
    if (!confirmed) return

    resolvingPay.value = true
    errorMessage.value = ''
    successMessage.value = ''

    try {
        await incidentsService.resolveByPayment(incidentId.value, {
            notes: resolvePayNotes.value.trim() || undefined,
        })
        successMessage.value = 'Insiden berhasil diselesaikan dengan pembayaran ganti rugi.'
        showPayResolve.value = false
        resolvePayNotes.value = ''
        await load()
    } catch (e: unknown) {
        errorMessage.value = e instanceof Error ? e.message : 'Gagal menyelesaikan insiden.'
    } finally {
        resolvingPay.value = false
    }
}

const submitResolveReplacement = async () => {
    if (!replacementCopyId.value) {
        errorMessage.value = 'Masukkan ID Copy Buku pengganti.'
        return
    }

    const confirmed = window.confirm(
        `Tandai insiden ini selesai dengan Buku Pengganti (ID Copy: ${replacementCopyId.value})?`
    )
    if (!confirmed) return

    resolvingReplace.value = true
    errorMessage.value = ''
    successMessage.value = ''

    try {
        await incidentsService.resolveByReplacement(incidentId.value, {
            replacement_book_copy_id: replacementCopyId.value,
            notes: resolveReplaceNotes.value.trim() || undefined,
        })
        successMessage.value = 'Insiden berhasil diselesaikan dengan buku pengganti.'
        showReplaceResolve.value = false
        replacementCopyId.value = null
        resolveReplaceNotes.value = ''
        await load()
    } catch (e: unknown) {
        errorMessage.value = e instanceof Error ? e.message : 'Gagal menyelesaikan insiden dengan penggantian.'
    } finally {
        resolvingReplace.value = false
    }
}

onMounted(load)
</script>

<template>
    <StaffShell active="incidents">
        <div class="space-y-6">
            <!-- Header -->
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">
                            <NuxtLink to="/staff/incidents" class="hover:underline">Insiden Buku</NuxtLink>
                            &rsaquo; Detail
                        </p>
                        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">
                            Detail Insiden #{{ incidentId }}
                        </h1>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <NuxtLink
                            to="/staff/incidents"
                            class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-200"
                        >
                            Kembali
                        </NuxtLink>

                        <div
                            v-if="incident && incident.status === 'OPEN' && canManage"
                            class="flex flex-wrap gap-3"
                        >
                            <button
                                type="button"
                                class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700"
                                @click="showPayResolve = !showPayResolve; showReplaceResolve = false; errorMessage = ''"
                            >
                                {{ showPayResolve ? 'Tutup' : 'Selesai: Bayar Ganti Rugi' }}
                            </button>

                            <button
                                type="button"
                                class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700"
                                @click="showReplaceResolve = !showReplaceResolve; showPayResolve = false; errorMessage = ''"
                            >
                                {{ showReplaceResolve ? 'Tutup' : 'Selesai: Ganti Buku' }}
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Messages -->
            <div v-if="successMessage" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">
                {{ successMessage }}
            </div>
            <div v-if="errorMessage" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                {{ errorMessage }}
            </div>

            <!-- Loading -->
            <div v-if="loading" class="space-y-4">
                <div v-for="i in 3" :key="i" class="h-36 animate-pulse rounded-2xl bg-slate-100" />
            </div>

            <template v-else-if="incident">
                <!-- Resolve via Payment Form -->
                <section v-if="showPayResolve" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6">
                    <h2 class="mb-3 text-base font-bold text-slate-900">Penyelesaian: Bayar Ganti Rugi</h2>
                    <p class="mb-4 text-sm text-slate-600">
                        Anggota membayar ganti rugi sebesar <strong>{{ money(incident.amount_due) }}</strong>.
                        Tindakan ini tidak dapat dibatalkan.
                    </p>
                    <div class="mb-4">
                        <label class="mb-1.5 block text-sm font-semibold text-slate-700">Catatan (opsional)</label>
                        <input
                            v-model="resolvePayNotes"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            placeholder="Catatan tambahan..."
                        >
                    </div>
                    <button
                        type="button"
                        :disabled="resolvingPay"
                        class="rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50 hover:bg-emerald-700"
                        @click="submitResolvePayment"
                    >
                        {{ resolvingPay ? 'Menyimpan...' : 'Konfirmasi Pembayaran Ganti Rugi' }}
                    </button>
                </section>

                <!-- Resolve via Replacement Form -->
                <section v-if="showReplaceResolve" class="rounded-2xl border border-blue-200 bg-blue-50 p-6">
                    <h2 class="mb-3 text-base font-bold text-slate-900">Penyelesaian: Ganti Buku</h2>
                    <p class="mb-4 text-sm text-slate-600">
                        Masukkan ID Copy Buku pengganti (harus berstatus AVAILABLE dan judul sama).
                    </p>
                    <div class="mb-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">ID Copy Buku Pengganti *</label>
                            <input
                                v-model.number="replacementCopyId"
                                type="number"
                                min="1"
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                                placeholder="Contoh: 42"
                            >
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">Catatan (opsional)</label>
                            <input
                                v-model="resolveReplaceNotes"
                                type="text"
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                                placeholder="Catatan tambahan..."
                            >
                        </div>
                    </div>
                    <button
                        type="button"
                        :disabled="resolvingReplace"
                        class="rounded-xl bg-slate-900 px-6 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50 hover:bg-slate-700"
                        @click="submitResolveReplacement"
                    >
                        {{ resolvingReplace ? 'Menyimpan...' : 'Konfirmasi Penggantian Buku' }}
                    </button>
                </section>

                <!-- Summary Cards -->
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Tagihan</p>
                        <p class="mt-2 text-2xl font-bold text-rose-600">{{ money(incident.amount_due) }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sudah Dibayar</p>
                        <p class="mt-2 text-2xl font-bold text-emerald-600">{{ money(incident.amount_paid) }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sisa Tagihan</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900">{{ money(incident.outstanding_amount) }}</p>
                    </div>
                </div>

                <!-- Detail Card -->
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-5 flex flex-wrap items-center gap-3">
                        <h2 class="text-base font-bold text-slate-900">Informasi Insiden</h2>
                        <span :class="['rounded-full px-3 py-1 text-xs font-semibold', typeClass(incident.type)]">
                            {{ incident.type === 'LOST' ? 'HILANG' : 'RUSAK' }}
                        </span>
                        <span :class="['rounded-full px-3 py-1 text-xs font-semibold', statusClass(incident.status)]">
                            {{ incident.status === 'OPEN' ? 'Terbuka' : 'Selesai' }}
                        </span>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Anggota</p>
                            <p class="mt-1 font-semibold text-slate-900">{{ incident.member?.name || '-' }}</p>
                            <p class="text-sm text-slate-500">{{ incident.member?.member_number || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Buku</p>
                            <p class="mt-1 font-semibold text-slate-900">{{ incident.book?.title || '-' }}</p>
                            <p class="text-sm text-slate-500">ISBN: {{ incident.book?.isbn || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Copy Buku (Barcode)</p>
                            <p class="mt-1 font-mono font-semibold text-slate-900">{{ incident.book_copy?.barcode || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Nomor Pinjaman</p>
                            <p class="mt-1 font-semibold text-slate-900">{{ incident.loan?.loan_number || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tanggal Insiden</p>
                            <p class="mt-1 font-semibold text-slate-900">{{ formatDate(incident.opened_at) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tanggal Selesai</p>
                            <p class="mt-1 font-semibold text-slate-900">{{ formatDate(incident.resolved_at) }}</p>
                        </div>
                        <div v-if="incident.resolution_type">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Resolusi</p>
                            <p class="mt-1 font-semibold text-slate-900">
                                {{ incident.resolution_type === 'PAID_MONEY' ? 'Bayar Ganti Rugi' : 'Ganti Buku' }}
                            </p>
                        </div>
                        <div v-if="incident.replacement_book_copy">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Buku Pengganti</p>
                            <p class="mt-1 font-mono font-semibold text-slate-900">{{ incident.replacement_book_copy.barcode }}</p>
                        </div>
                        <div v-if="incident.resolved_by">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Diselesaikan Oleh</p>
                            <p class="mt-1 font-semibold text-slate-900">{{ incident.resolved_by.name }}</p>
                        </div>
                        <div v-if="incident.notes" class="sm:col-span-2 lg:col-span-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Catatan</p>
                            <p class="mt-1 text-slate-700">{{ incident.notes }}</p>
                        </div>
                    </div>
                </section>
            </template>
        </div>
    </StaffShell>
</template>
