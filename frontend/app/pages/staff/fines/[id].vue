<script setup lang="ts">
import { useFinesService, type StaffFine, type FinePaymentPayload } from '~/services/fines'

definePageMeta({ middleware: 'staff' })

const route = useRoute()
const finesService = useFinesService()
const authStore = useAuthStore()

const fineId = computed(() => Number(route.params.id))
const fine = ref<StaffFine | null>(null)
const loading = ref(true)
const errorMessage = ref('')
const successMessage = ref('')

const canPay = computed(() => authStore.hasPermission('fines.payment') || authStore.hasRole('SUPER_ADMIN'))

// Payment form
const showPayForm = ref(false)
const payAmount = ref<number | null>(null)
const payMethod = ref<'CASH' | 'TRANSFER' | 'OTHER'>('CASH')
const payReference = ref('')
const payNotes = ref('')
const paying = ref(false)
type PaymentIntent = { idempotencyKey: string; payload: FinePaymentPayload }
const paymentIntent = ref<PaymentIntent | null>(null)
const intentStorageError = ref('')

const intentStorageKey = computed(() => `fine-payment-intent:${fineId.value}`)

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null

const getPaymentErrorMessage = (error: unknown): string => {
    const fallbackMessage = error instanceof Error ? error.message : 'Gagal mencatat pembayaran.'
    if (!isRecord(error)) return fallbackMessage

    const response = isRecord(error.response) ? error.response : null
    const statusCode = Number(error.statusCode ?? error.status ?? response?.status)
    const responseData = isRecord(error.data)
        ? error.data
        : response && isRecord(response._data)
            ? response._data
            : null
    const backendMessage = typeof responseData?.message === 'string' && responseData.message.trim()
        ? responseData.message.trim()
        : null

    if (statusCode === 409 && backendMessage) {
        return `Konflik pembayaran: ${backendMessage}`
    }

    if (statusCode === 422) {
        const validationErrors = isRecord(responseData?.errors) ? responseData.errors : null
        const details = validationErrors
            ? Object.entries(validationErrors).flatMap(([field, value]) => {
                const messages = Array.isArray(value)
                    ? value.filter((message): message is string => typeof message === 'string')
                    : typeof value === 'string'
                        ? [value]
                        : []

                return messages.map(message => `${field}: ${message}`)
            })
            : []
        const message = backendMessage
            ? `Validasi pembayaran: ${backendMessage}`
            : fallbackMessage

        return details.length > 0 ? `${message}\n${details.join('\n')}` : message
    }

    return fallbackMessage
}

const isPaymentIntent = (value: unknown): value is PaymentIntent => {
    if (typeof value !== 'object' || value === null) return false
    if (!('idempotencyKey' in value) || typeof value.idempotencyKey !== 'string') return false
    if (!/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(value.idempotencyKey)) return false
    if (!('payload' in value) || typeof value.payload !== 'object' || value.payload === null) return false
    if (!('amount' in value.payload) || typeof value.payload.amount !== 'number' || !Number.isFinite(value.payload.amount)) return false
    if (!('payment_method' in value.payload) || !['CASH', 'TRANSFER', 'OTHER'].includes(String(value.payload.payment_method))) return false

    return (!('reference_number' in value.payload) || typeof value.payload.reference_number === 'string')
        && (!('notes' in value.payload) || typeof value.payload.notes === 'string')
        && (!('paid_at' in value.payload) || typeof value.payload.paid_at === 'string')
}

const money = (v: string | number | null) =>
    v === null ? '-' : new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v))

const formatDate = (d: string | null) => {
    if (!d) return '-'
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const statusClass = (status: string) => {
    switch (status) {
        case 'PAID': return 'bg-emerald-100 text-emerald-800'
        case 'PARTIAL': return 'bg-amber-100 text-amber-800'
        case 'UNPAID': return 'bg-rose-100 text-rose-800'
        default: return 'bg-slate-100 text-slate-700'
    }
}

const statusLabel = (status: string) => {
    switch (status) {
        case 'PAID': return 'Lunas'
        case 'PARTIAL': return 'Dibayar Sebagian'
        case 'UNPAID': return 'Belum Dibayar'
        default: return status
    }
}

const load = async () => {
    loading.value = true
    errorMessage.value = ''
    try {
        const result = await finesService.getFine(fineId.value)
        fine.value = result.data as StaffFine
    } catch (e: unknown) {
        errorMessage.value = e instanceof Error ? e.message : 'Gagal memuat detail denda.'
    } finally {
        loading.value = false
    }
}

const restorePaymentIntent = () => {
    try {
        const storedIntent = window.sessionStorage.getItem(intentStorageKey.value)
        if (!storedIntent) return

        const parsed: unknown = JSON.parse(storedIntent)
        if (!isPaymentIntent(parsed)) {
            intentStorageError.value = 'Intent pembayaran tersimpan tidak valid. Jangan membuat pembayaran baru sebelum memeriksa status pembayaran ini.'
            return
        }

        paymentIntent.value = parsed
        payAmount.value = parsed.payload.amount
        payMethod.value = parsed.payload.payment_method
        payReference.value = parsed.payload.reference_number || ''
        payNotes.value = parsed.payload.notes || ''
        showPayForm.value = true
    } catch (error: unknown) {
        intentStorageError.value = error instanceof Error
            ? `Intent pembayaran tidak dapat dipulihkan: ${error.message}`
            : 'Intent pembayaran tidak dapat dipulihkan.'
    }
}

const persistPaymentIntent = (intent: PaymentIntent): boolean => {
    try {
        window.sessionStorage.setItem(intentStorageKey.value, JSON.stringify(intent))
        return true
    } catch (error: unknown) {
        intentStorageError.value = error instanceof Error
            ? `Intent pembayaran tidak dapat disimpan: ${error.message}`
            : 'Intent pembayaran tidak dapat disimpan.'
        return false
    }
}

const clearPaymentIntent = (): boolean => {
    try {
        window.sessionStorage.removeItem(intentStorageKey.value)
        paymentIntent.value = null
        return true
    } catch (error: unknown) {
        intentStorageError.value = error instanceof Error
            ? `Intent pembayaran belum dapat dibersihkan: ${error.message}`
            : 'Intent pembayaran belum dapat dibersihkan.'
        return false
    }
}

const generateCryptoUUID = (): string => {
    if (typeof window !== 'undefined' && typeof window.crypto?.randomUUID === 'function') {
        return window.crypto.randomUUID()
    }

    if (typeof window !== 'undefined' && typeof window.crypto?.getRandomValues === 'function') {
        const bytes = new Uint8Array(16)
        window.crypto.getRandomValues(bytes)

        const byte6 = bytes[6] ?? 0
        const byte8 = bytes[8] ?? 0
        bytes[6] = (byte6 & 0x0f) | 0x40
        bytes[8] = (byte8 & 0x3f) | 0x80

        const hex = Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('')
        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`
    }

    throw new Error('Sumber acak kriptografis (Web Crypto API) tidak tersedia di lingkungan browser ini.')
}

const submitPayment = async () => {
    if (paying.value || intentStorageError.value) return

    let intent = paymentIntent.value
    if (intent) {
        const confirmed = window.confirm('Kirim ulang permintaan pembayaran yang sama menggunakan kunci idempotensi yang sama?')
        if (!confirmed) return
    } else {
        if (!payAmount.value || payAmount.value <= 0) {
            errorMessage.value = 'Nominal pembayaran harus lebih dari 0.'
            return
        }

        const outstanding = Number(fine.value?.outstanding_amount || 0)
        if (payAmount.value > outstanding) {
            errorMessage.value = `Nominal tidak boleh melebihi sisa tagihan (${money(outstanding)}).`
            return
        }

        const confirmed = window.confirm(
            `Catat pembayaran sebesar ${money(payAmount.value)} via ${payMethod.value}?`
        )
        if (!confirmed) return
    }

    paying.value = true
    errorMessage.value = ''
    successMessage.value = ''

    try {
        if (!intent) {
            const payload: FinePaymentPayload = {
                amount: payAmount.value!,
                payment_method: payMethod.value,
            }
            if (payReference.value.trim()) payload.reference_number = payReference.value.trim()
            if (payNotes.value.trim()) payload.notes = payNotes.value.trim()

            const idempotencyKey = generateCryptoUUID()
            intent = { idempotencyKey, payload }
            if (!persistPaymentIntent(intent)) {
                throw new Error(intentStorageError.value || 'Gagal menyimpan intent pembayaran.')
            }
            paymentIntent.value = intent
        }

        await finesService.payFine(fineId.value, intent.payload, intent.idempotencyKey)

        if (!clearPaymentIntent()) {
            throw new Error('Pembayaran berhasil, tetapi intent belum dapat dibersihkan. Gunakan retry dengan kunci yang sama untuk memastikan hasilnya.')
        }
        successMessage.value = 'Pembayaran berhasil dicatat.'
        showPayForm.value = false
        payAmount.value = null
        payReference.value = ''
        payNotes.value = ''
        payMethod.value = 'CASH'

        await load()
    } catch (e: unknown) {
        errorMessage.value = getPaymentErrorMessage(e)
        const statusCode = typeof e === 'object' && e !== null && 'statusCode' in e
            ? Number(e.statusCode)
            : typeof e === 'object' && e !== null && 'status' in e
                ? Number(e.status)
                : null

        if (statusCode === 422) {
            clearPaymentIntent()
        }
    } finally {
        paying.value = false
    }
}

onMounted(() => {
    restorePaymentIntent()
    void load()
})
</script>

<template>
    <StaffShell active="fines">
        <div class="space-y-6">
            <!-- Header -->
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">
                            <NuxtLink to="/staff/fines" class="hover:underline">Denda</NuxtLink>
                            &rsaquo; Detail
                        </p>
                        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">
                            Detail Denda #{{ fineId }}
                        </h1>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <NuxtLink
                            to="/staff/fines"
                            class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-200"
                        >
                            Kembali
                        </NuxtLink>

                        <button
                            v-if="fine && canPay && (fine.status !== 'PAID' || paymentIntent)"
                            type="button"
                            class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700"
                            @click="showPayForm = !showPayForm; errorMessage = ''"
                        >
                            {{ showPayForm ? 'Tutup Form' : 'Catat Pembayaran' }}
                        </button>
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
            <div v-if="intentStorageError" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                {{ intentStorageError }}
            </div>

            <!-- Loading -->
            <div v-if="loading" class="space-y-4">
                <div v-for="i in 3" :key="i" class="h-32 animate-pulse rounded-2xl bg-slate-100" />
            </div>

            <template v-else-if="fine">
                <!-- Payment Form -->
                <section v-if="showPayForm && (fine.status !== 'PAID' || paymentIntent) && canPay" class="rounded-2xl border border-amber-200 bg-amber-50 p-6">
                    <h2 class="mb-4 text-base font-bold text-slate-900">Catat Pembayaran Denda</h2>
                    <p v-if="paymentIntent" class="mb-4 text-sm text-amber-900">
                        Permintaan sebelumnya belum terkonfirmasi. Retry akan memakai nominal, detail, dan kunci yang sama.
                    </p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">
                                Nominal Bayar *
                                <span class="font-normal text-slate-400">(sisa: {{ money(fine.outstanding_amount) }})</span>
                            </label>
                            <input
                                v-model.number="payAmount"
                                type="number"
                                min="1"
                                step="0.01"
                                :max="Number(fine.outstanding_amount)"
                                :disabled="paying || Boolean(paymentIntent)"
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                                placeholder="Contoh: 2000"
                            >
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">Metode Pembayaran *</label>
                            <select
                                v-model="payMethod"
                                :disabled="paying || Boolean(paymentIntent)"
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            >
                                <option value="CASH">CASH (Tunai)</option>
                                <option value="TRANSFER">TRANSFER</option>
                                <option value="OTHER">OTHER (Lainnya)</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">Nomor Referensi (opsional)</label>
                            <input
                                v-model="payReference"
                                type="text"
                                maxlength="100"
                                :disabled="paying || Boolean(paymentIntent)"
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                                placeholder="No. bukti transfer, kwitansi, dll."
                            >
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">Catatan (opsional)</label>
                            <input
                                v-model="payNotes"
                                type="text"
                                :disabled="paying || Boolean(paymentIntent)"
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                                placeholder="Catatan tambahan..."
                            >
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <button
                            type="button"
                            :disabled="paying || Boolean(intentStorageError)"
                            class="rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50 hover:bg-emerald-700"
                            @click="submitPayment"
                        >
                            {{ paying ? 'Menyimpan...' : paymentIntent ? 'Retry Pembayaran' : 'Simpan Pembayaran' }}
                        </button>
                    </div>
                </section>

                <!-- Fine Summary -->
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Denda</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900">{{ money(fine.amount) }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sudah Dibayar</p>
                        <p class="mt-2 text-2xl font-bold text-emerald-600">{{ money(fine.amount_paid) }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sisa Tagihan</p>
                        <p class="mt-2 text-2xl font-bold text-rose-600">{{ money(fine.outstanding_amount) }}</p>
                    </div>
                </div>

                <!-- Fine Detail Card -->
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-5 flex items-center justify-between">
                        <h2 class="text-base font-bold text-slate-900">Informasi Denda</h2>
                        <span :class="['rounded-full px-3 py-1 text-xs font-semibold', statusClass(fine.status)]">
                            {{ statusLabel(fine.status) }}
                        </span>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Anggota</p>
                            <p class="mt-1 font-semibold text-slate-900">{{ fine.member?.name || '-' }}</p>
                            <p class="text-sm text-slate-500">{{ fine.member?.member_number || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Buku</p>
                            <p class="mt-1 font-semibold text-slate-900">{{ fine.book?.title || '-' }}</p>
                            <p class="text-sm text-slate-500">ISBN: {{ fine.book?.isbn || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Nomor Pinjaman</p>
                            <p class="mt-1 font-semibold text-slate-900">{{ fine.loan?.loan_number || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Keterlambatan</p>
                            <p class="mt-1 font-semibold text-slate-900">{{ fine.late_days }} hari</p>
                            <p class="text-sm text-slate-500">{{ money(fine.rate_per_day) }} / hari</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tanggal Denda</p>
                            <p class="mt-1 font-semibold text-slate-900">{{ formatDate(fine.assessed_at) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tanggal Lunas</p>
                            <p class="mt-1 font-semibold text-slate-900">{{ fine.paid_at ? formatDate(fine.paid_at) : '-' }}</p>
                        </div>
                        <div v-if="fine.notes">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Catatan</p>
                            <p class="mt-1 text-slate-700">{{ fine.notes }}</p>
                        </div>
                    </div>
                </section>

                <!-- Payment History -->
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="mb-4 text-base font-bold text-slate-900">Riwayat Pembayaran</h2>

                    <div v-if="!fine.payment_history || fine.payment_history.length === 0" class="rounded-xl border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500">
                        Belum ada riwayat pembayaran.
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="payment in fine.payment_history"
                            :key="payment.id"
                            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3"
                        >
                            <div>
                                <p class="font-semibold text-slate-900">{{ money(payment.amount) }}</p>
                                <p class="text-xs text-slate-500">{{ payment.payment_method }} &bull; {{ payment.received_by?.name || '-' }}</p>
                                <p v-if="payment.reference_number" class="text-xs text-slate-500">Ref: {{ payment.reference_number }}</p>
                                <p v-if="payment.notes" class="text-xs text-slate-500">{{ payment.notes }}</p>
                            </div>
                            <p class="text-sm text-slate-500">{{ formatDate(payment.paid_at) }}</p>
                        </div>
                    </div>
                </section>
            </template>
        </div>
    </StaffShell>
</template>
