<script setup lang="ts">
import { useSchoolIdentityStore } from '~/stores/schoolIdentity'

const identityStore = useSchoolIdentityStore()

onMounted(() => {
    identityStore.fetchIdentity()
})

useHead({
    titleTemplate: (title) => (
        title ? `${title} · ${identityStore.schoolName}` : identityStore.schoolName
    ),
    meta: [
        {
            name: 'description',
            content: computed(() => `Sistem ${identityStore.schoolName}`),
        },
    ],
})
</script>

<template>
    <NuxtLayout>
        <NuxtPage />
        <Toast />
    </NuxtLayout>
</template>
