<script setup lang="ts">
import type { StaffBook } from '~/types/staff'

definePageMeta({
    middleware: 'staff',
})

const authStore = useAuthStore()

const {
    listBooks,
    deleteBook,
} = useStaffBooks()

const books = ref<StaffBook[]>([])
const loading = ref(true)
const deletingId = ref<number | null>(null)
const search = ref('')
const currentPage = ref(1)
const perPage = 15
const total = ref(0)
const lastPage = ref(1)
const errorMessage = ref('')
const successMessage = ref('')

let searchTimer: ReturnType<typeof setTimeout> | null = null

const canCreate = computed(() =>
    authStore.hasPermission('books.create') ||
    authStore.hasRole('SUPER_ADMIN'),
)

const canUpdate = computed(() =>
    authStore.hasPermission('books.update') ||
    authStore.hasRole('SUPER_ADMIN'),
)

const canDelete = computed(() =>
    authStore.hasPermission('books.delete') ||
    authStore.hasRole('SUPER_ADMIN'),
)

const formatAuthors = (book: StaffBook) => {
    return book.authors.length
        ? book.authors.map((author) => author.name).join(', ')
        : '-'
}

const loadBooks = async () => {
    loading.value = true
    errorMessage.value = ''

    try {
        const result = await listBooks(
            currentPage.value,
            perPage,
            search.value,
        )

        books.value = result.items
        total.value = result.pagination?.total ?? 0
        lastPage.value = result.pagination?.last_page ?? 1
    } catch (error: unknown) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Data buku gagal dimuat.'
    } finally {
        loading.value = false
    }
}

const onSearchInput = () => {
    if (searchTimer) {
        clearTimeout(searchTimer)
    }

    searchTimer = setTimeout(() => {
        currentPage.value = 1
        loadBooks()
    }, 350)
}

const goToPage = (page: number) => {
    currentPage.value = page
    loadBooks()
}

const confirmDelete = async (book: StaffBook) => {
    const confirmed = window.confirm(
        `Hapus buku "${book.title}"? Tindakan ini tidak dapat dibatalkan.`,
    )

    if (!confirmed) {
        return
    }

    deletingId.value = book.id
    errorMessage.value = ''
    successMessage.value = ''

    try {
        await deleteBook(book.id)

        successMessage.value = 'Buku berhasil dihapus.'

        await loadBooks()
    } catch (error: unknown) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Buku gagal dihapus.'
    } finally {
        deletingId.value = null
    }
}

onMounted(loadBooks)

onUnmounted(() => {
    if (searchTimer) {
        clearTimeout(searchTimer)
    }
})
</script>

<template>
    <StaffShell active="books">
        <div class="space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">
                            Data Bibliografi
                        </p>

                        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">
                            Manajemen Buku
                        </h1>

                        <p class="mt-2 max-w-2xl text-sm text-slate-500">
                            Kelola data buku sebelum menambahkan copy fisiknya.
                        </p>
                    </div>

                    <NuxtLink
                        v-if="canCreate"
                        to="/staff/books/create"
                        class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800"
                    >
                        + Tambah Buku
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

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">
                            Daftar Buku
                        </p>

                        <p class="mt-1 text-xs text-slate-500">
                            {{ total }} buku terdaftar
                        </p>
                    </div>

                    <div class="w-full sm:max-w-sm">
                        <label
                            for="staff-book-search"
                            class="sr-only"
                        >
                            Cari buku
                        </label>

                        <input
                            id="staff-book-search"
                            v-model="search"
                            type="search"
                            placeholder="Cari judul, ISBN, penulis..."
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            @input="onSearchInput"
                        >
                    </div>
                </div>

                <div
                    v-if="loading"
                    class="mt-5 space-y-3"
                >
                    <div
                        v-for="i in 5"
                        :key="i"
                        class="h-20 animate-pulse rounded-xl bg-slate-100"
                    />
                </div>

                <div
                    v-else-if="books.length === 0"
                    class="mt-5 rounded-2xl border border-dashed border-slate-300 p-10 text-center"
                >
                    <p class="font-semibold text-slate-900">
                        Belum ada buku
                    </p>

                    <p class="mt-1 text-sm text-slate-500">
                        Tambahkan data bibliografi buku pertama.
                    </p>
                </div>

                <div
                    v-else
                    class="mt-5 overflow-x-auto"
                >
                    <table class="w-full min-w-[700px] border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-4 py-3">
                                    Buku
                                </th>

                                <th class="px-4 py-3">
                                    Penulis
                                </th>

                                <th class="px-4 py-3">
                                    Jenis
                                </th>

                                <th class="px-4 py-3">
                                    Copy
                                </th>

                                <th class="px-4 py-3 text-right">
                                    Aksi
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="book in books"
                                :key="book.id"
                                class="border-b border-slate-100 last:border-0"
                            >
                                <td class="px-4 py-4">
                                    <div>
                                        <NuxtLink
                                            :to="`/staff/books/${book.id}`"
                                            class="font-semibold text-slate-900 hover:underline"
                                        >
                                            {{ book.title }}
                                        </NuxtLink>

                                        <p class="mt-1 text-xs text-slate-500">
                                            ISBN: {{ book.isbn || '-' }}
                                        </p>
                                    </div>
                                </td>

                                <td class="px-4 py-4 text-sm text-slate-600">
                                    {{ formatAuthors(book) }}
                                </td>

                                <td class="px-4 py-4 text-sm text-slate-600">
                                    {{ book.collection_type?.name || '-' }}
                                </td>

                                <td class="px-4 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                            {{ book.copies_count }} total
                                        </span>

                                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                            {{ book.available_copies_count }} tersedia
                                        </span>
                                    </div>
                                </td>

                                <td class="px-4 py-4">
                                    <div class="flex justify-end gap-2">
                                        <NuxtLink
                                            :to="`/staff/books/${book.id}`"
                                            class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                        >
                                            Detail
                                        </NuxtLink>

                                        <NuxtLink
                                            v-if="canUpdate"
                                            :to="`/staff/books/${book.id}`"
                                            class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800"
                                        >
                                            Edit
                                        </NuxtLink>

                                        <button
                                            v-if="canDelete"
                                            type="button"
                                            class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:opacity-50"
                                            :disabled="deletingId === book.id"
                                            @click="confirmDelete(book)"
                                        >
                                            {{
                                                deletingId === book.id
                                                    ? 'Menghapus...'
                                                    : 'Hapus'
                                            }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="lastPage > 1"
                    class="mt-5 flex flex-wrap items-center justify-center gap-2"
                >
                    <button
                        v-for="page in lastPage"
                        :key="page"
                        type="button"
                        class="h-9 min-w-9 rounded-lg border px-3 text-sm font-semibold"
                        :class="page === currentPage
                            ? 'border-slate-900 bg-slate-900 text-white'
                            : 'border-slate-200 text-slate-700 hover:bg-slate-50'"
                        @click="goToPage(page)"
                    >
                        {{ page }}
                    </button>
                </div>
            </section>
        </div>
    </StaffShell>
</template>