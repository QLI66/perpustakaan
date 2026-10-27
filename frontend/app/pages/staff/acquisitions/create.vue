<script setup lang="ts">
import { FetchError } from 'ofetch'
import { useAcquisitionsService } from '~/services/acquisitions'
import type { AcquisitionSource, StoreAcquisitionItemPayload } from '~/services/acquisitions'
import type { ApiError } from '~/types/api'

definePageMeta({
    middleware: 'staff',
})

const router = useRouter()
const { createAcquisition } = useAcquisitionsService()
const { listBooks } = useStaffBooks()

const loading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const acquisitionDate = ref('')
const sourceType = ref<AcquisitionSource>('PURCHASE')
const sourceName = ref('')
const referenceNumber = ref('')
const notes = ref('')

const items = ref<Array<StoreAcquisitionItemPayload & { bookTitle?: string }>>([])
const searchBookText = ref('')
const bookResults = ref<Array<{ id: number, title: string, isbn: string | null }>>([])
const bookSearchError = ref('')
const bookSearchTimer = ref<ReturnType<typeof setTimeout> | null>(null)
let bookSearchRequestId = 0

const sourceTypes: AcquisitionSource[] = ['PURCHASE', 'DONATION', 'GRANT', 'GIFT', 'OTHER']

onMounted(() => {
    const today = new Date()
    const month = String(today.getMonth() + 1).padStart(2, '0')
    const day = String(today.getDate()).padStart(2, '0')
    acquisitionDate.value = `${today.getFullYear()}-${month}-${day}`
})

const onSearchBook = () => {
    if (bookSearchTimer.value) clearTimeout(bookSearchTimer.value)
    const requestId = ++bookSearchRequestId
    const query = searchBookText.value.trim()
    bookResults.value = []
    bookSearchError.value = ''

    if (!query) return

    bookSearchTimer.value = setTimeout(async () => {
        try {
            const res = await listBooks(1, 10, query)
            if (requestId !== bookSearchRequestId) return

            bookResults.value = res.items.map(b => ({ id: b.id, title: b.title, isbn: b.isbn }))
        } catch (error: unknown) {
            if (requestId !== bookSearchRequestId) return

            bookResults.value = []
            bookSearchError.value = error instanceof Error
                ? error.message
                : 'Gagal mencari buku. Coba lagi.'
        }
    }, 350)
}

const addBookItem = (book: { id: number, title: string, isbn: string | null }) => {
    if (items.value.find(i => i.book_id === book.id)) {
        errorMessage.value = `Buku "${book.title}" sudah ada di daftar.`
        setTimeout(() => { errorMessage.value = '' }, 3000)
        return
    }

    items.value.push({
        book_id: book.id,
        quantity: 1,
        unit_price: null,
        notes: '',
        bookTitle: book.title
    })
    
    bookSearchRequestId++
    if (bookSearchTimer.value) clearTimeout(bookSearchTimer.value)
    bookSearchTimer.value = null
    searchBookText.value = ''
    bookResults.value = []
    bookSearchError.value = ''
}

const removeBookItem = (index: number) => {
    items.value.splice(index, 1)
}

const submit = async () => {
    if (loading.value) return

    errorMessage.value = ''
    successMessage.value = ''
    
    if (!acquisitionDate.value) {
        errorMessage.value = 'Tanggal akuisisi wajib diisi.'
        return
    }
    
    if (items.value.length === 0) {
        errorMessage.value = 'Tambahkan setidaknya satu item buku.'
        return
    }

    loading.value = true

    try {
        const payloadItems = items.value.map(item => ({
            book_id: item.book_id,
            quantity: item.quantity,
            unit_price: item.unit_price ?? null,
            notes: item.notes || null,
        }))

        await createAcquisition({
            acquisition_date: acquisitionDate.value,
            source_type: sourceType.value,
            source_name: sourceName.value || null,
            reference_number: referenceNumber.value || null,
            notes: notes.value || null,
            items: payloadItems
        })

        successMessage.value = 'Akuisisi berhasil disimpan.'
        setTimeout(() => {
            router.push('/staff/acquisitions')
        }, 1500)
    } catch (error: unknown) {
        const payload = error instanceof FetchError
            ? error.data as ApiError | undefined
            : undefined

        errorMessage.value = payload?.message || 'Gagal menyimpan akuisisi.'
    } finally {
        loading.value = false
    }
}

onUnmounted(() => {
    bookSearchRequestId++
    if (bookSearchTimer.value) clearTimeout(bookSearchTimer.value)
})
</script>

<template>
    <StaffShell>
        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="sm:flex sm:items-center mb-8">
                <div class="sm:flex-auto">
                    <div class="flex items-center gap-4">
                        <NuxtLink
                            to="/staff/acquisitions"
                            class="text-sm font-medium text-slate-500 hover:text-slate-700"
                        >
                            &larr; Kembali
                        </NuxtLink>
                        <h1 class="text-xl font-bold text-slate-900">
                            Tambah Akuisisi Baru
                        </h1>
                    </div>
                </div>
            </div>

            <div v-if="errorMessage" class="mb-6 rounded-lg bg-red-50 p-4 shadow-sm ring-1 ring-red-200">
                <p class="text-sm text-red-700">{{ errorMessage }}</p>
            </div>
            <div v-if="successMessage" class="mb-6 rounded-lg bg-green-50 p-4 shadow-sm ring-1 ring-green-200">
                <p class="text-sm text-green-700">{{ successMessage }}</p>
            </div>

            <form @submit.prevent="submit" class="space-y-8 divide-y divide-slate-200 bg-white shadow-sm ring-1 ring-slate-200 sm:rounded-xl p-6 lg:p-8">
                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                    <div class="sm:col-span-3">
                        <label class="block text-sm font-medium leading-6 text-slate-900">Tanggal Akuisisi <span class="text-red-500">*</span></label>
                        <div class="mt-2">
                            <input v-model="acquisitionDate" type="date" required class="block w-full rounded-lg border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
                        </div>
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-sm font-medium leading-6 text-slate-900">Tipe Sumber <span class="text-red-500">*</span></label>
                        <div class="mt-2">
                            <select v-model="sourceType" required class="block w-full rounded-lg border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                <option v-for="st in sourceTypes" :key="st" :value="st">{{ st }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-sm font-medium leading-6 text-slate-900">Nama Sumber</label>
                        <div class="mt-2">
                            <input v-model="sourceName" type="text" class="block w-full rounded-lg border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
                        </div>
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-sm font-medium leading-6 text-slate-900">Nomor Referensi</label>
                        <div class="mt-2">
                            <input v-model="referenceNumber" type="text" class="block w-full rounded-lg border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
                        </div>
                    </div>

                    <div class="sm:col-span-6">
                        <label class="block text-sm font-medium leading-6 text-slate-900">Catatan</label>
                        <div class="mt-2">
                            <textarea v-model="notes" rows="3" class="block w-full rounded-lg border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"></textarea>
                        </div>
                    </div>
                </div>

                <div class="pt-8">
                    <h3 class="text-base font-semibold leading-6 text-slate-900">Item Buku <span class="text-red-500">*</span></h3>
                    <p class="mt-1 text-sm text-slate-500">Cari buku dan tambahkan ke dalam daftar akuisisi.</p>

                    <div class="mt-4 max-w-xl relative">
                        <input
                            v-model="searchBookText"
                            @input="onSearchBook"
                            type="text"
                            placeholder="Cari judul buku atau ISBN..."
                            class="block w-full rounded-lg border-0 py-1.5 pl-3 pr-10 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                        />
                        <p v-if="bookSearchError" class="mt-2 text-sm text-red-600">
                            {{ bookSearchError }}
                        </p>
                        <div v-if="bookResults.length" class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                            <div
                                v-for="book in bookResults"
                                :key="book.id"
                                @click="addBookItem(book)"
                                class="relative cursor-pointer select-none py-2 pl-3 pr-9 text-slate-900 hover:bg-indigo-600 hover:text-white"
                            >
                                <span class="block truncate font-medium">{{ book.title }}</span>
                                <span class="block truncate text-xs">{{ book.isbn || '-' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 overflow-hidden shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
                        <table class="min-w-full divide-y divide-slate-300">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-slate-900 sm:pl-6">Judul Buku</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-slate-900 w-24">Kuantitas</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-slate-900 w-48">Harga Satuan (Opsional)</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-slate-900">Catatan (Opsional)</th>
                                    <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                        <span class="sr-only">Hapus</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <tr v-if="items.length === 0">
                                    <td colspan="5" class="py-8 text-center text-sm text-slate-500">Belum ada item buku yang ditambahkan.</td>
                                </tr>
                                <tr v-else v-for="(item, index) in items" :key="index">
                                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-slate-900 sm:pl-6">
                                        {{ item.bookTitle }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-sm text-slate-500">
                                        <input v-model="item.quantity" type="number" min="1" required class="block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-sm text-slate-500">
                                        <input v-model="item.unit_price" type="number" min="0" class="block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-sm text-slate-500">
                                        <input v-model="item.notes" type="text" class="block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
                                    </td>
                                    <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                        <button type="button" @click="removeBookItem(index)" class="text-red-600 hover:text-red-900">
                                            Hapus
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="pt-6 flex items-center justify-end gap-x-6">
                    <NuxtLink to="/staff/acquisitions" class="text-sm font-semibold leading-6 text-slate-900">Batal</NuxtLink>
                    <button
                        type="submit"
                        :disabled="loading || items.length === 0"
                        class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50"
                    >
                        {{ loading ? 'Menyimpan...' : 'Simpan Akuisisi' }}
                    </button>
                </div>
            </form>
        </div>
    </StaffShell>
</template>
