<script setup lang="ts">
import { FetchError } from 'ofetch'
import { useAcquisitionsService } from '~/services/acquisitions'
import type { Acquisition } from '~/services/acquisitions'
import type { ApiError } from '~/types/api'

definePageMeta({
    middleware: 'staff',
})

const route = useRoute()
const { getAcquisition } = useAcquisitionsService()

const item = ref<Acquisition | null>(null)
const loading = ref(true)
const errorMessage = ref('')

const load = async () => {
    loading.value = true
    errorMessage.value = ''

    try {
        const id = route.params.id as string
        const result = await getAcquisition(id)
        item.value = result.data
    } catch (error: unknown) {
        const payload = error instanceof FetchError
            ? error.data as ApiError | undefined
            : undefined

        errorMessage.value = payload?.message || 'Gagal memuat detail akuisisi.'
    } finally {
        loading.value = false
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
                    <div class="flex items-center gap-4">
                        <NuxtLink
                            to="/staff/acquisitions"
                            class="text-sm font-medium text-slate-500 hover:text-slate-700"
                        >
                            &larr; Kembali
                        </NuxtLink>
                        <h1 class="text-xl font-bold text-slate-900">
                            Detail Akuisisi
                        </h1>
                    </div>
                </div>
            </div>

            <div v-if="loading" class="mt-8 text-center text-slate-500">
                Memuat data...
            </div>

            <div v-else-if="errorMessage" class="mt-8 rounded-lg bg-red-50 p-4">
                <p class="text-sm text-red-700">{{ errorMessage }}</p>
            </div>

            <div v-else-if="item" class="mt-8 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                <div class="px-4 py-6 sm:px-6 lg:px-8">
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-8 sm:grid-cols-2">
                        <div class="sm:col-span-1">
                            <dt class="text-sm font-medium text-slate-500">Nomor Akuisisi</dt>
                            <dd class="mt-1 text-sm text-slate-900 font-semibold">{{ item.acquisition_number }}</dd>
                        </div>
                        <div class="sm:col-span-1">
                            <dt class="text-sm font-medium text-slate-500">Tanggal</dt>
                            <dd class="mt-1 text-sm text-slate-900">{{ item.acquisition_date }}</dd>
                        </div>
                        <div class="sm:col-span-1">
                            <dt class="text-sm font-medium text-slate-500">Tipe Sumber</dt>
                            <dd class="mt-1 text-sm text-slate-900">{{ item.source_type }}</dd>
                        </div>
                        <div class="sm:col-span-1">
                            <dt class="text-sm font-medium text-slate-500">Nama Sumber</dt>
                            <dd class="mt-1 text-sm text-slate-900">{{ item.source_name || '-' }}</dd>
                        </div>
                        <div class="sm:col-span-1">
                            <dt class="text-sm font-medium text-slate-500">Nomor Referensi</dt>
                            <dd class="mt-1 text-sm text-slate-900">{{ item.reference_number || '-' }}</dd>
                        </div>
                        <div class="sm:col-span-1">
                            <dt class="text-sm font-medium text-slate-500">Total Biaya</dt>
                            <dd class="mt-1 text-sm text-slate-900">{{ item.total_amount !== null ? 'Rp ' + Number(item.total_amount).toLocaleString('id-ID') : '-' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-sm font-medium text-slate-500">Catatan</dt>
                            <dd class="mt-1 text-sm text-slate-900 whitespace-pre-wrap">{{ item.notes || '-' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="border-t border-slate-200 px-4 py-6 sm:px-6 lg:px-8">
                    <h3 class="text-lg font-medium leading-6 text-slate-900 mb-4">Item Buku</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-300">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-slate-900 sm:pl-6">Judul Buku</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-slate-900">Kuantitas</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-slate-900">Harga Satuan</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-slate-900">Catatan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <tr v-if="!item.items?.length">
                                    <td colspan="4" class="py-4 text-center text-sm text-slate-500">Tidak ada item buku.</td>
                                </tr>
                                <tr v-else v-for="bookItem in item.items" :key="bookItem.id">
                                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-slate-900 sm:pl-6">
                                        {{ bookItem.book?.title }} <span class="text-xs text-slate-500" v-if="bookItem.book?.isbn">({{ bookItem.book?.isbn }})</span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500">
                                        {{ bookItem.quantity }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500">
                                        {{ bookItem.unit_price !== null ? 'Rp ' + Number(bookItem.unit_price).toLocaleString('id-ID') : '-' }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500">
                                        {{ bookItem.notes || '-' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </StaffShell>
</template>
