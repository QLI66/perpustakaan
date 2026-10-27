<script setup lang="ts">
import type { SelectPassThroughOptions } from 'primevue/select'

import type {
    CatalogCategory,
    CatalogCollectionType,
    CatalogPerson,
    CatalogPublisher,
} from '~/types/catalog'

interface Props {
    categoryId: number | null
    authorId: number | null
    publisherId: number | null
    collectionTypeId: number | null
    status: string
    categories: CatalogCategory[]
    authors: CatalogPerson[]
    publishers: CatalogPublisher[]
    collectionTypes: CatalogCollectionType[]
}

const props = defineProps<Props>()

const emit = defineEmits<{
    'update:categoryId': [value: number | null]
    'update:authorId': [value: number | null]
    'update:publisherId': [value: number | null]
    'update:collectionTypeId': [value: number | null]
    'update:status': [value: string]
    reset: []
}>()

const categoryOptions = computed(() => [
    { label: 'Semua kategori', value: null },
    ...props.categories.map((item) => ({
        label: item.name,
        value: item.id,
    })),
])

const authorOptions = computed(() => [
    { label: 'Semua penulis', value: null },
    ...props.authors.map((item) => ({
        label: item.name ?? 'Tanpa nama',
        value: item.id ?? 0,
    })),
])

const publisherOptions = computed(() => [
    { label: 'Semua penerbit', value: null },
    ...props.publishers.map((item) => ({
        label: item.name,
        value: item.id,
    })),
])

const collectionOptions = computed(() => [
    { label: 'Semua jenis koleksi', value: null },
    ...props.collectionTypes.map((item) => ({
        label: item.name,
        value: item.id,
    })),
])

const statusOptions = [
    { label: 'Semua status', value: '' },
    { label: 'Tersedia', value: 'AVAILABLE' },
    { label: 'Dipinjam', value: 'BORROWED' },
    { label: 'Dipesan', value: 'RESERVED' },
    { label: 'Hilang', value: 'LOST' },
    { label: 'Rusak', value: 'DAMAGED' },
]

const selectPassThrough: SelectPassThroughOptions = {
    root: {
        class: 'w-full !border-slate-300 !bg-white !text-slate-900 hover:!border-slate-400 focus:!border-blue-600 focus:!ring-4 focus:!ring-blue-100 focus:!outline-none',
    },
    label: {
        class: '!text-slate-900 [&.p-placeholder]:!text-slate-400',
    },
    dropdownIcon: {
        class: '!text-slate-500',
    },
    overlay: {
        class: '!border-slate-200 !bg-white !text-slate-900',
    },
    list: {
        class: '!bg-white !text-slate-900',
    },
    option: ({ context }) => ({
        class: [
            '!text-slate-700 [&:not(.p-select-option-selected):hover]:!bg-slate-50 [&:not(.p-select-option-selected):hover]:!text-slate-900',
            context.selected && context.focused
                ? '!bg-blue-200 !text-blue-950'
                : context.selected
                  ? '!bg-blue-100 !text-blue-900'
                  : context.focused
                    ? '!bg-blue-50 !text-blue-900'
                    : '',
        ],
    }),
}
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <Select
            :model-value="categoryId"
            :options="categoryOptions"
            option-label="label"
            option-value="value"
            placeholder="Kategori"
            :pt="selectPassThrough"
            @update:model-value="emit('update:categoryId', $event)"
        />

        <Select
            :model-value="authorId"
            :options="authorOptions"
            option-label="label"
            option-value="value"
            placeholder="Penulis"
            :pt="selectPassThrough"
            @update:model-value="emit('update:authorId', $event)"
        />

        <Select
            :model-value="publisherId"
            :options="publisherOptions"
            option-label="label"
            option-value="value"
            placeholder="Penerbit"
            :pt="selectPassThrough"
            @update:model-value="emit('update:publisherId', $event)"
        />

        <Select
            :model-value="collectionTypeId"
            :options="collectionOptions"
            option-label="label"
            option-value="value"
            placeholder="Jenis koleksi"
            :pt="selectPassThrough"
            @update:model-value="emit('update:collectionTypeId', $event)"
        />

        <Select
            :model-value="status"
            :options="statusOptions"
            option-label="label"
            option-value="value"
            placeholder="Status"
            :pt="selectPassThrough"
            @update:model-value="emit('update:status', $event)"
        />

        <div class="md:col-span-2 xl:col-span-5">
            <Button
                label="Reset Filter"
                icon="pi pi-refresh"
                severity="secondary"
                outlined
                @click="emit('reset')"
            />
        </div>
    </div>
</template>
