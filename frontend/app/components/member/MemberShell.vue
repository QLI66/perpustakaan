<script setup lang="ts">
import type { MemberProfile } from '~/types/member'
import { useSchoolIdentityStore } from '~/stores/schoolIdentity'

defineProps<{
    profile?: MemberProfile | null
}>()

const identityStore = useSchoolIdentityStore()
const route = useRoute()
const { logout } = useMember()

onMounted(() => {
    identityStore.fetchIdentity()
})

const navItems = [
    {
        label: 'Dashboard',
        to: '/member',
        icon: 'dashboard',
    },
    {
        label: 'Katalog',
        to: '/catalog',
        icon: 'catalog',
    },
    {
        label: 'Peminjaman',
        to: '/member/loans',
        icon: 'loans',
    },
    {
        label: 'Reservasi',
        to: '/member/reservations',
        icon: 'reservations',
    },
    {
        label: 'Denda',
        to: '/member/fines',
        icon: 'fines',
    },
    {
        label: 'Insiden',
        to: '/member/incidents',
        icon: 'incidents',
    },
    {
        label: 'Profil',
        to: '/member/profile',
        icon: 'profile',
    },
]

const isActive = (to: string) => {
    if (to === '/member') {
        return route.path === '/member'
    }

    return route.path === to || route.path.startsWith(`${to}/`)
}

const iconPath = (icon: string) => {
    const paths: Record<string, string> = {
        dashboard:
            'M3 10.5 12 3l9 7.5M5 9v10h14V9M9 19v-6h6v6',
        catalog:
            'M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5V5.5ZM4 5.5A2.5 2.5 0 0 0 6.5 8H20',
        loans:
            'M5 4h14v16H5zM8 8h8M8 12h8M8 16h5',
        reservations:
            'M6 3v4M18 3v4M4 7h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1ZM8 11h3M8 15h3',
        fines:
            'M12 4v16M16 7.5c-.8-.8-2-1.5-4-1.5-2.5 0-4 1.2-4 3s1.5 3 4 3 4 1.2 4 3-1.5 3-4 3c-2 0-3.2-.7-4-1.5',
        profile:
            'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 21a8 8 0 0 1 16 0',
        logout:
            'M9 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4M15 16l4-4-4-4M19 12H9',
    }

    return paths[icon] || paths.dashboard
}
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-[1440px] items-center justify-between px-4 lg:px-6">
                <NuxtLink
                    to="/member"
                    class="flex items-center gap-3"
                >
                    <div
                        class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-xl text-white shadow-sm"
                        :style="{ backgroundColor: identityStore.themeColor || '#0f172a' }"
                    >
                        <img
                            v-if="identityStore.hasCustomLogo"
                            :src="identityStore.resolvedLogoUrl || ''"
                            alt="Logo"
                            class="h-full w-full object-contain p-1"
                        >
                        <svg
                            v-else
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path :d="iconPath('catalog')" />
                        </svg>
                    </div>

                    <div class="hidden sm:block">
                        <p class="text-sm font-bold text-slate-900">
                            {{ identityStore.schoolName }}
                        </p>
                        <p class="text-xs text-slate-500">
                            Portal Anggota
                        </p>
                    </div>
                </NuxtLink>

                <div class="flex items-center gap-3">
                    <div class="hidden text-right sm:block">
                        <p class="text-sm font-semibold text-slate-900">
                            {{ profile?.name || 'Member' }}
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ profile?.member_number || '' }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
                        title="Keluar"
                        @click="logout"
                    >
                        <svg
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path :d="iconPath('logout')" />
                        </svg>
                    </button>
                </div>
            </div>
        </header>

        <div class="mx-auto flex max-w-[1440px]">
            <aside class="sticky top-16 hidden h-[calc(100vh-4rem)] w-64 shrink-0 border-r border-slate-200 bg-white p-4 lg:block">
                <div class="mb-5 rounded-2xl bg-slate-50 p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                        Akun Anda
                    </p>
                    <p class="mt-2 font-semibold text-slate-900">
                        {{ profile?.name || 'Member' }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ profile?.member_number || '-' }}
                    </p>
                </div>

                <nav class="space-y-1.5">
                    <NuxtLink
                        v-for="item in navItems"
                        :key="item.to"
                        :to="item.to"
                        class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition"
                        :class="isActive(item.to)
                            ? 'bg-slate-900 text-white'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                    >
                        <svg
                            class="h-5 w-5 shrink-0"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path :d="iconPath(item.icon)" />
                        </svg>

                        <span>{{ item.label }}</span>
                    </NuxtLink>
                </nav>

                <div class="mt-8 rounded-2xl border border-slate-200 p-4">
                    <p class="text-xs font-semibold text-slate-900">
                        Aturan peminjaman
                    </p>
                    <div class="mt-3 space-y-2 text-xs text-slate-500">
                        <p>• Maksimal 2 buku</p>
                        <p>• Lama pinjam 3 hari</p>
                        <p>• Perpanjangan maksimal 1x</p>
                        <p>• Denda Rp1.000/hari/buku</p>
                    </div>
                </div>
            </aside>

            <main class="min-w-0 flex-1">
                <div class="border-b border-slate-200 bg-white lg:hidden">
                    <div class="flex gap-2 overflow-x-auto px-4 py-3">
                        <NuxtLink
                            v-for="item in navItems"
                            :key="item.to"
                            :to="item.to"
                            class="whitespace-nowrap rounded-xl px-3 py-2 text-xs font-semibold transition"
                            :class="isActive(item.to)
                                ? 'bg-slate-900 text-white'
                                : 'bg-slate-100 text-slate-600'"
                        >
                            {{ item.label }}
                        </NuxtLink>
                    </div>
                </div>

                <div class="p-4 sm:p-6 lg:p-8">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>