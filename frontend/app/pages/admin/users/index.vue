<script setup lang="ts">
import { useAdminService } from '~/services/admin'
import type { User } from '~/services/admin'
import type { ApiError } from '~/types/api'

definePageMeta({
    middleware: 'admin',
})

const { getUsers, deleteUser } = useAdminService()

const items = ref<User[]>([])
const loading = ref(true)
const search = ref('')
const currentPage = ref(1)
const perPage = 15
const total = ref(0)
const lastPage = ref(1)
const errorMessage = ref('')
const successMessage = ref('')
const actionLoading = ref<number | null>(null)

let searchTimer: ReturnType<typeof setTimeout> | null = null

const hasApiErrorMessage = (
    error: unknown,
): error is { data: Pick<ApiError, 'message'> } => {
    return (
        typeof error === 'object' &&
        error !== null &&
        'data' in error &&
        typeof error.data === 'object' &&
        error.data !== null &&
        'message' in error.data &&
        typeof error.data.message === 'string'
    )
}

const load = async () => {
    loading.value = true
    errorMessage.value = ''

    try {
        const result = await getUsers({
            page: currentPage.value,
            per_page: perPage,
            search: search.value || undefined,
        })

        items.value = result.data.items
        total.value = result.data.pagination.total
        lastPage.value = result.data.pagination.last_page
    } catch (error: unknown) {
        errorMessage.value = hasApiErrorMessage(error)
            ? error.data.message
            : 'Gagal memuat data pengguna.'
    } finally {
        loading.value = false
    }
}

const onSearchInput = () => {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        currentPage.value = 1
        load()
    }, 350)
}

const goToPage = (page: number) => {
    currentPage.value = page
    load()
}

const confirmDelete = async (user: User) => {
    const confirmed = window.confirm(
        `Hapus pengguna "${user.name}"? Tindakan ini tidak dapat dibatalkan.`,
    )
    if (!confirmed) return

    actionLoading.value = user.id
    errorMessage.value = ''
    successMessage.value = ''

    try {
        await deleteUser(user.id)
        successMessage.value = `Pengguna ${user.name} berhasil dihapus.`
        if (items.value.length === 1 && currentPage.value > 1) {
            currentPage.value--
        }
        await load()
    } catch (error: unknown) {
        errorMessage.value = hasApiErrorMessage(error)
            ? error.data.message
            : 'Gagal menghapus pengguna.'
    } finally {
        actionLoading.value = null
    }
}

onMounted(() => {
    load()
})
</script>

<template>
    <AdminShell>
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-blue-700">
                        Administrasi
                    </p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                        Manajemen Pengguna
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                        Kelola data pengguna, role, dan akses login sistem.
                    </p>
                </div>
                <NuxtLink
                    to="/admin/users/create"
                    class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-200 sm:w-auto"
                >
                    <span aria-hidden="true" class="text-lg leading-none">+</span>
                    Tambah Pengguna
                </NuxtLink>
            </header>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <label for="user-search" class="mb-2 block text-sm font-semibold text-slate-700">
                    Cari pengguna
                </label>
                <div class="relative max-w-xl">
                    <input
                        id="user-search"
                        v-model="search"
                        type="text"
                        placeholder="Cari nama atau email..."
                        class="block min-h-11 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 hover:border-slate-400 focus:border-blue-600 focus:ring-4 focus:ring-blue-100"
                        @input="onSearchInput"
                    >
                </div>
            </section>

            <div v-if="errorMessage" role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4">
                <p class="text-sm leading-6 text-rose-800">{{ errorMessage }}</p>
            </div>
            <div v-if="successMessage" role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-sm leading-6 text-emerald-800">{{ successMessage }}</p>
            </div>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-4 sm:px-6">
                    <div>
                        <h2 class="font-semibold text-slate-900">
                            Daftar Pengguna
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ total }} pengguna
                        </p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                                    <tr>
                                        <th scope="col" class="whitespace-nowrap py-3.5 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 sm:pl-6">Nama</th>
                                        <th scope="col" class="whitespace-nowrap px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Email</th>
                                        <th scope="col" class="whitespace-nowrap px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Peran (Roles)</th>
                                        <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6"><span class="sr-only">Aksi</span></th>
                                    </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                                    <tr v-if="loading">
                                        <td colspan="4" class="px-4 py-12 text-center text-sm text-slate-500">Memuat data...</td>
                                    </tr>
                                    <tr v-else-if="items.length === 0">
                                        <td colspan="4" class="px-4 py-12 text-center text-sm text-slate-500">Data tidak ditemukan.</td>
                                    </tr>
                                    <tr v-for="item in items" v-else :key="item.id" class="transition hover:bg-slate-50/80">
                                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-slate-900 sm:pl-6">
                                            {{ item.name }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-600">
                                            {{ item.email }}
                                        </td>
                                        <td class="px-3 py-4 text-sm text-slate-600">
                                            <div class="flex min-w-40 flex-wrap gap-1.5">
                                                <span v-for="role in item.roles" :key="role.id" class="inline-flex items-center rounded-full border border-blue-100 bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-800">
                                                    {{ role.name }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                            <NuxtLink :to="`/admin/users/${item.id}`" class="rounded-lg px-2 py-1.5 font-semibold text-blue-700 transition hover:bg-blue-50 hover:text-blue-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-100">Lihat</NuxtLink>
                                            <button
                                                class="ml-2 rounded-lg px-2 py-1.5 font-semibold text-rose-600 transition hover:bg-rose-50 hover:text-rose-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-rose-100 disabled:cursor-not-allowed disabled:opacity-50 sm:ml-3"
                                                :disabled="actionLoading === item.id"
                                                @click="confirmDelete(item)"
                                            >
                                                Hapus
                                            </button>
                                        </td>
                                    </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div v-if="lastPage > 1" class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <p class="text-center text-sm text-slate-600 sm:text-left">
                    Total <span class="font-semibold text-slate-900">{{ total }}</span> hasil
                </p>
                <nav class="flex items-center justify-center gap-2" aria-label="Pagination">
                            <button :disabled="currentPage === 1" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-200 px-3 text-sm font-medium text-slate-600 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-100 disabled:cursor-not-allowed disabled:opacity-40" @click="goToPage(currentPage - 1)">
                                Sebelumnya
                            </button>
                            <span class="min-w-20 text-center text-sm font-semibold text-slate-700">{{ currentPage }} / {{ lastPage }}</span>
                            <button :disabled="currentPage === lastPage" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-200 px-3 text-sm font-medium text-slate-600 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-100 disabled:cursor-not-allowed disabled:opacity-40" @click="goToPage(currentPage + 1)">
                                Berikutnya
                            </button>
                </nav>
            </div>
        </div>
    </AdminShell>
</template>
