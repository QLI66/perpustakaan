<script setup lang="ts">
import { useSchoolIdentityStore } from '~/stores/schoolIdentity'

const identityStore = useSchoolIdentityStore()

onMounted(() => {
    identityStore.fetchIdentity()
})
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:px-6 lg:px-8">
                <div
                    v-if="identityStore.hasCustomLogo"
                    class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl"
                >
                    <img
                        :src="identityStore.resolvedLogoUrl || ''"
                        alt="Logo"
                        class="h-full w-full object-contain"
                    >
                </div>
                <div class="text-lg font-semibold text-slate-900">
                    {{ identityStore.schoolName }}
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <slot />
        </main>
    </div>
</template>
