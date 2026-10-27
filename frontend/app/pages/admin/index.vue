<script setup lang="ts">
definePageMeta({
    middleware: 'admin',
})
</script>

<template>
    <AdminShell>
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
            <section class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-blue-800 to-slate-900 px-6 py-8 shadow-xl shadow-blue-950/10 sm:px-9 sm:py-10">
                <div
                    aria-hidden="true"
                    class="pointer-events-none absolute -right-16 -top-24 -z-10 h-72 w-72 rounded-full bg-blue-400/20 blur-2xl"
                />
                <div
                    aria-hidden="true"
                    class="pointer-events-none absolute -bottom-32 right-1/3 -z-10 h-64 w-64 rounded-full bg-cyan-300/10 blur-2xl"
                />

                <div class="max-w-3xl">
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3.5 py-1.5 text-sm font-medium text-blue-50">
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
                            <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z" />
                            <path d="m9 12 2 2 4-4" />
                        </svg>
                        Panel Administrasi
                    </div>

                    <h1 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">
                        Admin Dashboard
                    </h1>
                    <p class="mt-4 max-w-2xl text-sm leading-6 text-blue-100 sm:text-base sm:leading-7">
                        Selamat datang di panel Super Admin. Pilih menu di sidebar untuk mengelola pengguna, peran, dan hak akses.
                    </p>
                </div>
            </section>

            <section aria-labelledby="admin-shortcuts-heading">
                <div class="mb-4">
                    <p class="text-sm font-semibold text-blue-700">
                        Administrasi
                    </p>
                    <h2
                        id="admin-shortcuts-heading"
                        class="mt-1 text-xl font-bold tracking-tight text-slate-900"
                    >
                        Akses Cepat
                    </h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500">
                        Pilih bagian yang ingin Anda kelola.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <NuxtLink
                        to="/admin/users"
                        class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-lg hover:shadow-blue-950/5 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-200 sm:p-6"
                    >
                        <div class="flex items-center justify-between">
                            <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-700 ring-1 ring-blue-100 transition group-hover:bg-blue-100">
                                <svg
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
                            </span>
                            <svg
                                class="h-4 w-4 text-slate-400 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-blue-700"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M7 17 17 7M8 7h9v9" />
                            </svg>
                        </div>
                        <h3 class="mt-5 text-lg font-semibold text-slate-900">
                            Pengguna
                        </h3>
                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            Kelola akun pengguna dan akses login sistem.
                        </p>
                    </NuxtLink>

                    <NuxtLink
                        to="/admin/roles"
                        class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-lg hover:shadow-blue-950/5 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-200 sm:p-6"
                    >
                        <div class="flex items-center justify-between">
                            <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-700 ring-1 ring-blue-100 transition group-hover:bg-blue-100">
                                <svg
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
                            </span>
                            <svg
                                class="h-4 w-4 text-slate-400 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-blue-700"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M7 17 17 7M8 7h9v9" />
                            </svg>
                        </div>
                        <h3 class="mt-5 text-lg font-semibold text-slate-900">
                            Peran (Roles)
                        </h3>
                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            Atur peran untuk mengelompokkan akses pengguna.
                        </p>
                    </NuxtLink>

                    <NuxtLink
                        to="/admin/permissions"
                        class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-lg hover:shadow-blue-950/5 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-200 sm:p-6"
                    >
                        <div class="flex items-center justify-between">
                            <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-700 ring-1 ring-blue-100 transition group-hover:bg-blue-100">
                                <svg
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
                            <svg
                                class="h-4 w-4 text-slate-400 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-blue-700"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M7 17 17 7M8 7h9v9" />
                            </svg>
                        </div>
                        <h3 class="mt-5 text-lg font-semibold text-slate-900">
                            Hak Akses
                        </h3>
                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            Tinjau permission yang tersedia untuk sistem.
                        </p>
                    </NuxtLink>

                    <NuxtLink
                        to="/admin/settings"
                        class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-lg hover:shadow-blue-950/5 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-200 sm:p-6"
                    >
                        <div class="flex items-center justify-between">
                            <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-700 ring-1 ring-blue-100 transition group-hover:bg-blue-100">
                                <span class="text-xl">⚙</span>
                            </span>
                            <svg
                                class="h-4 w-4 text-slate-400 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-blue-700"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M7 17 17 7M8 7h9v9" />
                            </svg>
                        </div>
                        <h3 class="mt-5 text-lg font-semibold text-slate-900">
                            Identitas Sekolah
                        </h3>
                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            Atur nama sekolah, logo resmi, dan tema warna sistem.
                        </p>
                    </NuxtLink>
                </div>
            </section>
        </div>
    </AdminShell>
</template>
