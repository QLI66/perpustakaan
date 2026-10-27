<script setup lang="ts">
import { useSchoolIdentityStore } from '~/stores/schoolIdentity'

const identityStore = useSchoolIdentityStore()

onMounted(() => {
    identityStore.fetchIdentity()
})

useHead({
    title: 'Beranda',
})
</script>

<template>
    <div class="relative isolate min-h-[80vh] overflow-hidden bg-gradient-to-br from-white via-slate-50 to-blue-100/70">
        <div
            aria-hidden="true"
            class="pointer-events-none absolute -right-24 -top-32 -z-10 h-96 w-96 rounded-full bg-blue-200/40 blur-3xl"
        />
        <div
            aria-hidden="true"
            class="pointer-events-none absolute -bottom-40 left-1/4 -z-10 h-96 w-96 rounded-full bg-cyan-100/60 blur-3xl"
        />

        <section class="mx-auto grid max-w-7xl items-center gap-14 px-4 py-16 sm:px-6 sm:py-24 lg:grid-cols-[1.1fr_0.9fr] lg:gap-20 lg:px-8 lg:py-28">
            <div class="max-w-3xl">
                <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-blue-200/80 bg-white/80 px-4 py-2 text-sm font-semibold text-blue-800 shadow-sm shadow-blue-950/5">
                    <i class="pi pi-book text-sm text-blue-600" aria-hidden="true" />
                    <span>Perpustakaan Sekolah</span>
                </div>

                <h1 class="text-4xl font-bold leading-[1.12] tracking-tight text-slate-950 sm:text-5xl lg:text-6xl">
                    Kelola dan Temukan Koleksi Perpustakaan dengan Mudah
                </h1>

                <p class="mt-6 max-w-2xl text-base leading-7 text-slate-600 sm:text-lg sm:leading-8">
                    Sistem perpustakaan sekolah untuk menemukan koleksi,
                    melihat ketersediaan buku, dan mengakses informasi
                    bibliografi secara cepat.
                </p>

                <div class="mt-9 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <NuxtLink
                        to="/catalog"
                        class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-blue-700 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-900/20 transition hover:bg-blue-800 hover:shadow-xl hover:shadow-blue-900/20 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-300"
                    >
                        <i class="pi pi-book" aria-hidden="true" />
                        Jelajahi Katalog
                        <i class="pi pi-arrow-right text-xs" aria-hidden="true" />
                    </NuxtLink>

                    <NuxtLink
                        to="/login"
                        class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white/80 px-6 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-blue-200 hover:bg-white hover:text-blue-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-200"
                    >
                        <i class="pi pi-sign-in" aria-hidden="true" />
                        Login
                    </NuxtLink>
                </div>

                <div class="mt-10 flex flex-wrap gap-x-6 gap-y-3 text-sm font-medium text-slate-600">
                    <span class="inline-flex items-center gap-2">
                        <i class="pi pi-search text-blue-600" aria-hidden="true" />
                        Temukan koleksi
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <i class="pi pi-check-circle text-blue-600" aria-hidden="true" />
                        Cek ketersediaan
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <i class="pi pi-list text-blue-600" aria-hidden="true" />
                        Lihat informasi buku
                    </span>
                </div>
            </div>

            <div class="relative mx-auto w-full max-w-lg lg:ml-auto">
                <div
                    aria-hidden="true"
                    class="absolute -inset-5 rounded-[2rem] bg-gradient-to-br from-blue-200/70 via-white/50 to-cyan-100/70 blur-2xl"
                />
                <div class="relative overflow-hidden rounded-[2rem] border border-white/80 bg-white/80 p-6 shadow-2xl shadow-blue-950/10 backdrop-blur sm:p-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-blue-700">
                                Ruang untuk bertumbuh
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                Temukan bacaan berikutnya
                            </p>
                        </div>
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-50 text-blue-700">
                            <i class="pi pi-sparkles text-lg" aria-hidden="true" />
                        </div>
                    </div>

                    <div class="mt-8 flex items-end justify-center gap-3 rounded-3xl bg-gradient-to-br from-blue-50 to-slate-100 px-5 pb-6 pt-8">
                        <div class="h-36 w-20 -rotate-6 rounded-lg bg-gradient-to-b from-cyan-500 to-cyan-700 p-3 shadow-lg shadow-cyan-900/15">
                            <div class="h-full rounded border border-white/30 px-2 py-4 text-center text-white">
                                <i class="pi pi-globe text-xl" aria-hidden="true" />
                                <span class="mt-5 block h-px bg-white/70" />
                                <span class="mt-2 block h-px bg-white/40" />
                            </div>
                        </div>
                        <div class="z-10 h-44 w-24 rounded-lg bg-gradient-to-b from-blue-600 to-blue-900 p-3 shadow-xl shadow-blue-900/20">
                            <div class="h-full rounded border border-white/30 px-2 py-5 text-center text-white">
                                <i class="pi pi-book text-2xl" aria-hidden="true" />
                                <span class="mt-7 block h-px bg-white/70" />
                                <span class="mt-2 block h-px bg-white/40" />
                            </div>
                        </div>
                        <div class="h-32 w-20 rotate-6 rounded-lg bg-gradient-to-b from-amber-400 to-orange-500 p-3 shadow-lg shadow-orange-900/15">
                            <div class="h-full rounded border border-white/40 px-2 py-4 text-center text-white">
                                <i class="pi pi-lightbulb text-xl" aria-hidden="true" />
                                <span class="mt-4 block h-px bg-white/70" />
                                <span class="mt-2 block h-px bg-white/40" />
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center gap-4 rounded-2xl border border-blue-100 bg-white p-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white shadow-md shadow-blue-900/20">
                            <i class="pi pi-compass text-xl" aria-hidden="true" />
                        </div>
                        <div>
                            <p class="font-semibold text-slate-900">
                                Pengetahuan dimulai dari sini
                            </p>
                            <p class="mt-1 text-sm leading-5 text-slate-500">
                                Jelajahi beragam koleksi perpustakaan sekolah.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
