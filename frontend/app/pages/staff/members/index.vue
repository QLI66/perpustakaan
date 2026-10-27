<script setup lang="ts">
import {
    useMembersService,
    type MemberListItem,
} from '~/services/members'

definePageMeta({
    middleware: 'staff',
})

const authStore = useAuthStore()
const membersService = useMembersService()

const members = ref<MemberListItem[]>([])
const loading = ref(true)
const errorMessage = ref('')
const successMessage = ref('')
const actionLoading = ref<number | null>(null)

const search = ref('')
const statusFilter = ref('')
const currentPage = ref(1)
const perPage = 15
const total = ref(0)
const lastPage = ref(1)

let searchTimer: ReturnType<typeof setTimeout> | null = null

const canView = computed(() =>
    authStore.hasPermission('members.view') ||
    authStore.hasRole('SUPER_ADMIN'),
)

const canCreate = computed(() =>
    authStore.hasPermission('members.create') ||
    authStore.hasRole('SUPER_ADMIN'),
)

const canUpdate = computed(() =>
    authStore.hasPermission('members.update') ||
    authStore.hasRole('SUPER_ADMIN'),
)

const canDelete = computed(() =>
    authStore.hasPermission('members.delete') ||
    authStore.hasRole('SUPER_ADMIN'),
)

const loadMembers = async () => {
    if (!canView.value) {
        loading.value = false
        return
    }

    loading.value = true
    errorMessage.value = ''

    try {
        const result = await membersService.getMembers({
            page: currentPage.value,
            per_page: perPage,
            search: search.value || undefined,
            status: statusFilter.value || undefined,
            sort: 'name',
            direction: 'asc',
        })

        members.value = result.data.items || []

        const pagination = result.data.pagination

        total.value = pagination?.total ?? 0
        lastPage.value = pagination?.last_page ?? 1
    } catch (error: unknown) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Gagal memuat data anggota.'
    } finally {
        loading.value = false
    }
}

const onSearchInput = () => {
    if (searchTimer) {
        clearTimeout(searchTimer)
    }

    searchTimer = setTimeout(() => {
        currentPage.value = 1
        loadMembers()
    }, 350)
}

const onFilterChange = () => {
    currentPage.value = 1
    loadMembers()
}

const deactivateMember = async (member: MemberListItem) => {
    if (!canUpdate.value || member.status !== 'ACTIVE') {
        return
    }

    const confirmed = window.confirm(
        `Nonaktifkan anggota "${member.name}" (${member.member_number})?`,
    )

    if (!confirmed) {
        return
    }

    actionLoading.value = member.id
    errorMessage.value = ''
    successMessage.value = ''

    try {
        await membersService.updateMember(member.id, {
            status: 'INACTIVE',
        })

        successMessage.value =
            `Anggota ${member.name} berhasil dinonaktifkan.`

        await loadMembers()
    } catch (error: unknown) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Gagal menonaktifkan anggota.'
    } finally {
        actionLoading.value = null
    }
}

const activateMember = async (member: MemberListItem) => {
    if (!canUpdate.value || member.status !== 'INACTIVE') {
        return
    }

    const confirmed = window.confirm(
        `Aktifkan kembali anggota "${member.name}" (${member.member_number})?`,
    )

    if (!confirmed) {
        return
    }

    actionLoading.value = member.id
    errorMessage.value = ''
    successMessage.value = ''

    try {
        await membersService.updateMember(member.id, {
            status: 'ACTIVE',
        })

        successMessage.value =
            `Anggota ${member.name} berhasil diaktifkan kembali.`

        await loadMembers()
    } catch (error: unknown) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Gagal mengaktifkan kembali anggota.'
    } finally {
        actionLoading.value = null
    }
}

const deleteMember = async (member: MemberListItem) => {
    if (!canDelete.value) {
        return
    }

    const confirmed = window.confirm(
        `Hapus anggota "${member.name}" (${member.member_number})?\\n\\nJika anggota memiliki histori peminjaman, penghapusan akan ditolak oleh sistem.`,
    )

    if (!confirmed) {
        return
    }

    actionLoading.value = member.id
    errorMessage.value = ''
    successMessage.value = ''

    try {
        await membersService.deleteMember(member.id)

        successMessage.value =
            `Anggota ${member.name} berhasil dihapus.`

        await loadMembers()
    } catch (error: unknown) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Gagal menghapus anggota.'
    } finally {
        actionLoading.value = null
    }
}

const goToPage = (page: number) => {
    currentPage.value = page
    loadMembers()
}

const formatDate = (date: string | null) => {
    if (!date) return '-'

    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    })
}

const getStatusClass = (status: MemberListItem['status']) => {
    switch (status) {
        case 'ACTIVE':
            return 'bg-emerald-100 text-emerald-800'

        case 'INACTIVE':
            return 'bg-slate-100 text-slate-700'

        case 'SUSPENDED':
            return 'bg-rose-100 text-rose-800'

        default:
            return 'bg-slate-100 text-slate-700'
    }
}

onMounted(loadMembers)

onUnmounted(() => {
    if (searchTimer) {
        clearTimeout(searchTimer)
    }
})
</script>

<template>
    <StaffShell active="members">
        <div class="space-y-6">
            <section
                class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"
            >
                <div
                    class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"
                >
                    <div>
                        <p class="text-sm font-medium text-slate-500">
                            Keanggotaan
                        </p>

                        <h1
                            class="mt-1 text-3xl font-bold tracking-tight text-slate-900"
                        >
                            Anggota
                        </h1>

                        <p class="mt-2 max-w-2xl text-sm text-slate-500">
                            Kelola data anggota perpustakaan sekolah.
                        </p>
                    </div>

                    <div class="flex gap-3">
                        <NuxtLink
                            v-if="canCreate"
                            to="/staff/members/import"
                            class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 shrink-0"
                        >
                            Import Excel
                        </NuxtLink>
                        <NuxtLink
                        v-if="canCreate"
                        to="/staff/members/create"
                        class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 shrink-0"
                    >
                        + Tambah Anggota
                    </NuxtLink>
                    </div>
                </div>
            </section>

            <div
                v-if="!canView"
                class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-700"
            >
                Anda tidak memiliki izin untuk melihat data anggota.
            </div>

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

            <section
                v-if="canView"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div
                    class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div>
                        <p class="text-sm font-semibold text-slate-900">
                            Daftar Anggota
                        </p>

                        <p class="mt-1 text-xs text-slate-500">
                            {{ total }} anggota ditemukan
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Cari nomor atau nama anggota..."
                            class="min-w-[260px] rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            @input="onSearchInput"
                        >

                        <select
                            v-model="statusFilter"
                            class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            @change="onFilterChange"
                        >
                            <option value="">Semua Status</option>
                            <option value="ACTIVE">Aktif</option>
                            <option value="INACTIVE">Tidak Aktif</option>
                            <option value="SUSPENDED">Ditangguhkan</option>
                        </select>
                    </div>
                </div>

                <div
                    v-if="loading"
                    class="space-y-3"
                >
                    <div
                        v-for="i in 6"
                        :key="i"
                        class="h-16 animate-pulse rounded-xl bg-slate-100"
                    />
                </div>

                <div
                    v-else-if="members.length === 0"
                    class="rounded-2xl border border-dashed border-slate-300 p-10 text-center"
                >
                    <p class="font-semibold text-slate-900">
                        Tidak ada anggota
                    </p>

                    <p class="mt-1 text-sm text-slate-500">
                        Coba ubah pencarian atau filter status.
                    </p>
                </div>

                <div
                    v-else
                    class="overflow-x-auto"
                >
                    <table class="w-full min-w-[750px] border-collapse">
                        <thead>
                            <tr
                                class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500"
                            >
                                <th class="px-4 py-3">
                                    Nomor Anggota
                                </th>

                                <th class="px-4 py-3">
                                    Nama
                                </th>

                                <th class="px-4 py-3">
                                    Jenis
                                </th>

                                <th class="px-4 py-3">
                                    Kelas / Jurusan
                                </th>

                                <th class="px-4 py-3">
                                    Bergabung
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
                                v-for="member in members"
                                :key="member.id"
                                class="border-b border-slate-100 last:border-0 hover:bg-slate-50"
                            >
                                <td
                                    class="px-4 py-4 font-mono text-sm text-slate-700"
                                >
                                    {{ member.member_number }}
                                </td>

                                <td class="px-4 py-4">
                                    <p
                                        class="font-semibold text-slate-900"
                                    >
                                        {{ member.name }}
                                    </p>

                                    <p
                                        v-if="member.identity_number"
                                        class="text-xs text-slate-500"
                                    >
                                        {{ member.identity_number }}
                                    </p>
                                </td>

                                <td class="px-4 py-4 text-sm text-slate-600">
                                    {{ member.member_type?.name || '-' }}
                                </td>

                                <td class="px-4 py-4">
                                    <p class="text-sm text-slate-700">
                                        {{ member.class_level?.name || '-' }}
                                    </p>

                                    <p
                                        v-if="member.major?.name"
                                        class="text-xs text-slate-500"
                                    >
                                        {{ member.major.name }}
                                    </p>
                                </td>

                                <td class="px-4 py-4 text-sm text-slate-600">
                                    {{ formatDate(member.joined_at) }}
                                </td>

                                <td class="px-4 py-4">
                                    <span
                                        :class="[
                                            'rounded-full px-3 py-1 text-xs font-semibold',
                                            getStatusClass(member.status),
                                        ]"
                                    >
                                        {{ member.status }}
                                    </span>
                                </td>

                                <td
                                    class="px-4 py-4"
                                >
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <NuxtLink
                                            :to="`/staff/members/${member.id}`"
                                            class="inline-block rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                        >
                                            Detail
                                        </NuxtLink>

                                        <button
    v-if="canUpdate && member.status === 'ACTIVE'"
    type="button"
    :disabled="actionLoading === member.id"
    class="rounded-lg border border-amber-200 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-50 disabled:cursor-not-allowed disabled:opacity-50"
    @click="deactivateMember(member)"
>
    {{ actionLoading === member.id ? 'Memproses...' : 'Nonaktifkan' }}
</button>

<button
    v-if="canUpdate && member.status === 'INACTIVE'"
    type="button"
    :disabled="actionLoading === member.id"
    class="rounded-lg border border-emerald-200 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50"
    @click="activateMember(member)"
>
    {{ actionLoading === member.id ? 'Memproses...' : 'Aktifkan' }}
</button>

                                        <button
                                            v-if="canDelete"
                                            type="button"
                                            :disabled="actionLoading === member.id"
                                            class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50"
                                            @click="deleteMember(member)"
                                        >
                                            {{ actionLoading === member.id ? 'Memproses...' : 'Hapus' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="lastPage > 1"
                    class="mt-5 flex flex-wrap items-center justify-center gap-2"
                >
                    <button
                        v-for="page in lastPage"
                        :key="page"
                        type="button"
                        class="h-9 min-w-9 rounded-lg border px-3 text-sm font-semibold"
                        :class="
                            page === currentPage
                                ? 'border-slate-900 bg-slate-900 text-white'
                                : 'border-slate-200 text-slate-700 hover:bg-slate-50'
                        "
                        @click="goToPage(page)"
                    >
                        {{ page }}
                    </button>
                </div>
            </section>
        </div>
    </StaffShell>
</template>
