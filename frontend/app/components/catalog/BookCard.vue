<script setup lang="ts">
import type { CatalogBook } from '~/types/catalog'

const props = defineProps<{
    book: CatalogBook
}>()

const authors = computed(() => {
    const names = props.book.authors
        ?.map((item) => item.name)
        .filter(Boolean)
        .join(', ')

    return names || 'Penulis belum tersedia'
})

const collectionName = computed(() => {
    return props.book.collection_type?.name ?? 'Koleksi'
})

const availability = computed(() => {
    const explicitAvailable =
        props.book.availability?.available_copies ??
        props.book.available_count ??
        props.book.available_copies

    if (typeof explicitAvailable === 'number') {
        return {
            label: explicitAvailable > 0
                ? `${explicitAvailable} tersedia`
                : 'Tidak tersedia',
            severity: explicitAvailable > 0
                ? 'success'
                : 'danger',
        } as const
    }

    return {
        label: 'Lihat detail',
        severity: 'info',
    } as const
})
</script>

<template>
    <Card class="h-full overflow-hidden !rounded-2xl !border !border-slate-200 !bg-white !text-slate-900 !shadow-sm transition duration-200 hover:!border-blue-200 hover:!shadow-lg hover:!shadow-blue-950/5">
        <template #header>
            <div class="relative flex h-52 items-center justify-center overflow-hidden bg-gradient-to-br from-blue-50 via-slate-50 to-cyan-100/70">
                <div
                    aria-hidden="true"
                    class="absolute -right-8 -top-10 h-32 w-32 rounded-full bg-white/70"
                />
                <div class="relative flex h-32 w-24 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-blue-900 p-2 shadow-xl shadow-blue-950/20">
                    <div class="flex h-full w-full items-center justify-center rounded-lg border border-white/30 text-4xl font-bold text-white">
                        {{ book.title.charAt(0).toUpperCase() }}
                    </div>
                </div>
            </div>
        </template>

        <template #title>
            <div class="line-clamp-2 min-h-14 text-lg font-semibold leading-6 !text-slate-900">
                {{ book.title }}
            </div>
        </template>

        <template #subtitle>
            <span class="text-sm leading-5 !text-slate-500">
                {{ authors }}
            </span>
        </template>

        <template #content>
            <div class="space-y-3 text-sm !text-slate-700">
                <div
                    v-if="book.isbn"
                    class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 text-slate-600"
                >
                    ISBN: {{ book.isbn }}
                </div>

                <div class="flex flex-wrap gap-2">
                    <Tag
                        :value="availability.label"
                        :severity="availability.severity"
                        :class="
                            availability.severity === 'success'
                                ? '!border !border-emerald-200 !bg-emerald-50 !text-emerald-700'
                                : availability.severity === 'danger'
                                  ? '!border !border-rose-200 !bg-rose-50 !text-rose-700'
                                  : '!border !border-blue-200 !bg-blue-50 !text-blue-700'
                        "
                    />

                    <Tag
                        :value="collectionName"
                        severity="secondary"
                        class="!border !border-slate-200 !bg-slate-100 !text-slate-700"
                    />
                </div>

                <div
                    v-if="book.publication_year"
                    class="text-slate-500"
                >
                    Tahun terbit: {{ book.publication_year }}
                </div>
            </div>
        </template>

        <template #footer>
            <NuxtLink
                v-slot="{ href, navigate }"
                :to="`/catalog/${book.id}`"
                custom
            >
                <Button
                    as="a"
                    :href="href"
                    label="Lihat Detail"
                    icon="pi pi-arrow-right"
                    class="w-full !border-blue-700 !bg-blue-700 !text-white hover:!border-blue-800 hover:!bg-blue-800 focus-visible:!outline-none focus-visible:!ring-4 focus-visible:!ring-blue-200"
                    @click="navigate"
                />
            </NuxtLink>
        </template>
    </Card>
</template>
