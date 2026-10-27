<script setup lang="ts">
import { useSchoolIdentityStore } from '~/stores/schoolIdentity'

interface Props {
    active?: string
}

withDefaults(
    defineProps<Props>(),
    {
        active: 'dashboard',
    },
)

const authStore = useAuthStore()
const identityStore = useSchoolIdentityStore()
const { apiFetch } = useApi()
const toast = useToast()

onMounted(() => {
    identityStore.fetchIdentity()
})

const mobileOpen = ref(false)

const menuItems = computed(() => {
    const items = [
        {
            key: 'dashboard',
            label: 'Dashboard',
            to: '/staff',
            icon: '⌂',
            permission: 'dashboard.view',
        },
        {
            key: 'books',
            label: 'Buku',
            to: '/staff/books',
            icon: '▤',
            permission: 'books.view',
        },
        {
            key: 'book-copies',
            label: 'Copy Buku',
            to: '/staff/book-copies',
            icon: '▥',
            permission: 'book_copies.view',
        },
        {
            key: 'members',
            label: 'Anggota',
            to: '/staff/members',
            icon: '♙',
            permission: 'members.view',
        },
        {
            key: 'loans',
            label: 'Peminjaman',
            to: '/staff/loans',
            icon: '▣',
            permission: 'loans.view',
        },
        {
            key: 'reservations',
            label: 'Reservasi',
            to: '/staff/reservations',
            icon: '◫',
            permission: 'reservations.view',
        },
        {
            key: 'fines',
            label: 'Denda',
            to: '/staff/fines',
            icon: 'Rp',
            permission: 'fines.view',
        },
        {
            key: 'incidents',
            label: 'Insiden Buku',
            to: '/staff/incidents',
            icon: '⚠',
            permission: 'book_incidents.view',
        },
        {
            key: 'finance',
            label: 'Keuangan',
            to: '/staff/finance',
            icon: '$',
            permission: 'dashboard.view',
        },
        {
            key: 'stock-opnames',
            label: 'Stock Opname',
            to: '/staff/stock-opnames',
            icon: 'S',
            permission: 'stock_opnames.view',
        },
        {
            key: 'acquisitions',
            label: 'Akuisisi',
            to: '/staff/acquisitions',
            icon: 'A',
            permission: 'acquisitions.view',
        },
        {
            key: 'reports',
            label: 'Laporan',
            to: '/staff/reports',
            icon: '▥',
            permission: 'dashboard.view', 
        },
    ]

    return items.filter((item) => {
        if (item.key === 'dashboard') {
            return (
                authStore.hasPermission(item.permission) ||
                authStore.hasRole('SUPER_ADMIN')
            )
        }

        return authStore.hasPermission(item.permission)
    })
})

const userName = computed(() => {
    return authStore.user?.name || 'Staff'
})

const userRoles = computed(() => {
    return authStore.roles.join(', ')
})

const executeLogout = async (): Promise<void> => {
    try {
        if (authStore.token) {
            await apiFetch<null>(
                '/auth/logout',
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        Authorization: `Bearer ${authStore.token}`,
                    },
                },
            )
        }
    }
    catch {
        // Tetap logout secara lokal.
    }
    finally {
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
    <div class="min-h-screen bg-slate-100 text-slate-900">
        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-[1500px] items-center justify-between px-4 sm:px-6">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 p-2 text-slate-700 lg:hidden"
                        aria-label="Buka menu"
                        @click="mobileOpen = true"
                    >
                        ☰
                    </button>

                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-xl text-lg text-white shadow-sm"
                            :style="{ backgroundColor: identityStore.themeColor || '#0f172a' }"
                        >
                            <img
                                v-if="identityStore.hasCustomLogo"
                                :src="identityStore.resolvedLogoUrl || ''"
                                alt="Logo"
                                class="h-full w-full object-contain p-1"
                            >
                            <span v-else>▣</span>
                        </div>

                        <div>
                            <p class="text-sm font-bold text-slate-900">
                                {{ identityStore.schoolName }}
                            </p>

                            <p class="text-xs text-slate-500">
                                Portal Petugas
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="hidden text-right sm:block">
                        <p class="text-sm font-semibold text-slate-900">
                            {{ userName }}
                        </p>

                        <p class="text-xs text-slate-500">
                            {{ userRoles || 'LIBRARY_STAFF' }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="executeLogout"
                    >
                        Keluar
                    </button>
                </div>
            </div>
        </header>

        <div class="mx-auto flex max-w-[1500px]">
            <aside class="hidden w-64 shrink-0 border-r border-slate-200 bg-white lg:block">
                <div class="sticky top-16 p-4">
                    <div class="mb-5 rounded-2xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Akun
                        </p>

                        <p class="mt-2 font-bold text-slate-900">
                            {{ userName }}
                        </p>

                        <p class="mt-1 text-xs text-slate-500">
                            {{ authStore.user?.email || '-' }}
                        </p>
                    </div>

                    <nav class="space-y-1">
                        <NuxtLink
                            v-for="item in menuItems"
                            :key="item.key"
                            :to="item.to"
                            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition"
                            :class="active === item.key
                                ? 'bg-slate-900 text-white'
                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                        >
                            <span class="w-5 text-center">
                                {{ item.icon }}
                            </span>

                            {{ item.label }}
                        </NuxtLink>
                    </nav>

                    <div class="mt-8 rounded-2xl border border-slate-200 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Operasional
                        </p>

                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            Kelola buku, copy fisik, anggota, dan transaksi
                            perpustakaan dari satu portal.
                        </p>
                    </div>
                </div>
            </aside>

            <Transition name="fade">
                <div
                    v-if="mobileOpen"
                    class="fixed inset-0 z-50 bg-slate-950/40 lg:hidden"
                    @click.self="mobileOpen = false"
                >
                    <aside class="h-full w-[85%] max-w-sm bg-white p-4 shadow-2xl">
                        <div class="flex items-center justify-between">
                            <p class="font-bold text-slate-900">
                                Menu
                            </p>

                            <button
                                type="button"
                                class="rounded-lg p-2 text-slate-500"
                                @click="mobileOpen = false"
                            >
                                ✕
                            </button>
                        </div>

                        <nav class="mt-5 space-y-1">
                            <NuxtLink
                                v-for="item in menuItems"
                                :key="item.key"
                                :to="item.to"
                                class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium"
                                :class="active === item.key
                                    ? 'bg-slate-900 text-white'
                                    : 'text-slate-600 hover:bg-slate-100'"
                                @click="mobileOpen = false"
                            >
                                <span class="w-5 text-center">
                                    {{ item.icon }}
                                </span>

                                {{ item.label }}
                            </NuxtLink>
                        </nav>
                    </aside>
                </div>
            </Transition>

            <main class="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>