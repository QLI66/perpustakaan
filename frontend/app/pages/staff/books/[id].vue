<script setup lang="ts">
import type {
    StaffBook,
    StaffBookForm,
} from '~/types/staff'

definePageMeta({
    middleware: 'staff',
})

const route = useRoute()
const router = useRouter()

const authStore = useAuthStore()

const {
    getBook,
    updateBook,
} = useStaffBooks()

const bookId = computed(() => Number(route.params.id))

const book = ref<StaffBook | null>(null)
const loading = ref(true)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const canUpdate = computed(() =>
    authStore.hasPermission('books.update') ||
    authStore.hasRole('SUPER_ADMIN'),
)

const toForm = (
    value: StaffBook,
): StaffBookForm => ({
    isbn: value.isbn || '',
    title: value.title,
    subtitle: value.subtitle || '',
    description: value.description || '',
    publication_year: value.publication_year,
    edition: value.edition || '',
    language: value.language || 'id',
    collection_type_id: value.collection_type?.id ?? null,
    publisher_id: value.publisher?.id ?? null,
    cover: value.cover || '',
    author_ids: value.authors.map((item) => item.id),
    category_ids: value.categories.map((item) => item.id),
})

const form = ref<StaffBookForm>({
    isbn: '',
    title: '',
    subtitle: '',
    description: '',
    publication_year: null,
    edition: '',
    language: 'id',
    collection_type_id: null,
    publisher_id: null,
    cover: '',
    author_ids: [],
    category_ids: [],
})

const loadBook = async () => {
    loading.value = true
    errorMessage.value = ''

    try {
        const result = await getBook(bookId.value)

        book.value = result
        form.value = toForm(result)
    } catch (error: unknown) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Detail buku gagal dimuat.'
    } finally {
        loading.value = false
    }
}

const saveBook = async (payload: StaffBookForm) => {
    if (!canUpdate.value) {
        return
    }

    saving.value = true
    errorMessage.value = ''
    successMessage.value = ''

    try {
        const result = await updateBook(
            bookId.value,
            payload,
        )

        book.value = result
        form.value = toForm(result)

        successMessage.value =
            'Perubahan buku berhasil disimpan.'
    } catch (error: unknown) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Perubahan buku gagal disimpan.'
    } finally {
        saving.value = false
    }
}

const goBack = () => {
    router.push('/staff/books')
}

onMounted(loadBook)
</script>

<template>
    <StaffShell active="books">
        <div class="mx-auto max-w-5xl space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">
                            Manajemen Buku
                        </p>

                        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">
                            {{ book?.title || 'Detail Buku' }}
                        </h1>

                        <p class="mt-2 text-sm text-slate-500">
                            Kelola bibliografi dan lihat ringkasan copy fisik.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="goBack"
                    >
                        ← Kembali
                    </button>
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
                v-if="loading"
                class="grid gap-4 lg:grid-cols-3"
            >
                <div class="h-28 animate-pulse rounded-2xl bg-slate-100"/>
                <div class="h-28 animate-pulse rounded-2xl bg-slate-100"/>
                <div class="h-28 animate-pulse rounded-2xl bg-slate-100"/>
            </section>

            <section
                v-else-if="book"
                class="grid gap-4 md:grid-cols-3"
            >
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-slate-500">
                        Total Copy
                    </p>

                    <p class="mt-2 text-3xl font-bold text-slate-900">
                        {{ book.copies_count }}
                    </p>
                </div>

                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-emerald-700">
                        Tersedia
                    </p>

                    <p class="mt-2 text-3xl font-bold text-emerald-800">
                        {{ book.available_copies_count }}
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-slate-500">
                        ISBN
                    </p>

                    <p class="mt-2 text-lg font-bold text-slate-900">
                        {{ book.isbn || '-' }}
                    </p>
                </div>
            </section>

            <section
                v-if="book && canUpdate"
                class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"
            >
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-slate-900">
                        Edit Data Buku
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Perubahan akan disimpan melalui Book Management API.
                    </p>
                </div>

                <StaffBookForm
                    :initial-value="form"
                    submit-label="Simpan Perubahan"
                    :loading="saving"
                    :error-message="errorMessage"
                    @submit="saveBook"
                />
            </section>

            <section
                v-else-if="book"
                class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
            >
                <h2 class="text-xl font-bold text-slate-900">
                    Informasi Buku
                </h2>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-500">
                            Judul
                        </p>

                        <p class="mt-1 font-semibold text-slate-900">
                            {{ book.title }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-500">
                            Penerbit
                        </p>

                        <p class="mt-1 font-semibold text-slate-900">
                            {{ book.publisher?.name || '-' }}
                        </p>
                    </div>

                    <div class="sm:col-span-2">
                        <p class="text-xs uppercase tracking-wide text-slate-500">
                            Penulis
                        </p>

                        <p class="mt-1 font-semibold text-slate-900">
                            {{
                                book.authors.length
                                    ? book.authors.map((item) => item.name).join(', ')
                                    : '-'
                            }}
                        </p>
                    </div>

                    <div class="sm:col-span-2">
                        <p class="text-xs uppercase tracking-wide text-slate-500">
                            Kategori
                        </p>

                        <p class="mt-1 font-semibold text-slate-900">
                            {{
                                book.categories.length
                                    ? book.categories.map((item) => item.name).join(', ')
                                    : '-'
                            }}
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </StaffShell>
</template>