<script setup lang="ts">
import { getApiErrorMessage } from '~/composables/useApi'
import { useAdminService } from '~/services/admin'
import type { Permission } from '~/services/admin'

definePageMeta({
    middleware: 'admin',
})

const { getPermissions } = useAdminService()

const allPermissions = ref<Permission[]>([])
const loading = ref(true)
const errorMessage = ref('')

// Group permissions by domain prefix (e.g. "books", "members")
const grouped = computed(() => {
    const groups: Record<string, Permission[]> = {}

    for (const p of allPermissions.value) {
        const key = (p.name.includes('.')
            ? p.name.split('.')[0]
            : 'other') || 'other'

        if (!groups[key]) groups[key] = []
        groups[key].push(p)
    }

    return Object.entries(groups).sort(([a], [b]) => a.localeCompare(b))
})

const load = async () => {
    loading.value = true
    errorMessage.value = ''

    try {
        const result = await getPermissions()
        allPermissions.value = result.data
    } catch (error: unknown) {
        errorMessage.value = getApiErrorMessage(
            error,
            'Gagal memuat daftar hak akses.',
        )
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    load()
})
</script>

<template>
    <AdminShell>
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <header>
                <p class="text-sm font-semibold text-blue-700">
                    Administrasi
                </p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    Hak Akses (Permissions)
                </h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                        Daftar semua hak akses yang tersedia dalam sistem, dikelompokkan berdasarkan domain.
                        Permissions dikelola melalui seeder dan tidak dapat diubah secara manual dari UI.
                </p>
            </header>

            <div v-if="errorMessage" role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4">
                <p class="text-sm leading-6 text-rose-800">{{ errorMessage }}</p>
            </div>

            <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white px-4 py-12 text-center text-sm text-slate-500 shadow-sm">
                Memuat data...
            </div>

            <div v-else-if="!errorMessage && grouped.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-blue-700">
                    <span aria-hidden="true" class="text-xl">—</span>
                </div>
                <h2 class="mt-4 text-base font-semibold text-slate-900">
                    Belum ada hak akses
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Tidak ada data hak akses untuk ditampilkan.
                </p>
            </div>

            <div v-else-if="!errorMessage" class="grid gap-5 md:grid-cols-2 2xl:grid-cols-3">
                <div
                    v-for="[group, permissions] in grouped"
                    :key="group"
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                >
                    <div class="border-b border-slate-100 bg-gradient-to-r from-blue-50/80 to-white px-4 py-4 sm:px-5">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-800">
                            {{ group.replace(/_/g, ' ') }}
                        </h2>
                    </div>
                    <ul role="list" class="divide-y divide-slate-100">
                        <li
                            v-for="permission in permissions"
                            :key="permission.id"
                            class="flex min-w-0 items-center gap-3 px-4 py-3 transition hover:bg-slate-50/80 sm:px-5"
                        >
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-blue-500" aria-hidden="true" />
                            <span class="min-w-0 break-all font-mono text-sm text-slate-700">{{ permission.name }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AdminShell>
</template>
