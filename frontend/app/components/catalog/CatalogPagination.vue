<script setup lang="ts">
const props = defineProps<{
    currentPage: number
    lastPage: number
    total: number
    perPage: number
}>()

const emit = defineEmits<{
    page: [page: number]
}>()

const first = computed(() => {
    return Math.max(
        0,
        (props.currentPage - 1) * props.perPage,
    )
})

const handlePage = (event: {
    page: number
}): void => {
    emit('page', event.page + 1)
}
</script>

<template>
    <div
        v-if="lastPage > 1"
        class="mt-8 flex justify-center"
    >
        <Paginator
            :first="first"
            :rows="perPage"
            :total-records="total"
            @page="handlePage"
        />
    </div>
</template>
