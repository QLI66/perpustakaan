<script setup lang="ts">
import type { StaffBookForm } from '~/types/staff'

definePageMeta({
    middleware: 'staff',
})

const authStore = useAuthStore()

if (
    !authStore.hasPermission('books.create') &&
    !authStore.hasRole('SUPER_ADMIN')
) {
    await navigateTo('/staff/books')
}

const { createBook } = useStaffBooks()

const loading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const form: StaffBookForm = {
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
}

const submit = async (payload: StaffBookForm) => {
    loading.value = true
    errorMessage.value = ''
    successMessage.value = ''

    try {
        const book = await createBook(payload)

        successMessage.value = `Buku "${book.title}" berhasil ditambahkan.`

        await navigateTo(`/staff/books/${book.id}`)
    } catch (error: unknown) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Buku gagal ditambahkan.'
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <StaffShell active="books">
        <div class="mx-auto max-w-5xl space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <p class="text-sm font-medium text-slate-500">
                    Manajemen Buku
                </p>

                <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">
                    Tambah Buku
                </h1>

                <p class="mt-2 text-sm leading-6 text-slate-500">
                    Masukkan data bibliografi. Setelah buku tersimpan, copy fisik
                    dapat ditambahkan pada tahap berikutnya.
                </p>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <StaffBookForm
                    :initial-value="form"
                    submit-label="Simpan Buku"
                    :loading="loading"
                    :error-message="errorMessage"
                    @submit="submit"
                />
            </section>
        </div>
    </StaffShell>
</template>