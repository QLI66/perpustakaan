<script setup lang="ts">
import type {
    BookIncidentRecord,
    MemberProfile,
} from '~/types/member'

definePageMeta({
    middleware: 'auth',
})

const {
    getProfile,
    getIncidents,
} = useMember()

const profile = ref<MemberProfile | null>(null)
const incidents = ref<BookIncidentRecord[]>([])
const loading = ref(true)
const errorMessage = ref('')
let incidentsRequestId = 0

const dateTime = (value: string | null) => {
    if (!value) return '-'

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value))
}

const statusClass = (status: string) => {
    switch (status) {
        case 'OPEN':
            return 'bg-rose-50 text-rose-700'
        case 'RESOLVED':
            return 'bg-emerald-50 text-emerald-700'
        default:
            return 'bg-slate-100 text-slate-700'
    }
}

const typeLabel = (type: string) => {
    switch (type) {
        case 'LOST':
            return 'Hilang'
        case 'DAMAGED':
            return 'Rusak'
        default:
            return type
    }
}

const loadIncidents = async (showLoading = true) => {
    const requestId = ++incidentsRequestId

    if (showLoading) {
        loading.value = true
        errorMessage.value = ''
    }

    try {
        const [profileResult, result] = await Promise.all([
            getProfile(),
            getIncidents(1, 50),
        ])

        if (requestId !== incidentsRequestId) {
            return
        }

        errorMessage.value = ''
        profile.value = profileResult
        incidents.value = result.items
    } catch (error: unknown) {
        if (requestId !== incidentsRequestId) {
            return
        }

        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Data insiden gagal dimuat.'
    } finally {
        if (requestId === incidentsRequestId) {
            loading.value = false
        }
    }
}

usePageRefresh(() => loadIncidents(false))
onMounted(() => loadIncidents())
</script>

<template>
    <MemberShell :profile="profile">
        <div class="space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <p class="text-sm font-medium text-slate-500">
                    Riwayat Buku
                </p>

                <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">
                    Insiden Buku Saya
                </h1>

                <p class="mt-2 max-w-2xl text-sm text-slate-500">
                    Informasi buku hilang atau rusak yang tercatat pada transaksi Anda
                    dan status penyelesaiannya.
                </p>
            </section>

            <div
                v-if="errorMessage"
                class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
            >
                {{ errorMessage }}
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
                    <div class="mt-4 h-4 w-1/3 rounded bg-slate-200"/>
                    <div class="mt-6 h-20 rounded bg-slate-100"/>
                </div>
            </div>

            <div
                v-else-if="incidents.length === 0"
                class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center"
            >
                <p class="font-semibold text-slate-900">
                    Tidak ada insiden buku
                </p>

                <p class="mt-1 text-sm text-slate-500">
                    Belum ada laporan buku hilang atau rusak pada akun Anda.
                </p>
            </div>

            <div
                v-else
                class="grid gap-4 lg:grid-cols-2"
            >
                <article
                    v-for="incident in incidents"
                    :key="incident.id"
                    class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-lg font-bold text-slate-900">
                                {{ incident.book?.title || 'Buku' }}
                            </p>

                            <p class="mt-1 text-sm text-slate-500">
                                {{
                                    incident.loan?.loan_number
                                        ? `Transaksi ${incident.loan.loan_number}`
                                        : 'Transaksi peminjaman'
                                }}
                            </p>
                        </div>

                        <span
                            class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold"
                            :class="statusClass(incident.status)"
                        >
                            {{ incident.status }}
                        </span>
                    </div>

                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-xs text-slate-500">
                                Jenis Insiden
                            </p>

                            <p class="mt-1 text-sm font-semibold text-slate-900">
                                {{ typeLabel(incident.type) }}
                            </p>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-xs text-slate-500">
                                Barcode
                            </p>

                            <p class="mt-1 text-sm font-semibold text-slate-900">
                                {{ incident.book_copy?.barcode || '-' }}
                            </p>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-xs text-slate-500">
                                Dibuka
                            </p>

                            <p class="mt-1 text-sm font-semibold text-slate-900">
                                {{ dateTime(incident.opened_at) }}
                            </p>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-xs text-slate-500">
                                Diselesaikan
                            </p>

                            <p class="mt-1 text-sm font-semibold text-slate-900">
                                {{ dateTime(incident.resolved_at) }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="incident.resolution_type"
                        class="mt-5 rounded-xl bg-emerald-50 p-4"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">
                            Penyelesaian
                        </p>

                        <p class="mt-1 text-sm font-semibold text-emerald-800">
                            {{ incident.resolution_type }}
                        </p>
                    </div>

                    <p
                        v-if="incident.notes"
                        class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-600"
                    >
                        {{ incident.notes }}
                    </p>
                </article>
            </div>
        </div>
    </MemberShell>
</template>