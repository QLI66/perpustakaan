<script setup lang="ts">
import { getApiErrorMessage } from '~/composables/useApi'
import { useAdminService } from '~/services/admin'
import type { Role } from '~/services/admin'

definePageMeta({
    middleware: 'admin',
})

const { getRoles, deleteRole } = useAdminService()

const items = ref<Role[]>([])
const loading = ref(true)
const search = ref('')
const errorMessage = ref('')
const successMessage = ref('')
const actionLoading = ref<number | null>(null)

let searchTimer: ReturnType<typeof setTimeout> | null = null

const load = async () => {
    loading.value = true
    errorMessage.value = ''

    try {
        const result = await getRoles({ search: search.value || undefined })
        items.value = result.data
    } catch (error: unknown) {
        errorMessage.value = getApiErrorMessage(
            error,
            'Gagal memuat data peran.',
        )
    } finally {
        loading.value = false
    }
}

const onSearchInput = () => {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        load()
    }, 350)
}

const confirmDelete = async (role: Role) => {
    if (['SUPER_ADMIN', 'LIBRARY_STAFF', 'MEMBER'].includes(role.name)) {
        alert('Peran inti sistem tidak dapat dihapus.')
        return
    }

    const confirmed = window.confirm(
        `Hapus peran "${role.name}"?`,
    )
    if (!confirmed) return

    actionLoading.value = role.id
    errorMessage.value = ''
    successMessage.value = ''

    try {
        await deleteRole(role.id)
        successMessage.value = `Peran ${role.name} berhasil dihapus.`
        await load()
    } catch (error: unknown) {
        errorMessage.value = getApiErrorMessage(
            error,
            'Gagal menghapus peran.',
        )
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
                        Manajemen Peran (Roles)
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                        Kelola peran yang tersedia dalam sistem beserta daftar penggunanya.
                    </p>
                </div>
                <NuxtLink
                    to="/admin/roles/create"
                    class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-200 sm:w-auto"
                >
                    <span aria-hidden="true" class="text-lg leading-none">+</span>
                    Tambah Peran
                </NuxtLink>
            </header>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <label for="role-search" class="mb-2 block text-sm font-semibold text-slate-700">
                    Cari peran
                </label>
                <div class="max-w-xl">
                    <input
                        id="role-search"
                        v-model="search"
                        type="text"
                        placeholder="Cari nama peran..."
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
                <div class="border-b border-slate-100 px-4 py-4 sm:px-6">
                    <h2 class="font-semibold text-slate-900">
                        Daftar Peran
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="whitespace-nowrap py-3.5 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 sm:pl-6">Nama Peran</th>
                            <th scope="col" class="whitespace-nowrap px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Jumlah Hak Akses</th>
                            <th class="relative py-3.5 pl-3 pr-4 sm:pr-6"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="loading">
                            <td colspan="3" class="px-4 py-12 text-center text-sm text-slate-500">Memuat data...</td>
                        </tr>
                        <tr v-else-if="items.length === 0">
                            <td colspan="3" class="px-4 py-12 text-center text-sm text-slate-500">Data tidak ditemukan.</td>
                        </tr>
                        <tr v-for="item in items" v-else :key="item.id" class="transition hover:bg-slate-50/80">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-slate-900 sm:pl-6">
                                <span class="inline-flex items-center rounded-full border border-blue-100 bg-blue-50 px-3 py-1 text-blue-800">
                                    {{ item.name }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-600">
                                <span class="font-semibold text-slate-900">{{ item.permissions?.length || 0 }}</span>
                                permission(s)
                            </td>
                            <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                <NuxtLink :to="`/admin/roles/${item.id}`" class="rounded-lg px-2 py-1.5 font-semibold text-blue-700 transition hover:bg-blue-50 hover:text-blue-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-100">Lihat</NuxtLink>
                                <button v-if="!['SUPER_ADMIN', 'LIBRARY_STAFF', 'MEMBER'].includes(item.name)" class="ml-2 rounded-lg px-2 py-1.5 font-semibold text-rose-600 transition hover:bg-rose-50 hover:text-rose-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-rose-100 disabled:cursor-not-allowed disabled:opacity-50 sm:ml-3" :disabled="actionLoading === item.id" @click="confirmDelete(item)">
                                    Hapus
                                </button>
                            </td>
                        </tr>
                    </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AdminShell>
</template>
