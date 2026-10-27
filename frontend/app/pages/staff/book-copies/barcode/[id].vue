<script setup lang="ts">
import BookCopyBarcodeLabel from '~/components/staff/BookCopyBarcodeLabel.vue'
import type {
    BookCopy,
} from '~/types/book-copy'

definePageMeta({
    middleware: 'staff',
})

const route = useRoute()

const {
    getCopy,
} = useBookCopies()

const copy = ref<BookCopy | null>(null)
const loading = ref(true)
const errorMessage = ref('')

const copyId = computed(() =>
    Number(route.params.id),
)

onMounted(async () => {
    if (!Number.isInteger(copyId.value) || copyId.value <= 0) {
        errorMessage.value = 'ID copy tidak valid.'
        loading.value = false
        return
    }

    try {
        copy.value = await getCopy(
            copyId.value,
        )
    }
    catch (error: unknown) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Data copy gagal dimuat.'
    }
    finally {
        loading.value = false
    }
})
</script>

<template>
    <StaffShell active="book-copies">
        <div class="mx-auto max-w-3xl space-y-6">

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">
                            Identitas Fisik
                        </p>

                        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">
                            Barcode Copy Buku
                        </h1>

                        <p class="mt-2 text-sm text-slate-500">
                            Barcode dapat dicetak dan ditempel pada buku fisik.
                        </p>
                    </div>

                    <NuxtLink
                        to="/staff/book-copies"
                        class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        ← Kembali
                    </NuxtLink>
                </div>
            </section>

            <div
                v-if="loading"
                class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500"
            >
                Memuat barcode...
            </div>

            <div
                v-else-if="errorMessage"
                class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-700"
            >
                {{ errorMessage }}
            </div>

            <section
                v-else-if="copy"
                class="space-y-6"
            >
                <BookCopyBarcodeLabel
                    :barcode="copy.barcode"
                    :title="copy.book?.title ?? ''"
                    :inventory-number="copy.inventory_number"
                />

                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Informasi Copy
                    </p>

                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-slate-500">
                                Barcode
                            </dt>

                            <dd class="mt-1 font-mono font-semibold text-slate-900">
                                {{ copy.barcode }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs text-slate-500">
                                Inventaris
                            </dt>

                            <dd class="mt-1 font-mono text-slate-900">
                                {{ copy.inventory_number || '-' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs text-slate-500">
                                Buku
                            </dt>

                            <dd class="mt-1 font-semibold text-slate-900">
                                {{ copy.book?.title || '-' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs text-slate-500">
                                Status
                            </dt>

                            <dd class="mt-1 font-semibold text-emerald-700">
                                {{ copy.status }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <NuxtLink
                    to="/staff/book-copies/scan"
                    class="block rounded-2xl border border-slate-200 bg-white p-5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Buka Scanner Barcode
                </NuxtLink>
            </section>

        </div>
    </StaffShell>
</template>