<script setup lang="ts">
import type {
    BookCopy,
    BookCopyForm,
} from '~/types/book-copy'

definePageMeta({
    middleware: 'staff',
})

const route = useRoute()

const authStore = useAuthStore()

const {
    getCopy,
    updateCopy,
} = useBookCopies()

const id = computed(() =>
    Number(route.params.id),
)

const copy = ref<BookCopy | null>(null)
const loading = ref(true)
const saving = ref(false)

const errorMessage = ref('')
const successMessage = ref('')

const canUpdate = computed(() =>
    authStore.hasPermission('book_copies.update') ||
    authStore.hasRole('SUPER_ADMIN'),
)

const form = ref<BookCopyForm>({
    book_id: null,
    barcode: '',
    inventory_number: '',
    location_id: null,
    acquisition_date: '',
    acquisition_source: 'PURCHASE',
    acquisition_price: null,
    condition: null,
    notes: '',
})

const toForm = (
    value: BookCopy,
): BookCopyForm => ({
    book_id: value.book?.id ?? null,
    barcode: value.barcode,
    inventory_number: value.inventory_number || '',
    location_id: value.location?.id ?? null,
    acquisition_date: value.acquisition_date || '',
    acquisition_source:
        value.acquisition_source || 'PURCHASE',
    acquisition_price:
        value.acquisition_price === null ||
        value.acquisition_price === undefined
            ? null
            : Number(value.acquisition_price),
    condition: value.condition,
    notes: value.notes || '',
})

const loadCopy = async () => {
    loading.value = true
    errorMessage.value = ''

    try {
        const result = await getCopy(id.value)

        copy.value = result
        form.value = toForm(result)
    }
    catch (error: unknown) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Detail copy gagal dimuat.'
    }
    finally {
        loading.value = false
    }
}

const saveCopy = async (
    payload: BookCopyForm,
) => {
    if (!canUpdate.value) {
        return
    }

    saving.value = true
    errorMessage.value = ''
    successMessage.value = ''

    try {
        const result = await updateCopy(
            id.value,
            payload,
        )

        copy.value = result
        form.value = toForm(result)

        successMessage.value =
            'Copy buku berhasil diperbarui.'
    }
    catch (error: unknown) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Copy buku gagal diperbarui.'
    }
    finally {
        saving.value = false
    }
}

onMounted(loadCopy)
</script>

<template>
    <StaffShell active="book-copies">
        <div class="mx-auto max-w-5xl space-y-6">

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">
                            Inventaris Fisik
                        </p>

                        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">
                            {{ copy?.barcode || 'Detail Copy' }}
                        </h1>

                        <p class="mt-2 text-sm text-slate-500">
                            {{
                                copy?.book?.title ||
                                'Memuat informasi copy buku...'
                            }}
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
                v-if="errorMessage"
                class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
            >
                {{ errorMessage }}
            </div>

            <div
                v-if="successMessage"
                class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700"
            >
                {{ successMessage }}
            </div>

            <section
                v-if="copy"
                class="grid gap-4 md:grid-cols-4"
            >
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-slate-500">
                        Status
                    </p>

                    <p class="mt-2 text-lg font-bold text-slate-900">
                        {{ copy.status }}
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-slate-500">
                        Kondisi
                    </p>

                    <p class="mt-2 text-lg font-bold text-slate-900">
                        {{ copy.condition || '-' }}
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-slate-500">
                        Inventaris
                    </p>

                    <p class="mt-2 text-lg font-bold text-slate-900">
                        {{ copy.inventory_number || '-' }}
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-slate-500">
                        Lokasi
                    </p>

                    <p class="mt-2 text-lg font-bold text-slate-900">
                        {{ copy.location?.name || '-' }}
                    </p>
                </div>
            </section>

            <section
                v-if="copy && canUpdate"
                class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"
            >
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-slate-900">
                        Edit Copy Buku
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Status tidak diedit di form karena perubahan status
                        ditangani oleh proses domain peminjaman/pengembalian.
                    </p>
                </div>

                <StaffBookCopyForm
                    :initial-value="form"
                    submit-label="Simpan Perubahan"
                    :loading="saving"
                    :error-message="errorMessage"
                    @submit="saveCopy"
                />
            </section>

            <section
                v-else-if="copy"
                class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
            >
                <h2 class="text-xl font-bold text-slate-900">
                    Informasi Copy
                </h2>

                <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">
                            Barcode
                        </dt>

                        <dd class="mt-1 font-mono font-semibold text-slate-900">
                            {{ copy.barcode }}
                        </dd>

                        <div class="mt-3">
                            <NuxtLink
                                :to="`/staff/book-copies/barcode/${copy.id}`"
                                class="inline-flex rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800"
                            >
                                Cetak Barcode
                            </NuxtLink>
                        </div>
                    </div>

                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">
                            Buku
                        </dt>

                        <dd class="mt-1 font-semibold text-slate-900">
                            {{ copy.book?.title || '-' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">
                            Sumber
                        </dt>

                        <dd class="mt-1 font-semibold text-slate-900">
                            {{ copy.acquisition_source || '-' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">
                            Harga
                        </dt>

                        <dd class="mt-1 font-semibold text-slate-900">
                            {{ copy.acquisition_price ?? '-' }}
                        </dd>
                    </div>
                </dl>
            </section>

        </div>
    </StaffShell>
</template>