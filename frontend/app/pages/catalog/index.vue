<script setup lang="ts">
import type {
    CatalogBook,
    CatalogCategory,
    CatalogCollectionType,
    CatalogPagination,
    CatalogPerson,
    CatalogPublisher,
} from '~/types/catalog'
import CatalogBookGrid from '~/components/catalog/BookGrid.vue'

const {
    getBooks,
    getCategories,
    getAuthors,
    getPublishers,
    getCollectionTypes,
} = useCatalog()

const search = ref('')
const categoryId = ref<number | null>(null)
const authorId = ref<number | null>(null)
const publisherId = ref<number | null>(null)
const collectionTypeId = ref<number | null>(null)
const status = ref('')

const books = ref<CatalogBook[]>([])
const pagination = ref<CatalogPagination>({
    current_page: 1,
    last_page: 1,
    per_page: 12,
    total: 0,
})

const categories = ref<CatalogCategory[]>([])
const authors = ref<CatalogPerson[]>([])
const publishers = ref<CatalogPublisher[]>([])
const collectionTypes = ref<CatalogCollectionType[]>([])

const loading = ref(true)
const filterLoading = ref(false)
const errorMessage = ref('')

const loadBooks = async (
    page = 1,
): Promise<void> => {
    errorMessage.value = ''

    try {
        filterLoading.value = true

        const result = await getBooks({
            search: search.value.trim(),
            category_id: categoryId.value,
            author_id: authorId.value,
            publisher_id: publisherId.value,
            collection_type_id: collectionTypeId.value,
            status: status.value,
            page,
            per_page: 12,
        })

        books.value = Array.isArray(result.items)
    ? result.items
    : []

pagination.value = result.pagination

console.log('CATALOG RESULT:', result)
console.log('CATALOG ITEMS:', books.value)
console.log('CATALOG ITEM COUNT:', books.value.length)
    } catch (error) {
        console.error(error)

        books.value = []
        errorMessage.value =
            'Katalog buku gagal dimuat. Periksa koneksi API dan coba lagi.'
    } finally {
        loading.value = false
        filterLoading.value = false
    }
}

const loadFilters = async (): Promise<void> => {
    try {
        const [
            categoryResult,
            authorResult,
            publisherResult,
            collectionResult,
        ] = await Promise.all([
            getCategories(),
            getAuthors(),
            getPublishers(),
            getCollectionTypes(),
        ])

        categories.value = categoryResult
        authors.value = authorResult
        publishers.value = publisherResult
        collectionTypes.value = collectionResult
    } catch (error) {
        console.error(error)
    }
}

const searchBooks = (): void => {
    void loadBooks(1)
}

const resetFilters = (): void => {
    search.value = ''
    categoryId.value = null
    authorId.value = null
    publisherId.value = null
    collectionTypeId.value = null
    status.value = ''

    void loadBooks(1)
}

const changePage = (page: number): void => {
    void loadBooks(page)
}

usePageRefresh(() => {
    if (filterLoading.value) {
        return Promise.resolve()
    }

    return loadBooks(pagination.value.current_page)
}, 30_000)

onMounted(async () => {
    await Promise.all([
        loadFilters(),
        loadBooks(1),
    ])
})
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <section class="border-b border-slate-200 bg-white">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="max-w-3xl">
                    <Tag
                        value="Katalog Perpustakaan"
                        severity="info"
                        class="mb-4"
                    />

                    <h1 class="text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl">
                        Temukan Buku yang Anda Cari
                    </h1>

                    <p class="mt-4 text-lg text-slate-600">
                        Jelajahi koleksi buku perpustakaan sekolah berdasarkan judul,
                        penulis, kategori, penerbit, dan jenis koleksi.
                    </p>
                </div>

                <div class="mt-8 max-w-4xl">
                    <CatalogSearch
                        v-model="search"
                        @search="searchBooks"
                    />
                </div>
            </div>
        </section>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-semibold text-slate-900">
                            Filter Katalog
                        </h2>

                        <p class="text-sm text-slate-500">
                            Gunakan filter untuk mempersempit hasil pencarian.
                        </p>
                    </div>

                    <div
                        v-if="filterLoading"
                        class="text-sm text-slate-500"
                    >
                        Memuat...
                    </div>
                </div>

                <CatalogFilters
                    v-model:category-id="categoryId"
                    v-model:author-id="authorId"
                    v-model:publisher-id="publisherId"
                    v-model:collection-type-id="collectionTypeId"
                    v-model:status="status"
                    :categories="categories"
                    :authors="authors"
                    :publishers="publishers"
                    :collection-types="collectionTypes"
                    @reset="resetFilters"
                    @update:category-id="loadBooks(1)"
                    @update:author-id="loadBooks(1)"
                    @update:publisher-id="loadBooks(1)"
                    @update:collection-type-id="loadBooks(1)"
                    @update:status="loadBooks(1)"
                />
            </div>

            <div class="mt-8 flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">
                        Koleksi Buku
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        {{ pagination.total }} buku ditemukan
                    </p>
                </div>
            </div>

            <div
                v-if="errorMessage"
                class="mt-6"
            >
                <Message
                    severity="error"
                    :closable="false"
                >
                    {{ errorMessage }}
                </Message>
            </div>

            <div
                v-if="loading"
                class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            >
                <div
                    v-for="item in 8"
                    :key="item"
                    class="h-96 animate-pulse rounded-xl border border-slate-200 bg-white"
                />
            </div>

            <div
                v-else
                class="mt-8"
            >
                <CatalogBookGrid :books="books" />

                <CatalogPagination
                    :current-page="pagination.current_page"
                    :last-page="pagination.last_page"
                    :total="pagination.total"
                    :per-page="pagination.per_page"
                    @page="changePage"
                />
            </div>
        </main>
    </div>
</template>
