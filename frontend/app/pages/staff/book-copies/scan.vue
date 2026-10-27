<script setup lang="ts">
definePageMeta({
    middleware: 'staff',
})



const scannerElementId = 'book-copy-barcode-scanner'

const scanning = ref(false)
const loading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const manualBarcode = ref('')

let scanner: {
    stop: () => Promise<void>
    clear: () => void
} | null = null

const cleanupScanner = async () => {
    if (!scanner) {
        return
    }

    try {
        await scanner.stop()
        scanner.clear()
    }
    catch {
        // Scanner may already be stopped.
    }

    scanner = null
    scanning.value = false
}

const findBarcode = async (
    barcode: string,
) => {
    const normalized =
        barcode.trim()

    if (!normalized) {
        errorMessage.value =
            'Barcode wajib diisi.'
        return
    }

    loading.value = true
    errorMessage.value = ''
    successMessage.value = ''

    try {
        const { apiFetch } = useApi()
        const authStore = useAuthStore()

        const response =
            await apiFetch<{
                id: number
                barcode: string
            }>(
                `/book-copies/barcode/${encodeURIComponent(normalized)}`,
                {
                    method: 'GET',
                    headers: {
                        Accept: 'application/json',
                        Authorization:
                            authStore.token
                                ? `Bearer ${authStore.token}`
                                : '',
                    },
                },
            )

        const id = response.data.id

        successMessage.value =
            `Barcode ${normalized} ditemukan.`

        await navigateTo(
            `/staff/book-copies/barcode/${id}`,
        )
    }
    catch (error: unknown) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : `Barcode ${normalized} tidak ditemukan.`
    }
    finally {
        loading.value = false
    }
}

const startScanner = async () => {
    errorMessage.value = ''
    successMessage.value = ''

    try {
        const {
            Html5Qrcode,
        } = await import(
            'html5-qrcode'
        )

        await cleanupScanner()

        const instance =
            new Html5Qrcode(
                scannerElementId,
            )

        scanner = instance

        await instance.start(
            {
                facingMode: 'environment',
            },
            {
                fps: 10,
                qrbox: {
                    width: 280,
                    height: 140,
                },
            },
            async (
                decodedText,
            ) => {
                await cleanupScanner()
                await findBarcode(
                    decodedText,
                )
            },
            () => {
                // Ignore intermediate scan failures.
            },
        )

        scanning.value = true
    }
    catch (error: unknown) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Kamera tidak dapat digunakan.'
    }
}

onMounted(() => {
    startScanner()
})

onBeforeUnmount(
    cleanupScanner,
)
</script>

<template>
    <StaffShell active="book-copies">
        <div class="mx-auto max-w-3xl space-y-6">

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-500">
                            Operasional
                        </p>

                        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">
                            Scan Barcode Buku
                        </h1>

                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            Arahkan kamera ke barcode copy buku untuk membuka data
                            copy fisik tersebut.
                        </p>
                    </div>

                    <NuxtLink
                        to="/staff/book-copies"
                        class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Kembali
                    </NuxtLink>
                </div>
            </section>

            <div
                v-if="successMessage"
                class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700"
            >
                {{ successMessage }}
            </div>

            <div
                v-if="errorMessage"
                class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
            >
                {{ errorMessage }}
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div
                    :id="scannerElementId"
                    class="overflow-hidden rounded-2xl bg-slate-950"
                />

                <button
                    v-if="!scanning"
                    type="button"
                    class="mt-4 w-full rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800"
                    @click="startScanner"
                >
                    Buka Kamera & Scan
                </button>

                <button
                    v-else
                    type="button"
                    class="mt-4 w-full rounded-xl border border-rose-200 px-5 py-3 text-sm font-semibold text-rose-700 hover:bg-rose-50"
                    @click="cleanupScanner"
                >
                    Hentikan Scanner
                </button>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-semibold text-slate-900">
                    Input Barcode Manual
                </p>

                <p class="mt-1 text-sm text-slate-500">
                    Bisa digunakan bila kamera tidak tersedia.
                </p>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                    <input
                        v-model="manualBarcode"
                        type="text"
                        placeholder="Contoh: QRH-01"
                        class="min-w-0 flex-1 rounded-xl border border-slate-300 px-4 py-3 font-mono text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                        @keyup.enter="findBarcode(manualBarcode)"
                    >

                    <button
                        type="button"
                        class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
                        :disabled="loading"
                        @click="findBarcode(manualBarcode)"
                    >
                        {{ loading ? 'Mencari...' : 'Cari Barcode' }}
                    </button>
                </div>
            </section>

        </div>
    </StaffShell>
</template>