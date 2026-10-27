<script setup lang="ts">
definePageMeta({
    middleware: 'staff',
})

const authStore = useAuthStore()

const modules = computed(() => {
    const items = [
        {
            key: 'books',
            title: 'Buku',
            description: 'Tambah, ubah, lihat, dan kelola data bibliografi.',
            to: '/staff/books',
            permission: 'books.view',
            icon: '▤',
        },
        {
            key: 'book-copies',
            title: 'Copy Buku',
            description: 'Kelola barcode, inventaris, lokasi, dan kondisi fisik.',
            to: '/staff/book-copies',
            permission: 'book_copies.view',
            icon: '▥',
        },
        {
            key: 'members',
            title: 'Anggota',
            description: 'Kelola data anggota dan status keanggotaan.',
            to: '/staff/members',
            permission: 'members.view',
            icon: '♙',
        },
    ]

    return items.filter((item) =>
        authStore.hasPermission(item.permission) ||
        authStore.hasRole('SUPER_ADMIN'),
    )
})

const workflow = [
    {
        number: '01',
        title: 'Tambah Buku',
        description: 'Buat data bibliografi buku baru.',
        to: '/staff/books/create',
    },
    {
        number: '02',
        title: 'Tambah Copy',
        description: 'Tambahkan copy fisik dan barcode.',
        to: '/staff/book-copies',
    },
    {
        number: '03',
        title: 'Kelola Anggota',
        description: 'Daftarkan dan kelola anggota perpustakaan.',
        to: '/staff/members',
    },
    {
        number: '04',
        title: 'Siap Dipinjam',
        description: 'Setelah copy tersedia, buku siap masuk proses peminjaman.',
        to: '/staff/book-copies',
    },
]
</script>

<template>
    <StaffShell active="dashboard">
        <div class="space-y-6">

            <!-- HERO -->
            <section class="rounded-3xl bg-slate-900 p-6 text-white shadow-sm sm:p-8">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">

                    <div>
                        <p class="text-sm font-medium text-slate-300">
                            Portal Petugas
                        </p>

                        <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                            Dashboard Staff
                        </h1>

                        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">
                            Kelola buku, copy fisik, anggota, dan operasional
                            perpustakaan dari satu tempat.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-700 bg-slate-800 px-5 py-4">
                        <p class="text-xs uppercase tracking-wide text-slate-400">
                            Login sebagai
                        </p>

                        <p class="mt-1 font-semibold">
                            {{ authStore.user?.name || 'Staff' }}
                        </p>

                        <p class="mt-1 text-xs text-slate-400">
                            {{ authStore.roles.join(', ') || 'LIBRARY_STAFF' }}
                        </p>
                    </div>

                </div>
            </section>

            <!-- QUICK ACCESS -->
            <section>
                <div>
                    <p class="text-sm font-medium text-slate-500">
                        Akses Cepat
                    </p>

                    <h2 class="mt-1 text-xl font-bold text-slate-900">
                        Operasional Perpustakaan
                    </h2>
                </div>

                <div
                    v-if="modules.length"
                    class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                >
                    <NuxtLink
                        v-for="module in modules"
                        :key="module.key"
                        :to="module.to"
                        class="group rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:border-slate-300 hover:shadow-md"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-xl text-slate-700">
                                {{ module.icon }}
                            </div>

                            <span class="text-slate-400 transition group-hover:translate-x-1">
                                →
                            </span>
                        </div>

                        <h3 class="mt-5 text-lg font-bold text-slate-900">
                            {{ module.title }}
                        </h3>

                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            {{ module.description }}
                        </p>

                        <p class="mt-5 text-sm font-semibold text-slate-900">
                            Buka modul
                        </p>
                    </NuxtLink>
                </div>

                <div
                    v-else
                    class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-5"
                >
                    <p class="font-semibold text-amber-900">
                        Belum ada modul yang dapat diakses.
                    </p>

                    <p class="mt-1 text-sm text-amber-700">
                        Periksa permission akun Staff pada backend.
                    </p>
                </div>
            </section>

            <!-- WORKFLOW -->
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Alur Kerja
                    </p>

                    <h2 class="mt-1 text-xl font-bold text-slate-900">
                        Dari Buku Baru sampai Siap Dipinjam
                    </h2>

                    <p class="mt-2 text-sm text-slate-500">
                        Klik langkah di bawah untuk langsung masuk ke modul terkait.
                    </p>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <NuxtLink
                        v-for="step in workflow"
                        :key="step.number"
                        :to="step.to"
                        class="group rounded-2xl border border-slate-200 bg-slate-50 p-5 transition hover:border-slate-300 hover:bg-white hover:shadow-sm"
                    >
                        <div class="flex items-start gap-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-xs font-bold text-white">
                                {{ step.number }}
                            </div>

                            <div class="min-w-0">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="font-bold text-slate-900">
                                        {{ step.title }}
                                    </h3>

                                    <span class="text-slate-400 transition group-hover:translate-x-1">
                                        →
                                    </span>
                                </div>

                                <p class="mt-1 text-sm leading-6 text-slate-500">
                                    {{ step.description }}
                                </p>
                            </div>
                        </div>
                    </NuxtLink>
                </div>
            </section>

            <!-- BASIC FLOW -->
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="grid gap-4 md:grid-cols-4">

                    <div class="rounded-xl bg-slate-50 p-5">
                        <p class="text-xs font-semibold text-slate-500">
                            01
                        </p>

                        <p class="mt-2 font-semibold text-slate-900">
                            Data Buku
                        </p>

                        <p class="mt-1 text-xs leading-5 text-slate-500">
                            Bibliografi buku dibuat.
                        </p>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-5">
                        <p class="text-xs font-semibold text-slate-500">
                            02
                        </p>

                        <p class="mt-2 font-semibold text-slate-900">
                            Copy Fisik
                        </p>

                        <p class="mt-1 text-xs leading-5 text-slate-500">
                            Barcode dan inventaris ditambahkan.
                        </p>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-5">
                        <p class="text-xs font-semibold text-slate-500">
                            03
                        </p>

                        <p class="mt-2 font-semibold text-slate-900">
                            AVAILABLE
                        </p>

                        <p class="mt-1 text-xs leading-5 text-slate-500">
                            Copy siap dipinjam.
                        </p>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-5">
                        <p class="text-xs font-semibold text-slate-500">
                            04
                        </p>

                        <p class="mt-2 font-semibold text-slate-900">
                            Peminjaman
                        </p>

                        <p class="mt-1 text-xs leading-5 text-slate-500">
                            Akan kita bangun pada Task 35.
                        </p>
                    </div>

                </div>
            </section>

        </div>
    </StaffShell>
</template>