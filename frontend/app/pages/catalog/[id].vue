<script setup lang="ts">
import type { CatalogBook } from '~/types/catalog'

const route = useRoute()
const { getBook } = useCatalog()

const book = ref<CatalogBook | null>(null)
const loading = ref(true)
const errorMessage = ref('')
let bookRequestInFlight = false

const bookId = computed(() => {
    const value = Number(route.params.id)

    return Number.isInteger(value) && value > 0
        ? value
        : null
})

const authorsText = computed(() => {
    const authors = book.value?.authors
        ?.map((author) => author.name)
        .filter(Boolean)

    return authors?.length
        ? authors.join(', ')
        : 'Tidak tersedia'
})

const categoriesText = computed(() => {
    const categories = book.value?.categories
        ?.map((category) => category.name)
        .filter(Boolean)

    return categories?.length
        ? categories.join(', ')
        : 'Tidak tersedia'
})

const locationsText = computed(() => {
    const locations = book.value?.locations
        ?.map((location) => location.name)
        .filter(Boolean)

    return locations?.length
        ? locations.join(', ')
        : 'Tidak tersedia'
})

const availability = computed(() => {
    return book.value?.availability ?? {
        total_copies: 0,
        available_copies: 0,
        borrowed_copies: 0,
        reserved_copies: 0,
        lost_copies: 0,
        damaged_copies: 0,
        maintenance_copies: 0,
        is_available: false,
    }
})

const loadBook = async (showLoading = true): Promise<void> => {
    if (bookRequestInFlight) {
        return
    }

    bookRequestInFlight = true
    errorMessage.value = ''

    if (bookId.value === null) {
        errorMessage.value = 'ID buku tidak valid.'
        if (showLoading) {
            loading.value = false
        }
        bookRequestInFlight = false
        return
    }

    try {
        if (showLoading) {
            loading.value = true
        }
        book.value = await getBook(bookId.value)

        useHead({
            title: book.value?.title
                ? `${book.value.title} - Perpustakaan Sekolah`
                : 'Detail Buku - Perpustakaan Sekolah',
        })
    } catch (error) {
        console.error('Failed to load book:', error)

        book.value = null
        errorMessage.value =
            'Detail buku tidak dapat dimuat atau buku tidak ditemukan.'
    } finally {
        if (showLoading) {
            loading.value = false
        }
        bookRequestInFlight = false
    }
}

usePageRefresh(() => loadBook(false), 30_000)
onMounted(() => {
    void loadBook()
})
</script>

<template>
    <main class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-blue-50/70 !text-slate-900">
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            <NuxtLink
                to="/catalog"
                class="mb-6 inline-flex items-center gap-2 rounded-lg text-sm font-semibold text-blue-700 transition hover:text-blue-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-200"
            >
                <i class="pi pi-arrow-left" />
                Kembali ke katalog
            </NuxtLink>

            <div
                v-if="loading"
                class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm"
            >
                <div class="animate-pulse space-y-6">
                    <div class="h-8 w-2/3 rounded bg-slate-200" />
                    <div class="h-5 w-1/3 rounded bg-slate-200" />
                    <div class="h-40 rounded bg-slate-200" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="h-16 rounded bg-slate-200" />
                        <div class="h-16 rounded bg-slate-200" />
                        <div class="h-16 rounded bg-slate-200" />
                        <div class="h-16 rounded bg-slate-200" />
                    </div>
                </div>
            </div>

            <Message
                v-else-if="errorMessage"
                severity="error"
                :closable="false"
            >
                {{ errorMessage }}
            </Message>

            <Card
                v-else-if="book"
                class="!overflow-hidden !rounded-3xl !border !border-slate-200 !bg-white !text-slate-900 !shadow-xl !shadow-blue-950/5"
            >
                <template #header>
                    <div class="bg-gradient-to-br from-blue-700 via-blue-800 to-slate-900 px-6 py-8 sm:px-10 sm:py-10">
                        <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
                            <div class="flex h-36 w-28 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-white to-blue-50 p-2 shadow-xl shadow-blue-950/20">
                                <div class="flex h-full w-full items-center justify-center rounded-xl border border-blue-100 text-5xl font-bold text-blue-800">
                                    {{ book.title.charAt(0).toUpperCase() }}
                                </div>
                            </div>

                            <div class="min-w-0 text-white">
                                <Tag
                                    value="DETAIL BUKU"
                                    severity="info"
                                    class="mb-4 !border !border-blue-200/40 !bg-blue-500/20 !text-blue-50"
                                />

                                <h1 class="text-3xl font-bold leading-tight tracking-tight text-white sm:text-4xl">
                                    {{ book.title }}
                                </h1>

                                <p
                                    v-if="book.subtitle"
                                    class="mt-3 text-base leading-7 text-blue-100"
                                >
                                    {{ book.subtitle }}
                                </p>
                            </div>
                        </div>
                    </div>
                </template>

                <template #content>
                    <div class="!bg-white !text-slate-900">
                        <div class="grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(16rem,1fr)]">
                            <section>
                                <h2 class="text-xl font-bold tracking-tight !text-slate-900">
                                    Informasi Bibliografi
                                </h2>

                                <dl class="mt-6 grid gap-x-8 gap-y-6 sm:grid-cols-2">
                                    <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-4">
                                        <dt class="text-sm !text-slate-500">
                                            ISBN
                                        </dt>
                                        <dd class="mt-1 font-medium !text-slate-900">
                                            {{ book.isbn || 'Tidak tersedia' }}
                                        </dd>
                                    </div>

                                    <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-4">
                                        <dt class="text-sm !text-slate-500">
                                            Penulis
                                        </dt>
                                        <dd class="mt-1 font-medium !text-slate-900">
                                            {{ authorsText }}
                                        </dd>
                                    </div>

                                    <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-4">
                                        <dt class="text-sm !text-slate-500">
                                            Penerbit
                                        </dt>
                                        <dd class="mt-1 font-medium !text-slate-900">
                                            {{ book.publisher?.name || 'Tidak tersedia' }}
                                        </dd>
                                    </div>

                                    <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-4">
                                        <dt class="text-sm !text-slate-500">
                                            Kategori
                                        </dt>
                                        <dd class="mt-1 font-medium !text-slate-900">
                                            {{ categoriesText }}
                                        </dd>
                                    </div>

                                    <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-4">
                                        <dt class="text-sm !text-slate-500">
                                            Jenis Koleksi
                                        </dt>
                                        <dd class="mt-1 font-medium !text-slate-900">
                                            {{ book.collection_type?.name || 'Tidak tersedia' }}
                                        </dd>
                                    </div>

                                    <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-4">
                                        <dt class="text-sm !text-slate-500">
                                            Tahun Terbit
                                        </dt>
                                        <dd class="mt-1 font-medium !text-slate-900">
                                            {{ book.publication_year ?? 'Tidak tersedia' }}
                                        </dd>
                                    </div>

                                    <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-4">
                                        <dt class="text-sm !text-slate-500">
                                            Edisi
                                        </dt>
                                        <dd class="mt-1 font-medium !text-slate-900">
                                            {{ book.edition || 'Tidak tersedia' }}
                                        </dd>
                                    </div>

                                    <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-4">
                                        <dt class="text-sm !text-slate-500">
                                            Bahasa
                                        </dt>
                                        <dd class="mt-1 font-medium !text-slate-900">
                                            {{ book.language || 'Tidak tersedia' }}
                                        </dd>
                                    </div>

                                    <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-4 sm:col-span-2">
                                        <dt class="text-sm !text-slate-500">
                                            Lokasi
                                        </dt>
                                        <dd class="mt-1 font-medium !text-slate-900">
                                            {{ locationsText }}
                                        </dd>
                                    </div>
                                </dl>
                            </section>

                            <aside class="rounded-2xl border border-blue-100 !bg-blue-50/70 p-5 sm:p-6">
                                <h2 class="text-lg font-bold !text-slate-900">
                                    Ketersediaan
                                </h2>

                                <div class="mt-5">
                                    <Tag
                                        :value="
                                            availability.is_available
                                                ? 'Buku tersedia'
                                                : 'Tidak tersedia'
                                        "
                                        :severity="
                                            availability.is_available
                                                ? 'success'
                                                : 'danger'
                                        "
                                        :class="
                                            availability.is_available
                                                ? '!border !border-emerald-200 !bg-emerald-50 !text-emerald-700'
                                                : '!border !border-rose-200 !bg-rose-50 !text-rose-700'
                                        "
                                    />
                                </div>

                                <div class="mt-6 grid gap-3">
                                    <div class="rounded-xl border border-blue-100 !bg-white p-4 shadow-sm shadow-blue-950/5">
                                        <div class="text-sm !text-slate-500">
                                            Copy tersedia
                                        </div>
                                        <div class="mt-1 text-2xl font-bold !text-slate-900">
                                            {{ availability.available_copies }}
                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-blue-100 !bg-white p-4 shadow-sm shadow-blue-950/5">
                                        <div class="text-sm !text-slate-500">
                                            Total copy
                                        </div>
                                        <div class="mt-1 text-2xl font-bold !text-slate-900">
                                            {{ availability.total_copies }}
                                        </div>
                                    </div>
                                </div>
                            </aside>
                        </div>

                        <section
                            v-if="book.description"
                            class="mt-10 border-t border-slate-200 pt-8"
                        >
                            <h2 class="text-xl font-bold tracking-tight !text-slate-900">
                                Deskripsi Buku
                            </h2>

                            <p class="mt-4 leading-7 !text-slate-600">
                                {{ book.description }}
                            </p>
                        </section>

                        <section
                            v-if="book.authors?.length"
                            class="mt-10 border-t border-slate-200 pt-8"
                        >
                            <h2 class="text-xl font-bold tracking-tight !text-slate-900">
                                Penulis
                            </h2>

                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                <div
                                    v-for="author in book.authors"
                                    :key="author.id"
                                    class="rounded-2xl border border-slate-200 !bg-gradient-to-br from-white to-blue-50/70 p-5 shadow-sm shadow-blue-950/5"
                                >
                                    <h3 class="font-semibold !text-slate-900">
                                        {{ author.name }}
                                    </h3>

                                    <p
                                        v-if="author.biography"
                                        class="mt-2 text-sm leading-6 !text-slate-600"
                                    >
                                        {{ author.biography }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section
                            v-if="book.targets?.length"
                            class="mt-10 border-t border-slate-200 pt-8"
                        >
                            <h2 class="text-xl font-bold tracking-tight !text-slate-900">
                                Target Pembaca
                            </h2>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <Tag
                                    v-for="target in book.targets"
                                    :key="target.id"
                                    :value="
                                        [
                                            target.class_level,
                                            target.major,
                                        ]
                                            .filter(Boolean)
                                            .join(' - ')
                                        || 'Umum'
                                    "
                                    severity="secondary"
                                    class="!border !border-blue-200 !bg-blue-50 !text-blue-800"
                                />
                            </div>
                        </section>

                        <section class="mt-10 border-t border-slate-200 pt-8">
                            <h2 class="text-xl font-bold tracking-tight !text-slate-900">
                                Ringkasan Status Copy
                            </h2>

                            <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div class="rounded-xl border border-slate-200 !bg-white p-4">
                                    <div class="text-sm !text-slate-500">
                                        Tersedia
                                    </div>
                                    <div class="mt-1 text-xl font-bold !text-slate-900">
                                        {{ availability.available_copies }}
                                    </div>
                                </div>

                                <div class="rounded-xl border border-slate-200 !bg-white p-4">
                                    <div class="text-sm !text-slate-500">
                                        Dipinjam
                                    </div>
                                    <div class="mt-1 text-xl font-bold !text-slate-900">
                                        {{ availability.borrowed_copies }}
                                    </div>
                                </div>

                                <div class="rounded-xl border border-slate-200 !bg-white p-4">
                                    <div class="text-sm !text-slate-500">
                                        Dipesan
                                    </div>
                                    <div class="mt-1 text-xl font-bold !text-slate-900">
                                        {{ availability.reserved_copies }}
                                    </div>
                                </div>

                                <div class="rounded-xl border border-slate-200 !bg-white p-4">
                                    <div class="text-sm !text-slate-500">
                                        Hilang
                                    </div>
                                    <div class="mt-1 text-xl font-bold !text-slate-900">
                                        {{ availability.lost_copies }}
                                    </div>
                                </div>

                                <div class="rounded-xl border border-slate-200 !bg-white p-4">
                                    <div class="text-sm !text-slate-500">
                                        Rusak
                                    </div>
                                    <div class="mt-1 text-xl font-bold !text-slate-900">
                                        {{ availability.damaged_copies }}
                                    </div>
                                </div>

                                <div class="rounded-xl border border-slate-200 !bg-white p-4">
                                    <div class="text-sm !text-slate-500">
                                        Maintenance
                                    </div>
                                    <div class="mt-1 text-xl font-bold !text-slate-900">
                                        {{ availability.maintenance_copies }}
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </template>

                <template #footer>
                    <div class="!bg-white">
                        <NuxtLink to="/catalog">
                            <Button
                                label="Kembali ke Katalog"
                                icon="pi pi-arrow-left"
                                severity="secondary"
                                outlined
                                class="focus-visible:!outline-none focus-visible:!ring-4 focus-visible:!ring-blue-200"
                            />
                        </NuxtLink>
                    </div>
                </template>
            </Card>
        </div>
    </main>
</template>
