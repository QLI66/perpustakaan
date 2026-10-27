<script setup lang="ts">
const links = [
    { label: 'Dashboard', to: '/member' },
    { label: 'Katalog', to: '/catalog' },
    { label: 'Peminjaman', to: '/member/loans' },
    { label: 'Denda', to: '/member/fines' },
    { label: 'Reservasi', to: '/member/reservations' },
    { label: 'Profil', to: '/member/profile' },
]
</script>

<template>
    <nav class="flex flex-wrap gap-2">
        <NuxtLink
            v-for="link in links"
            :key="link.to"
            :to="link.to"
            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
            active-class="border-slate-900 bg-slate-900 !text-white"
        >
            {{ link.label }}
        </NuxtLink>
    </nav>
</template>