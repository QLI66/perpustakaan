<script setup lang="ts">
import type {
    BookCopyBatchForm,
} from '~/types/book-copy'

definePageMeta({
    middleware: 'staff',
})

const authStore = useAuthStore()

const canCreate = computed(() =>
    authStore.hasPermission('book_copies.create') ||
    authStore.hasRole('SUPER_ADMIN'),
)

const {
    createCopy,
} = useBookCopies()

const loading = ref(false)
const errorMessage = ref('')

const submitBatch = async (
    payload: BookCopyBatchForm,
) => {
    if (!canCreate.value) {
        await navigateTo('/staff/book-copies')
        return
    }

    loading.value = true
    errorMessage.value = ''

    let createdCount = 0

    try {
        for (const copy of payload.copies) {
            await createCopy({
                ...copy,
                book_id: payload.book_id,
            })

            createdCount++
        }

        await navigateTo(
            `/staff/book-copies?created=${createdCount}`,
        )
    }
    catch (error: unknown) {
        errorMessage.value =
            error instanceof Error
                ? `${error.message} (${createdCount} dari ${payload.copies.length} copy sudah tersimpan.)`
                : `Proses berhenti setelah ${createdCount} dari ${payload.copies.length} copy.`
    }
    finally {
        loading.value = false
    }
}
</script>

<template>
    <StaffShell active="book-copies">
        <div class="mx-auto max-w-6xl space-y-6">

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <p class="text-sm font-medium text-slate-500">
                    Inventaris Fisik
                </p>

                <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">
                    Tambah Copy Buku
                </h1>

                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                    Pilih satu buku, tentukan jumlah eksemplar, lalu lengkapi
                    barcode, inventaris, lokasi, dan kondisi untuk setiap copy.
                </p>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <StaffBookCopiesCreateForm
                    :loading="loading"
                    :error-message="errorMessage"
                    @submit="submitBatch"
                />
            </section>

        </div>
    </StaffShell>
</template>