<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'
import { useSchoolIdentityStore } from '~/stores/schoolIdentity'

const authStore = useAuthStore()
const identityStore = useSchoolIdentityStore()
const route = useRoute()
const { apiFetch } = useApi()
const toast = useToast()

onMounted(() => {
    identityStore.fetchIdentity()
})

const isSidebarOpen = ref(false)

const modules = [
    {
        key: 'dashboard',
        label: 'Dashboard',
        to: '/admin',
        icon: '⌂',
    },
    {
        key: 'users',
        label: 'Pengguna',
        to: '/admin/users',
        icon: 'U',
    },
    {
        key: 'roles',
        label: 'Peran (Roles)',
        to: '/admin/roles',
        icon: 'R',
    },
    {
        key: 'permissions',
        label: 'Hak Akses',
        to: '/admin/permissions',
        icon: 'P',
    },
    {
        key: 'settings',
        label: 'Identitas Sekolah',
        to: '/admin/settings',
        icon: '⚙',
    },
]

const handleLogout = async () => {
    try {
        await apiFetch('/auth/logout', {
            method: 'POST',
            headers: {
                Authorization: `Bearer ${authStore.token}`,
            },
        })
    } catch {
        // Ignored
    } finally {
        authStore.clearAuth()
        toast.add({
            severity: 'info',
            summary: 'Logout',
            detail: 'Anda telah keluar.',
            life: 3000,
        })
        await navigateTo('/login')
    }
}
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <div
            v-if="isSidebarOpen"
            class="fixed inset-0 z-40 bg-slate-950/40 backdrop-blur-sm lg:hidden"
            @click="isSidebarOpen = false"
        />

        <aside
            id="admin-sidebar"
            class="fixed inset-y-0 left-0 z-50 flex w-72 transform flex-col border-r border-slate-200 bg-white shadow-xl shadow-slate-950/5 transition-transform duration-300 ease-in-out lg:translate-x-0 lg:shadow-none"
            :class="[isSidebarOpen ? 'translate-x-0' : '-translate-x-full']"
        >
            <div class="flex h-20 shrink-0 items-center justify-between border-b border-slate-100 px-5">
                <div class="flex min-w-0 items-center gap-3">
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-2xl text-white shadow-md shadow-blue-900/20"
                        :style="{ backgroundColor: identityStore.themeColor }"
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
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path d="M12 7v14" />
                            <path d="M3 18V5a2 2 0 0 1 2-2h3a4 4 0 0 1 4 4 4 4 0 0 1 4-4h3a2 2 0 0 1 2 2v13h-5a4 4 0 0 0-4 3 4 4 0 0 0-4-3H3Z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold text-slate-900">
                            {{ identityStore.schoolName }}
                        </p>
                        <p class="mt-0.5 text-xs font-medium" :style="{ color: identityStore.themeColor }">
                            Admin Panel
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-100 lg:hidden"
                    aria-label="Tutup menu"
                    @click="isSidebarOpen = false"
                >
                    <svg
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        aria-hidden="true"
                    >
                        <path d="m18 6-12 12M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <nav aria-label="Navigasi admin" class="flex-1 overflow-y-auto px-4 py-6">
                <p class="mb-3 px-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
                    Menu utama
                </p>
                <ul role="list" class="flex flex-col gap-1.5">
                    <li
                        v-for="item in modules"
                        :key="item.key"
                    >
                        <NuxtLink
                            :to="item.to"
                            class="group relative flex min-h-12 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-100"
                            :class="route.path === item.to || (item.to !== '/admin' && route.path.startsWith(item.to + '/'))
                                ? 'bg-blue-50 text-blue-800 ring-1 ring-inset ring-blue-100 before:absolute before:bottom-2 before:left-0 before:top-2 before:w-1 before:rounded-r-full before:bg-blue-700'
                                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                            :aria-current="route.path === item.to || (item.to !== '/admin' && route.path.startsWith(item.to + '/')) ? 'page' : undefined"
                            @click="isSidebarOpen = false"
                        >
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl transition"
                                :class="route.path === item.to || (item.to !== '/admin' && route.path.startsWith(item.to + '/'))
                                    ? 'bg-white text-blue-700 shadow-sm shadow-blue-900/5'
                                    : 'bg-slate-100 text-slate-500 group-hover:bg-white group-hover:text-blue-700'"
                            >
                                <svg
                                    v-if="item.key === 'dashboard'"
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    aria-hidden="true"
                                >
                                    <path d="m3 10 9-7 9 7" />
                                    <path d="M5 9v11h14V9M9 20v-6h6v6" />
                                </svg>
                                <svg
                                    v-else-if="item.key === 'users'"
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    aria-hidden="true"
                                >
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                    <circle cx="10" cy="7" r="4" />
                                    <path d="M20 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
                                </svg>
                                <svg
                                    v-else-if="item.key === 'roles'"
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    aria-hidden="true"
                                >
                                    <rect x="3" y="5" width="18" height="14" rx="2" />
                                    <circle cx="9" cy="11" r="2" />
                                    <path d="M6 16a3 3 0 0 1 6 0M14 10h4M14 14h4" />
                                </svg>
                                <svg
                                    v-else
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    aria-hidden="true"
                                >
                                    <rect x="4" y="11" width="16" height="10" rx="2" />
                                    <path d="M8 11V7a4 4 0 1 1 8 0v4M12 15v2" />
                                </svg>
                            </span>
                            {{ item.label }}
                            <svg
                                v-if="route.path === item.to || (item.to !== '/admin' && route.path.startsWith(item.to + '/'))"
                                class="ml-auto h-4 w-4 text-blue-600"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="m9 18 6-6-6-6" />
                            </svg>
                        </NuxtLink>
                    </li>
                </ul>
            </nav>

            <div class="shrink-0 border-t border-slate-100 p-4">
                <div class="rounded-2xl bg-slate-50 p-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-700 text-sm font-semibold text-white ring-4 ring-white">
                            {{ authStore.user?.name?.charAt(0)?.toUpperCase() ?? 'U' }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-900">
                                {{ authStore.user?.name ?? 'Admin' }}
                            </p>
                            <p class="truncate text-xs text-slate-500">
                                {{ authStore.user?.email ?? '' }}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="mt-3 flex min-h-10 w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-600 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-100"
                        @click="handleLogout"
                    >
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path d="M10 17l5-5-5-5M15 12H3" />
                            <path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6" />
                        </svg>
                        Keluar
                    </button>
                </div>
            </div>
        </aside>

        <div class="lg:pl-72">
            <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/95 px-4 shadow-sm shadow-slate-950/5 backdrop-blur sm:px-6 lg:hidden">
                <button
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-100"
                    aria-label="Buka menu sidebar"
                    aria-controls="admin-sidebar"
                    :aria-expanded="isSidebarOpen"
                    @click="isSidebarOpen = true"
                >
                    <svg
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        aria-hidden="true"
                    >
                        <path d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-medium text-slate-500">
                        Perpustakaan Sekolah
                    </p>
                    <p class="truncate text-sm font-semibold text-slate-900">
                        Admin Panel
                    </p>
                </div>
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-50 text-sm font-semibold text-blue-800 ring-1 ring-blue-100">
                    {{ authStore.user?.name?.charAt(0)?.toUpperCase() ?? 'U' }}
                </span>
            </header>

            <main class="py-6 sm:py-8">
                <slot />
            </main>
        </div>
    </div>
</template>
