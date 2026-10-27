<script setup lang="ts">
import type {
    AcquisitionSource,
    BookCopyCondition,
    BookCopyForm,
} from '~/types/book-copy'

interface LookupBook {
    id: number
    title: string
    isbn?: string | null
}

interface LookupLocation {
    id: number
    code: string
    name: string
}

interface Props {
    initialValue: BookCopyForm
    submitLabel: string
    loading: boolean
    errorMessage?: string
}

const props = defineProps<Props>()

const emit = defineEmits<{
    submit: [value: BookCopyForm]
}>()

const {
    listBooks,
    listLocations,
} = useBookCopies()

const books = ref<LookupBook[]>([])
const locations = ref<LookupLocation[]>([])
const loadingLookups = ref(true)

const form = reactive<BookCopyForm>({
    book_id: props.initialValue.book_id,
    barcode: props.initialValue.barcode,
    inventory_number: props.initialValue.inventory_number,
    location_id: props.initialValue.location_id,
    acquisition_date: props.initialValue.acquisition_date,
    acquisition_source: props.initialValue.acquisition_source,
    acquisition_price: props.initialValue.acquisition_price,
    condition: props.initialValue.condition,
    notes: props.initialValue.notes,
})

const acquisitionSources: Array<{
    label: string
    value: AcquisitionSource
}> = [
    {
        label: 'Pembelian',
        value: 'PURCHASE',
    },
    {
        label: 'Donasi',
        value: 'DONATION',
    },
    {
        label: 'Hibah',
        value: 'GRANT',
    },
    {
        label: 'Hadiah',
        value: 'GIFT',
    },
    {
        label: 'Lainnya',
        value: 'OTHER',
    },
]

const conditions: Array<{
    label: string
    value: BookCopyCondition
}> = [
    {
        label: 'Baru',
        value: 'NEW',
    },
    {
        label: 'Baik',
        value: 'GOOD',
    },
    {
        label: 'Cukup',
        value: 'FAIR',
    },
    {
        label: 'Buruk',
        value: 'POOR',
    },
]

const selectedBook = computed(() =>
    books.value.find(
        (book) => book.id === form.book_id,
    ),
)

const submit = () => {
    if (!form.book_id) {
        return
    }

    if (!form.location_id) {
        return
    }

    if (!form.barcode.trim()) {
        return
    }

    emit('submit', {
        ...form,
        barcode: form.barcode.trim(),
        inventory_number:
            form.inventory_number.trim(),
        notes: form.notes.trim(),
    })
}

const loadLookups = async () => {
    loadingLookups.value = true

    try {
        const [
            bookResult,
            locationResult,
        ] = await Promise.all([
            listBooks(),
            listLocations(),
        ])

        books.value = bookResult
        locations.value = locationResult
    }
    finally {
        loadingLookups.value = false
    }
}

onMounted(loadLookups)
</script>

<template>
    <form
        class="space-y-6"
        @submit.prevent="submit"
    >
        <div
            v-if="errorMessage"
            class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
        >
            {{ errorMessage }}
        </div>

        <section class="rounded-2xl bg-slate-50 p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                Buku
            </p>

            <div class="mt-4">
                <label
                    for="copy-book"
                    class="mb-2 block text-sm font-semibold text-slate-700"
                >
                    Buku <span class="text-rose-600">*</span>
                </label>

                <select
                    id="copy-book"
                    v-model.number="form.book_id"
                    required
                    :disabled="loadingLookups"
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
                    <option :value="null">
                        Pilih buku
                    </option>

                    <option
                        v-for="book in books"
                        :key="book.id"
                        :value="book.id"
                    >
                        {{ book.title }}
                        {{ book.isbn ? ` — ${book.isbn}` : '' }}
                    </option>
                </select>
            </div>

            <div
                v-if="selectedBook"
                class="mt-3 rounded-xl border border-slate-200 bg-white p-4"
            >
                <p class="text-xs uppercase tracking-wide text-slate-500">
                    Buku dipilih
                </p>

                <p class="mt-1 font-bold text-slate-900">
                    {{ selectedBook.title }}
                </p>

                <p class="mt-1 text-sm text-slate-500">
                    ISBN: {{ selectedBook.isbn || '-' }}
                </p>
            </div>
        </section>

        <section>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                Identitas Fisik
            </p>

            <div class="mt-4 grid gap-5 lg:grid-cols-2">
                <div>
                    <label
                        for="copy-barcode"
                        class="mb-2 block text-sm font-semibold text-slate-700"
                    >
                        Barcode <span class="text-rose-600">*</span>
                    </label>

                    <input
                        id="copy-barcode"
                        v-model="form.barcode"
                        type="text"
                        maxlength="100"
                        required
                        placeholder="Contoh: QRH-001"
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 font-mono text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                    >

                    <p class="mt-2 text-xs text-slate-500">
                        Barcode fisik ditempel pada buku oleh petugas.
                    </p>
                </div>

                <div>
                    <label
                        for="copy-inventory"
                        class="mb-2 block text-sm font-semibold text-slate-700"
                    >
                        Nomor Inventaris
                    </label>

                    <input
                        id="copy-inventory"
                        v-model="form.inventory_number"
                        type="text"
                        maxlength="100"
                        placeholder="Contoh: INV/QH/001"
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 font-mono text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                    >
                </div>

                <div>
                    <label
                        for="copy-location"
                        class="mb-2 block text-sm font-semibold text-slate-700"
                    >
                        Lokasi <span class="text-rose-600">*</span>
                    </label>

                    <select
                        id="copy-location"
                        v-model.number="form.location_id"
                        required
                        :disabled="loadingLookups"
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                    >
                        <option :value="null">
                            Pilih lokasi
                        </option>

                        <option
                            v-for="location in locations"
                            :key="location.id"
                            :value="location.id"
                        >
                            {{ location.name }} — {{ location.code }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="copy-condition"
                        class="mb-2 block text-sm font-semibold text-slate-700"
                    >
                        Kondisi
                    </label>

                    <select
                        id="copy-condition"
                        v-model="form.condition"
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                    >
                        <option :value="null">
                            Belum ditentukan
                        </option>

                        <option
                            v-for="condition in conditions"
                            :key="condition.value"
                            :value="condition.value"
                        >
                            {{ condition.label }}
                        </option>
                    </select>
                </div>
            </div>
        </section>

        <section>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                Perolehan
            </p>

            <div class="mt-4 grid gap-5 lg:grid-cols-3">
                <div>
                    <label
                        for="copy-source"
                        class="mb-2 block text-sm font-semibold text-slate-700"
                    >
                        Sumber Perolehan
                    </label>

                    <select
                        id="copy-source"
                        v-model="form.acquisition_source"
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                    >
                        <option
                            v-for="source in acquisitionSources"
                            :key="source.value"
                            :value="source.value"
                        >
                            {{ source.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="copy-date"
                        class="mb-2 block text-sm font-semibold text-slate-700"
                    >
                        Tanggal Perolehan
                    </label>

                    <input
                        id="copy-date"
                        v-model="form.acquisition_date"
                        type="date"
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                    >
                </div>

                <div>
                    <label
                        for="copy-price"
                        class="mb-2 block text-sm font-semibold text-slate-700"
                    >
                        Harga Perolehan
                    </label>

                    <input
                        id="copy-price"
                        v-model.number="form.acquisition_price"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="0"
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                    >
                </div>
            </div>
        </section>

        <section>
            <label
                for="copy-notes"
                class="mb-2 block text-sm font-semibold text-slate-700"
            >
                Catatan
            </label>

            <textarea
                id="copy-notes"
                v-model="form.notes"
                rows="4"
                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
            />
        </section>

        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-sm font-semibold text-emerald-800">
                Status awal
            </p>

            <p class="mt-1 text-sm text-emerald-700">
                Copy baru akan masuk sebagai <strong>AVAILABLE</strong>.
                Status tidak diubah dari form ini.
            </p>
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end">
            <NuxtLink
                to="/staff/book-copies"
                class="rounded-xl border border-slate-200 px-5 py-3 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
                Batal
            </NuxtLink>

            <button
                type="submit"
                class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
                :disabled="
                    loading ||
                    loadingLookups ||
                    !form.book_id ||
                    !form.location_id ||
                    !form.barcode.trim()
                "
            >
                {{ loading ? 'Menyimpan...' : submitLabel }}
            </button>
        </div>
    </form>
</template>