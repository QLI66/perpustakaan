<script setup lang="ts">
import type {
    BookCopy,
    BookCopyStatus,
} from '~/types/book-copy'

definePageMeta({
    middleware: 'staff',
})

const {
    listCopies,
    deleteCopy,
} = useBookCopies()

const authStore = useAuthStore()

const copies = ref<BookCopy[]>([])
const loading = ref(true)
const errorMessage = ref('')
const successMessage = ref('')

const search = ref('')
const status = ref<BookCopyStatus | ''>('')

const currentPage = ref(1)
const perPage = 15
const total = ref(0)
const lastPage = ref(1)

const deletingId = ref<number | null>(null)

let timer: ReturnType<typeof setTimeout> | null = null
let copiesRequestId = 0

const canCreate = computed(() =>
    authStore.hasPermission('book_copies.create') ||
    authStore.hasRole('SUPER_ADMIN'),
)

const canUpdate = computed(() =>
    authStore.hasPermission('book_copies.update') ||
    authStore.hasRole('SUPER_ADMIN'),
)

const canDelete = computed(() =>
    authStore.hasPermission('book_copies.delete') ||
    authStore.hasRole('SUPER_ADMIN'),
)

const statusOptions = [
    {
        label: 'Semua Status',
        value: '',
    },
    {
        label: 'Tersedia',
        value: 'AVAILABLE',
    },
    {
        label: 'Dipinjam',
        value: 'BORROWED',
    },
    {
        label: 'Dipesan',
        value: 'RESERVED',
    },
    {
        label: 'Hilang',
        value: 'LOST',
    },
    {
        label: 'Rusak',
        value: 'DAMAGED',
    },
    {
        label: 'Maintenance',
        value: 'MAINTENANCE',
    },
]

const loadCopies = async (showLoading = true) => {
    const requestId = ++copiesRequestId

    if (showLoading) {
        loading.value = true
        errorMessage.value = ''
    }

    try {
        const result = await listCopies(
            currentPage.value,
            perPage,
            search.value,
            status.value,
        )

        if (requestId !== copiesRequestId) {
            return
        }

        errorMessage.value = ''
        copies.value = result.items
        total.value = result.pagination?.total ?? 0
        lastPage.value = result.pagination?.last_page ?? 1
    }
    catch (error: unknown) {
        if (requestId !== copiesRequestId) {
            return
        }

        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Data copy buku gagal dimuat.'
    }
    finally {
        if (requestId === copiesRequestId) {
            loading.value = false
        }
    }
}

const reloadFromFirstPage = () => {
    currentPage.value = 1
    loadCopies()
}

const onSearch = () => {
    if (timer) {
        clearTimeout(timer)
    }

    timer = setTimeout(() => {
        reloadFromFirstPage()
    }, 350)
}

const goToPage = (
    page: number,
) => {
    currentPage.value = page
    loadCopies()
}

const statusLabel = (
    value: string,
) => {
    return statusOptions.find(
        (item) => item.value === value,
    )?.label ?? value
}

const confirmDelete = async (
    copy: BookCopy,
) => {
    const confirmed = window.confirm(
        `Hapus copy dengan barcode "${copy.barcode}"?`,
    )

    if (!confirmed) {
        return
    }

    deletingId.value = copy.id
    errorMessage.value = ''
    successMessage.value = ''

    try {
        await deleteCopy(copy.id)

        successMessage.value =
            'Copy buku berhasil dihapus.'

        await loadCopies()
    }
    catch (error: unknown) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Copy buku gagal dihapus.'
    }
    finally {
        deletingId.value = null
    }
}

usePageRefresh(() => loadCopies(false))
onMounted(() => loadCopies())

onUnmounted(() => {
    if (timer) {
        clearTimeout(timer)
    }
})
</script>

<template>
    <StaffShell active="book-copies">
        <div class="space-y-6">

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">
                            Inventaris Fisik
                        </p>

                        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">
                            Copy Buku
                        </h1>

                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                            Kelola barcode, nomor inventaris, lokasi, kondisi,
                            dan status setiap eksemplar.
                        </p>
                    </div>

                    <NuxtLink
                        v-if="canCreate"
                        to="/staff/book-copies/create"
                        class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800"
                    >
                        + Tambah Copy
                    </NuxtLink>
                </div>
            </section>

            <div
                v-if="errorMessage"
                class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
            >
                {{ errorMessage }}
            </div>

            <div
                v-if="successMessage"
                class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700"
            >
                {{ successMessage }}
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">

                <div class="grid gap-4 lg:grid-cols-[1fr_240px_auto] lg:items-end">
                    <div>
                        <label
                            for="copy-search"
                            class="mb-2 block text-sm font-semibold text-slate-700"
                        >
                            Cari
                        </label>

                        <input
                            id="copy-search"
                            v-model="search"
                            type="search"
                            placeholder="Barcode, nomor inventaris, judul..."
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            @input="onSearch"
                        >
                    </div>

                    <div>
                        <label
                            for="copy-status"
                            class="mb-2 block text-sm font-semibold text-slate-700"
                        >
                            Status
                        </label>

                        <select
                            id="copy-status"
                            v-model="status"
                            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            @change="reloadFromFirstPage"
                        >
                            <option
                                v-for="option in statusOptions"
                                :key="option.value || 'all'"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </div>

                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="reloadFromFirstPage"
                    >
                        Refresh
                    </button>
                </div>

                <div
                    v-if="loading"
                    class="mt-5 space-y-3"
                >
                    <div
                        v-for="i in 6"
                        :key="i"
                        class="h-20 animate-pulse rounded-xl bg-slate-100"
                    />
                </div>

                <div
                    v-else-if="copies.length === 0"
                    class="mt-5 rounded-2xl border border-dashed border-slate-300 p-12 text-center"
                >
                    <p class="font-semibold text-slate-900">
                        Belum ada copy buku
                    </p>

                    <p class="mt-1 text-sm text-slate-500">
                        Tambahkan copy pertama dari buku yang sudah terdaftar.
                    </p>
                </div>

                <div
                    v-else
                    class="mt-5 overflow-x-auto"
                >
                    <table class="w-full min-w-[740px] border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-4 py-3">
                                    Buku
                                </th>

                                <th class="px-4 py-3">
                                    Barcode / Inventaris
                                </th>

                                <th class="px-4 py-3">
                                    Lokasi
                                </th>

                                <th class="px-4 py-3">
                                    Kondisi
                                </th>

                                <th class="px-4 py-3">
                                    Status
                                </th>

                                <th class="px-4 py-3 text-right">
                                    Aksi
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="copy in copies"
                                :key="copy.id"
                                class="border-b border-slate-100 last:border-0"
                            >
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-900">
                                        {{ copy.book?.title || '-' }}
                                    </p>

                                    <p class="mt-1 text-xs text-slate-500">
                                        ISBN: {{ copy.book?.isbn || '-' }}
                                    </p>
                                </td>

                                <td class="px-4 py-4">
                                    <p class="font-mono text-sm text-slate-700">{{ copy.barcode }}</p>
                                    <p v-if="copy.inventory_number" class="text-xs text-slate-500">{{ copy.inventory_number }}</p>
                                </td>

                                <td class="px-4 py-4 text-sm text-slate-600">
                                    <p class="font-medium">
                                        {{ copy.location?.name || '-' }}
                                    </p>

                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ copy.location?.code || '-' }}
                                    </p>
                                </td>

                                <td class="px-4 py-4 text-sm text-slate-600">
                                    {{ copy.condition || '-' }}
                                </td>

                                <td class="px-4 py-4">
                                    <span
                                        class="rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="copy.status === 'AVAILABLE'
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : copy.status === 'LOST'
                                                ? 'bg-rose-50 text-rose-700'
                                                : copy.status === 'DAMAGED'
                                                    ? 'bg-orange-50 text-orange-700'
                                                    : 'bg-slate-100 text-slate-700'"
                                    >
                                        {{ statusLabel(copy.status) }}
                                    </span>
                                </td>

                                <td class="px-4 py-4">
                                    <div class="flex justify-end gap-2">
                                        <NuxtLink
                                            :to="`/staff/book-copies/${copy.id}`"
                                            class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                        >
                                            Detail
                                        </NuxtLink>

                                        <NuxtLink
                                            v-if="canUpdate"
                                            :to="`/staff/book-copies/${copy.id}`"
                                            class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800"
                                        >
                                            Edit
                                        </NuxtLink>

                                        <button
                                            v-if="canDelete"
                                            type="button"
                                            class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:opacity-50"
                                            :disabled="deletingId === copy.id"
                                            @click="confirmDelete(copy)"
                                        >
                                            {{ deletingId === copy.id ? 'Menghapus...' : 'Hapus' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="lastPage > 1"
                    class="mt-5 flex flex-wrap justify-center gap-2"
                >
                    <button
                        v-for="page in lastPage"
                        :key="page"
                        type="button"
                        class="h-9 min-w-9 rounded-lg border px-3 text-sm font-semibold"
                        :class="page === currentPage
                            ? 'border-slate-900 bg-slate-900 text-white'
                            : 'border-slate-200 text-slate-700 hover:bg-slate-50'"
                        @click="goToPage(page)"
                    >
                        {{ page }}
                    </button>
                </div>

            </section>
        </div>
    </StaffShell>
</template>