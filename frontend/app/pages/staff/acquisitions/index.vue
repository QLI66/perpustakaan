<script setup lang="ts">
import { FetchError } from 'ofetch'
import { useAcquisitionsService } from '~/services/acquisitions'
import type { Acquisition } from '~/services/acquisitions'
import type { ApiError } from '~/types/api'

definePageMeta({
    middleware: 'staff',
})

const authStore = useAuthStore()
const { getAcquisitions, deleteAcquisition } = useAcquisitionsService()

const items = ref<Acquisition[]>([])
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

const canCreate = computed(() =>
    authStore.hasPermission('acquisitions.create') ||
    authStore.hasRole('SUPER_ADMIN'),
)

const canDelete = computed(() =>
    authStore.hasPermission('acquisitions.delete') ||
    authStore.hasRole('SUPER_ADMIN'),
)

const load = async () => {
    loading.value = true
    errorMessage.value = ''

    try {
        const result = await getAcquisitions({
            page: currentPage.value,
            per_page: perPage,
            search: search.value || undefined,
        })

        items.value = result.data.items
        total.value = result.data.pagination.total
        lastPage.value = result.data.pagination.last_page
    } catch (error: unknown) {
        const payload = error instanceof FetchError
            ? error.data as ApiError | undefined
            : undefined

        errorMessage.value = payload?.message || 'Gagal memuat data akuisisi.'
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

const confirmDelete = async (acq: Acquisition) => {
    const confirmed = window.confirm(
        `Hapus Akuisisi ${acq.acquisition_number}? Jika terdapat buku yang telah tercatat sebagai copy, penghapusan akan gagal.`,
    )
    if (!confirmed) return

    actionLoading.value = acq.id
    errorMessage.value = ''
    successMessage.value = ''

    try {
        await deleteAcquisition(acq.id)
        successMessage.value = `Akuisisi ${acq.acquisition_number} berhasil dihapus.`
        if (items.value.length === 1 && currentPage.value > 1) {
            currentPage.value--
        }
        await load()
    } catch (error: unknown) {
        const payload = error instanceof FetchError
            ? error.data as ApiError | undefined
            : undefined

        errorMessage.value = payload?.message || 'Gagal menghapus akuisisi.'
    } finally {
        actionLoading.value = null
    }
}

onMounted(() => {
    load()
})
</script>

<template>
    <StaffShell>
        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="sm:flex sm:items-center">
                <div class="sm:flex-auto">
                    <h1 class="text-xl font-bold text-slate-900">
                        Pengadaan / Akuisisi
                    </h1>
                    <p class="mt-2 text-sm text-slate-500">
                        Kelola data pengadaan buku (pembelian, donasi, hadiah, dll).
                    </p>
                </div>
                <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
                    <NuxtLink
                        v-if="canCreate"
                        to="/staff/acquisitions/create"
                        class="block rounded-lg bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                    >
                        + Tambah Akuisisi
                    </NuxtLink>
                </div>
            </div>

            <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="relative max-w-sm flex-1">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari nomor pengadaan..."
                        class="block w-full rounded-lg border-0 py-1.5 pl-3 pr-10 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                        @input="onSearchInput"
                    />
                </div>
            </div>

            <div v-if="errorMessage" class="mt-4 rounded-lg bg-red-50 p-4">
                <div class="flex">
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Error</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <p>{{ errorMessage }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="successMessage" class="mt-4 rounded-lg bg-green-50 p-4">
                <div class="flex">
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-green-800">Berhasil</h3>
                        <div class="mt-2 text-sm text-green-700">
                            <p>{{ successMessage }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-8 flow-root">
                <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
                            <table class="min-w-full divide-y divide-slate-300">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-slate-900 sm:pl-6">
                                            No. Akuisisi
                                        </th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-slate-900">
                                            Tanggal
                                        </th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-slate-900">
                                            Sumber
                                        </th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-slate-900">
                                            Total Biaya
                                        </th>
                                        <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                            <span class="sr-only">Aksi</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 bg-white">
                                    <tr v-if="loading">
                                        <td colspan="5" class="py-10 text-center text-sm text-slate-500">
                                            Memuat data...
                                        </td>
                                    </tr>
                                    <tr v-else-if="items.length === 0">
                                        <td colspan="5" class="py-10 text-center text-sm text-slate-500">
                                            Data tidak ditemukan.
                                        </td>
                                    </tr>
                                    <tr v-else v-for="item in items" :key="item.id">
                                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-slate-900 sm:pl-6">
                                            {{ item.acquisition_number }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500">
                                            {{ item.acquisition_date }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500">
                                            <span class="font-medium text-slate-900">{{ item.source_type }}</span>
                                            <div v-if="item.source_name" class="text-xs">{{ item.source_name }}</div>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500">
                                            {{ item.total_amount !== null ? 'Rp ' + Number(item.total_amount).toLocaleString('id-ID') : '-' }}
                                        </td>
                                        <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                            <NuxtLink
                                                :to="`/staff/acquisitions/${item.id}`"
                                                class="text-indigo-600 hover:text-indigo-900"
                                            >
                                                Lihat<span class="sr-only">, {{ item.acquisition_number }}</span>
                                            </NuxtLink>
                                            
                                            <button
                                                v-if="canDelete"
                                                class="ml-4 text-red-600 hover:text-red-900 disabled:opacity-50"
                                                :disabled="actionLoading === item.id"
                                                @click="confirmDelete(item)"
                                            >
                                                Hapus<span class="sr-only">, {{ item.acquisition_number }}</span>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="lastPage > 1" class="mt-6 flex items-center justify-between border-t border-slate-200 bg-white px-4 py-3 sm:px-6 rounded-lg shadow-sm">
                <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-slate-700">
                            Total <span class="font-medium">{{ total }}</span> hasil
                        </p>
                    </div>
                    <div>
                        <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                            <button
                                :disabled="currentPage === 1"
                                class="relative inline-flex items-center rounded-l-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 disabled:opacity-50"
                                @click="goToPage(currentPage - 1)"
                            >
                                <span class="sr-only">Sebelumnya</span>
                                &larr;
                            </button>
                            <span class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-900 ring-1 ring-inset ring-slate-300">
                                {{ currentPage }} / {{ lastPage }}
                            </span>
                            <button
                                :disabled="currentPage === lastPage"
                                class="relative inline-flex items-center rounded-r-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 disabled:opacity-50"
                                @click="goToPage(currentPage + 1)"
                            >
                                <span class="sr-only">Selanjutnya</span>
                                &rarr;
                            </button>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </StaffShell>
</template>
