<script setup lang="ts">
defineProps<{
    modelValue: string
}>()

const emit = defineEmits<{
    'update:modelValue': [value: string]
    search: []
}>()

const updateValue = (event: Event): void => {
    emit(
        'update:modelValue',
        (event.target as HTMLInputElement).value,
    )
}
</script>

<template>
    <form
        class="flex flex-col gap-3 sm:flex-row"
        @submit.prevent="emit('search')"
    >
        <InputText
            :model-value="modelValue"
            placeholder="Cari judul, ISBN, atau informasi buku..."
            class="w-full !border-slate-300 !bg-white !text-slate-900 placeholder:!text-slate-400 hover:!border-slate-400 focus:!border-blue-600 focus:!ring-4 focus:!ring-blue-100 focus:!outline-none"
            size="large"
            @input="updateValue"
            @keydown.enter.prevent="emit('search')"
        />

        <Button
            type="button"
            label="Cari Buku"
            icon="pi pi-search"
            size="large"
            @click="emit('search')"
        />
    </form>
</template>
