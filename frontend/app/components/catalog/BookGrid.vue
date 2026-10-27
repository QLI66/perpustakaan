<script setup lang="ts">
import BookCard from './BookCard.vue'

import type { CatalogBook } from '~/types/catalog'

defineProps<{
    books: CatalogBook[]
}>()
</script>

<template>
    <div>
        <div
            v-if="books.length > 0"
            class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <BookCard
                v-for="book in books"
                :key="book.id"
                :book="book"
            />
        </div>

        <div
            v-else
            class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center"
        >
            <div class="text-5xl">
                ??
            </div>

            <h3 class="mt-4 text-lg font-semibold text-slate-900">
                Tidak ada buku pada halaman ini
            </h3>

            <p class="mt-2 text-sm text-slate-500">
                Data katalog tidak memiliki item yang bisa ditampilkan.
            </p>
        </div>
    </div>
</template>
