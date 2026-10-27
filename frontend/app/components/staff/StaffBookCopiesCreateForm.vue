<script setup lang="ts">
import BookCopyBarcodeLabel from '~/components/staff/BookCopyBarcodeLabel.vue'
import type {
    AcquisitionSource,
    BookCopyBatchForm,
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

defineProps<{
    loading: boolean
    errorMessage?: string
}>()

const emit = defineEmits<{
    submit: [value: BookCopyBatchForm]
}>()

const {
    listBooks,
    listLocations,
    createLocation,
} = useBookCopies()

const books = ref<LookupBook[]>([])
const locations = ref<LookupLocation[]>([])

const loadingLookups = ref(true)
const lookupError = ref('')

const selectedBookId = ref<number | null>(null)
const quantity = ref(1)

const acquisitionSource = ref<AcquisitionSource>('PURCHASE')
const acquisitionDate = ref('')
const acquisitionPrice = ref<number | null>(null)
const notes = ref('')

const copies = ref<BookCopyForm[]>([])

const showLocationModal = ref(false)
const locationSaving = ref(false)
const locationError = ref('')
const newLocationCode = ref('')
const newLocationName = ref('')

const barcodePreviewIndex = ref<number | null>(null)

const barcodePreviewCopy = computed(() => {
    if (barcodePreviewIndex.value === null) {
        return null
    }

    return copies.value[barcodePreviewIndex.value] ?? null
})

const openBarcodePreview = (
    index: number,
) => {
    barcodePreviewIndex.value = index
}

const closeBarcodePreview = () => {
    barcodePreviewIndex.value = null

    nextTick(() => {
        const activeElement =
            document.activeElement as HTMLElement | null

        if (activeElement?.blur) {
            activeElement.blur()
        }
    })
}
const handleBarcodePreviewKeydown = (
    event: KeyboardEvent,
) => {
    if (
        event.key === 'Escape' &&
        barcodePreviewIndex.value !== null
    ) {
        event.preventDefault()
        event.stopPropagation()
        closeBarcodePreview()
    }
}

onMounted(() => {
    window.addEventListener(
        'keydown',
        handleBarcodePreviewKeydown,
    )
})

onBeforeUnmount(() => {
    window.removeEventListener(
        'keydown',
        handleBarcodePreviewKeydown,
    )
})

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

const selectedBook = computed(() =>
    books.value.find(
        (book) => book.id === selectedBookId.value,
    ),
)

const selectedBookLabel = computed(() => {
    if (!selectedBook.value) {
        return ''
    }

    return selectedBook.value.isbn
        ? `${selectedBook.value.title} — ${selectedBook.value.isbn}`
        : selectedBook.value.title
})

const normalizeQuantity = (
    value: number,
) => {
    if (!Number.isFinite(value)) {
        return 1
    }

    return Math.max(
        1,
        Math.min(50, Math.floor(value)),
    )
}

const emptyCopy = (): BookCopyForm => ({
    book_id: selectedBookId.value,
    barcode: '',
    inventory_number: '',
    location_id: null,
    acquisition_date: acquisitionDate.value,
    acquisition_source: acquisitionSource.value,
    acquisition_price: acquisitionPrice.value,
    condition: 'GOOD',
    notes: notes.value,
})

const rebuildCopies = () => {
    quantity.value = normalizeQuantity(quantity.value)

    while (copies.value.length < quantity.value) {
        copies.value.push(emptyCopy())
    }

    while (copies.value.length > quantity.value) {
        copies.value.pop()
    }

    syncCommonFields()
}

const syncCommonFields = () => {
    copies.value = copies.value.map((copy) => ({
        ...copy,
        book_id: selectedBookId.value,
        acquisition_date: acquisitionDate.value,
        acquisition_source: acquisitionSource.value,
        acquisition_price: acquisitionPrice.value,
        notes: notes.value,
    }))
}

const onQuantityInput = () => {
    rebuildCopies()
}

const onBookChange = () => {
    copies.value.forEach((copy) => {
        copy.book_id = selectedBookId.value
    })
}

const applyLocationToAll = (
    locationId: number | null,
) => {
    if (!locationId) {
        return
    }

    copies.value.forEach((copy) => {
        copy.location_id = locationId
    })
}

const openLocationModal = () => {
    locationError.value = ''
    newLocationCode.value = ''
    newLocationName.value = ''
    showLocationModal.value = true
}

const closeLocationModal = () => {
    if (locationSaving.value) {
        return
    }

    showLocationModal.value = false
}

const saveLocation = async () => {
    locationSaving.value = true
    locationError.value = ''

    try {
        const location = await createLocation({
            code: newLocationCode.value,
            name: newLocationName.value,
        })

        locations.value = [
            ...locations.value,
            location,
        ].sort((a, b) =>
            a.name.localeCompare(b.name),
        )

        if (copies.value.length > 0) {
            const firstCopy = copies.value[0]

            if (firstCopy) {
                firstCopy.location_id = location.id
            }
        }

        showLocationModal.value = false
    }
    catch (error: unknown) {
        locationError.value =
            error instanceof Error
                ? error.message
                : 'Rak gagal ditambahkan.'
    }
    finally {
        locationSaving.value = false
    }
}

const validationError = computed(() => {
    if (!selectedBookId.value) {
        return 'Buku wajib dipilih.'
    }

    if (copies.value.length !== quantity.value) {
        return 'Jumlah copy belum sesuai.'
    }

    for (let i = 0; i < copies.value.length; i++) {
        const copy = copies.value[i]

        if (!copy) {
            return `Data Copy ${i + 1} tidak tersedia.`
        }

        if (!copy.barcode.trim()) {
            return `Barcode Copy ${i + 1} wajib diisi.`
        }

        if (!copy.location_id) {
            return `Lokasi Copy ${i + 1} wajib dipilih.`
        }
    }

    const barcodeSet = new Set<string>()

    for (let i = 0; i < copies.value.length; i++) {
        const copy = copies.value[i]

        if (!copy) {
            return `Data Copy ${i + 1} tidak tersedia.`
        }

        const barcode = copy.barcode
            .trim()
            .toUpperCase()

        if (barcodeSet.has(barcode)) {
            return `Barcode Copy ${i + 1} duplikat.`
        }

        barcodeSet.add(barcode)
    }

    const inventoryValues = copies.value
        .map((copy) =>
            copy.inventory_number
                .trim()
                .toUpperCase(),
        )
        .filter(Boolean)

    if (
        new Set(inventoryValues).size !==
        inventoryValues.length
    ) {
        return 'Nomor inventaris tidak boleh duplikat.'
    }

    return ''
})

const submit = () => {
    syncCommonFields()

    if (validationError.value) {
        return
    }

    emit('submit', {
        book_id: selectedBookId.value,
        copies: copies.value.map((copy) => ({
            ...copy,
            book_id: selectedBookId.value,
            barcode: copy.barcode.trim(),
            inventory_number:
                copy.inventory_number.trim(),
            notes: copy.notes.trim(),
        })),
    })
}

const loadLookups = async () => {
    loadingLookups.value = true
    lookupError.value = ''

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

        const firstBook = books.value[0]

        if (firstBook) {
            selectedBookId.value = firstBook.id
        }

        rebuildCopies()
    }
    catch (error: unknown) {
        lookupError.value =
            error instanceof Error
                ? error.message
                : 'Data buku atau lokasi gagal dimuat.'
    }
    finally {
        loadingLookups.value = false
    }
}

watch(
    () => selectedBookId.value,
    () => {
        onBookChange()
    },
)

watch(
    () => quantity.value,
    () => {
        quantity.value = normalizeQuantity(
            quantity.value,
        )
    },
)

watch(
    () => [
        acquisitionDate.value,
        acquisitionSource.value,
        acquisitionPrice.value,
        notes.value,
    ],
    () => {
        syncCommonFields()
    },
)

onMounted(loadLookups)
</script>

<template>
    <form
        class="space-y-6"
        @submit.prevent="submit"
    >

        <div
            v-if="errorMessage || lookupError || validationError"
            class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
        >
            {{ errorMessage || lookupError || validationError }}
        </div>

        <!-- BUKU + JUMLAH -->
        <section class="rounded-2xl bg-slate-50 p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                Buku
            </p>

            <div class="mt-4 grid gap-5 lg:grid-cols-[1fr_220px]">
                <div>
                    <label
                        for="batch-book"
                        class="mb-2 block text-sm font-semibold text-slate-700"
                    >
                        Buku <span class="text-rose-600">*</span>
                    </label>

                    <select
                        id="batch-book"
                        v-model.number="selectedBookId"
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
                </div>

                <div>
                    <label
                        for="batch-quantity"
                        class="mb-2 block text-sm font-semibold text-slate-700"
                    >
                        Jumlah Copy <span class="text-rose-600">*</span>
                    </label>

                    <div class="flex overflow-hidden rounded-xl border border-slate-300 bg-white">
                        <button
                            type="button"
                            class="w-12 border-r border-slate-200 text-lg font-semibold text-slate-700 hover:bg-slate-50"
                            @click="quantity = Math.max(1, quantity - 1); rebuildCopies()"
                        >
                            −
                        </button>

                        <input
                            id="batch-quantity"
                            v-model.number="quantity"
                            type="number"
                            min="1"
                            max="50"
                            class="min-w-0 flex-1 px-4 py-3 text-center text-sm font-bold text-slate-900 outline-none"
                            @change="onQuantityInput"
                        >

                        <button
                            type="button"
                            class="w-12 border-l border-slate-200 text-lg font-semibold text-slate-700 hover:bg-slate-50"
                            @click="quantity = Math.min(50, quantity + 1); rebuildCopies()"
                        >
                            +
                        </button>
                    </div>

                    <p class="mt-2 text-xs text-slate-500">
                        Maksimal 50 copy dalam satu proses.
                    </p>
                </div>
            </div>
        </section>

        <!-- COPY FISIK -->
        <section>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Eksemplar Fisik
                    </p>

                    <h2 class="mt-1 text-xl font-bold text-slate-900">
                        {{ quantity }} Copy yang akan dibuat
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Setiap copy harus mempunyai barcode sendiri.
                    </p>
                </div>

                <div
                    v-if="locations.length > 0 && copies.length > 0"
                    class="text-xs text-slate-500"
                >
                    Lokasi dapat diisi berbeda untuk setiap copy.
                </div>
            </div>

            <div class="mt-4 space-y-4">
                <article
                    v-for="(copy, index) in copies"
                    :key="index"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                Copy {{ index + 1 }}
                            </p>

                            <p class="mt-1 font-bold text-slate-900">
                                {{ selectedBookLabel || 'Buku belum dipilih' }}
                            </p>
                        </div>

                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                            Status awal: AVAILABLE
                        </span>
                    </div>

                    <div class="mb-5 flex justify-end">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-300 px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="!copy.barcode.trim()"
                            @click="openBarcodePreview(index)"
                        >
                            Lihat & Cetak Barcode
                        </button>
                    </div>

                    <div class="grid gap-5 lg:grid-cols-2">
                        <div>
                            <label
                                :for="`barcode-${index}`"
                                class="mb-2 block text-sm font-semibold text-slate-700"
                            >
                                Barcode <span class="text-rose-600">*</span>
                            </label>

                            <input
                                :id="`barcode-${index}`"
                                v-model="copy.barcode"
                                type="text"
                                maxlength="100"
                                required
                                :placeholder="`Contoh: QRH-${String(index + 1).padStart(3, '0')}`"
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 font-mono text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            >
                        </div>

                        <div>
                            <label
                                :for="`inventory-${index}`"
                                class="mb-2 block text-sm font-semibold text-slate-700"
                            >
                                Nomor Inventaris
                            </label>

                            <input
                                :id="`inventory-${index}`"
                                v-model="copy.inventory_number"
                                type="text"
                                maxlength="100"
                                :placeholder="`Contoh: INV/BUKU/${String(index + 1).padStart(3, '0')}`"
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 font-mono text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            >
                        </div>

                        <div>
                            <label
                                :for="`location-${index}`"
                                class="mb-2 block text-sm font-semibold text-slate-700"
                            >
                                Lokasi <span class="text-rose-600">*</span>
                            </label>

                            <div class="flex gap-2">
                                <select
                                    :id="`location-${index}`"
                                    v-model.number="copy.location_id"
                                    required
                                    class="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
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

                                <button
                                    type="button"
                                    class="whitespace-nowrap rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                    @click="openLocationModal"
                                >
                                    + Tambah Rak
                                </button>
                            </div>

                            <button
                                v-if="copy.location_id"
                                type="button"
                                class="mt-2 text-xs font-semibold text-slate-500 hover:text-slate-900"
                                @click="applyLocationToAll(copy.location_id)"
                            >
                                Gunakan lokasi ini untuk semua copy
                            </button>
                        </div>

                        <div>
                            <label
                                :for="`condition-${index}`"
                                class="mb-2 block text-sm font-semibold text-slate-700"
                            >
                                Kondisi
                            </label>

                            <select
                                :id="`condition-${index}`"
                                v-model="copy.condition"
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
                </article>
            </div>
        </section>

        <!-- PEROLEHAN BERSAMA -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                Perolehan
            </p>

            <div class="mt-4 grid gap-5 lg:grid-cols-3">
                <div>
                    <label
                        for="batch-source"
                        class="mb-2 block text-sm font-semibold text-slate-700"
                    >
                        Sumber Perolehan
                    </label>

                    <select
                        id="batch-source"
                        v-model="acquisitionSource"
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
                        for="batch-date"
                        class="mb-2 block text-sm font-semibold text-slate-700"
                    >
                        Tanggal Perolehan
                    </label>

                    <input
                        id="batch-date"
                        v-model="acquisitionDate"
                        type="date"
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                    >
                </div>

                <div>
                    <label
                        for="batch-price"
                        class="mb-2 block text-sm font-semibold text-slate-700"
                    >
                        Harga Per Copy
                    </label>

                    <input
                        id="batch-price"
                        v-model.number="acquisitionPrice"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="0"
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                    >
                </div>
            </div>

            <div class="mt-5">
                <label
                    for="batch-notes"
                    class="mb-2 block text-sm font-semibold text-slate-700"
                >
                    Catatan
                </label>

                <textarea
                    id="batch-notes"
                    v-model="notes"
                    rows="4"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                />
            </div>
        </section>

        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-sm font-semibold text-emerald-800">
                Setiap copy baru akan berstatus AVAILABLE
            </p>

            <p class="mt-1 text-sm text-emerald-700">
                Status awal ditetapkan oleh backend. Nantinya status berubah
                otomatis melalui proses peminjaman, pengembalian, reservasi,
                atau penanganan kondisi buku.
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
                    !!validationError
                "
            >
                {{
                    loading
                        ? `Menyimpan ${quantity} Copy...`
                        : `Simpan ${quantity} Copy`
                }}
            </button>
        </div>

        <!-- MODAL TAMBAH RAK -->
        <div
            v-if="showLocationModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"
            @click.self="closeLocationModal"
        >
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Master Lokasi
                        </p>

                        <h3 class="mt-1 text-xl font-bold text-slate-900">
                            Tambah Rak Baru
                        </h3>
                    </div>

                    <button
                        type="button"
                        class="text-xl text-slate-400 hover:text-slate-900"
                        @click="closeLocationModal"
                    >
                        ×
                    </button>
                </div>

                <div
                    v-if="locationError"
                    class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700"
                >
                    {{ locationError }}
                </div>

                <div class="mt-5 space-y-4">
                    <div>
                        <label
                            for="new-location-code"
                            class="mb-2 block text-sm font-semibold text-slate-700"
                        >
                            Kode Rak <span class="text-rose-600">*</span>
                        </label>

                        <input
                            id="new-location-code"
                            v-model="newLocationCode"
                            type="text"
                            maxlength="100"
                            placeholder="Contoh: D02"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 font-mono text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                        >
                    </div>

                    <div>
                        <label
                            for="new-location-name"
                            class="mb-2 block text-sm font-semibold text-slate-700"
                        >
                            Nama Rak <span class="text-rose-600">*</span>
                        </label>

                        <input
                            id="new-location-name"
                            v-model="newLocationName"
                            type="text"
                            maxlength="255"
                            placeholder="Contoh: Rak Al-Qur'an"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                        >
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        :disabled="locationSaving"
                        @click="closeLocationModal"
                    >
                        Batal
                    </button>

                    <button
                        type="button"
                        class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
                        :disabled="
                            locationSaving ||
                            !newLocationCode.trim() ||
                            !newLocationName.trim()
                        "
                        @click="saveLocation"
                    >
                        {{ locationSaving ? 'Menyimpan...' : 'Simpan Rak' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL PREVIEW + CETAK BARCODE -->
        <div
            v-if="barcodePreviewIndex !== null && barcodePreviewCopy"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/60 p-4"
            role="dialog"
            aria-modal="true"
            @click.self.stop.prevent="closeBarcodePreview"
        >
            <div
                class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl"
                @click.stop
            >

                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Identitas Fisik
                        </p>

                        <h3 class="mt-1 text-xl font-bold text-slate-900">
                            Preview Barcode
                        </h3>

                        <p class="mt-1 text-sm text-slate-500">
                            Barcode dapat dicetak sebelum copy disimpan.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="text-2xl leading-none text-slate-400 hover:text-slate-900"
                        @click.stop.prevent="closeBarcodePreview"
                    >
                        ×
                    </button>
                </div>

                <div class="mt-5">
                    <BookCopyBarcodeLabel
                        :barcode="barcodePreviewCopy.barcode"
                        :title="selectedBook?.title ?? ''"
                        :inventory-number="barcodePreviewCopy.inventory_number || null"
                    />
                </div>

                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <p class="text-sm font-semibold text-amber-800">
                        Perhatian
                    </p>

                    <p class="mt-1 text-sm text-amber-700">
                        Barcode ini masih berada dalam form dan belum disimpan
                        ke database. Pastikan barcode unik sebelum menyimpan copy.
                    </p>
                </div>

                <div class="mt-5 flex justify-end">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click.stop.prevent="closeBarcodePreview"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>

    </form>
</template>