<script setup lang="ts">
definePageMeta({
    middleware: 'staff',
})

type PreviewStatus =
    | 'VALID'
    | 'NEEDS_FIX'
    | 'IGNORED'

type PreviewRow = {
    sheet: string
    line: number | null
    student_number: number | null
    nisn: string | null
    nis: string | null
    name: string | null
    class: string | null
    major: string | null
    status: PreviewStatus
    is_valid: boolean
    ignored_reason: string | null
    errors: string[]
}

type PreviewSheet = {
    name: string
    student_total: number
    valid: number
    invalid: number
    ignored: number
    mapping_status: string
}

type PreviewData = {
    total: number
    valid: number
    invalid: number
    ignored: number
    sheets: PreviewSheet[]
    rows: PreviewRow[]
}

type ImportStudent = {
    nisn: string
    nis: string
    name: string
    username: string
    sheet: string
    class: string
    major: string | null
}

type ImportResult = {
    total: number
    created: number
    failed: number
    students: ImportStudent[]
}

type EditableRow = {
    sheet: string
    line: number | null
    nisn: string
    nis: string
    name: string
}

type ExcludedRow = {
    sheet: string
    line: number
}

type ImportPayload = {
    overrides: EditableRow[]
    exclude: ExcludedRow[]
}

const authStore = useAuthStore()
const { apiFetch } = useApi()

const canCreate = computed(() =>
    authStore.hasPermission('members.create') ||
    authStore.hasRole('SUPER_ADMIN'),
)

onMounted(() => {
    if (!canCreate.value) {
        navigateTo('/staff/members')
    }
})

const fileInput = ref<HTMLInputElement | null>(null)
const selectedFile = ref<File | null>(null)
const isUploading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const previewData = ref<PreviewData | null>(null)
const importResult = ref<ImportResult | null>(null)

const editingKey = ref<string | null>(null)
const excludedKeys = ref<string[]>([])
const showIgnoredRows = ref(false)
const activeSheet = ref('ALL')

const editDraft = reactive<EditableRow>({
    sheet: '',
    line: null,
    nisn: '',
    nis: '',
    name: '',
})

const rowKey = (row: PreviewRow): string =>
    `${row.sheet}|${row.line ?? ''}`

const isExcluded = (row: PreviewRow): boolean =>
    excludedKeys.value.includes(rowKey(row))
const visibleRows = computed(() => {
    const rows = previewData.value?.rows ?? []

    return showIgnoredRows.value
        ? rows
        : rows.filter(
            (row) => row.status !== 'IGNORED',
        )
})

const filteredRows = computed(() => {
    if (activeSheet.value === 'ALL') {
        return visibleRows.value
    }

    return visibleRows.value.filter(
        (row) => row.sheet === activeSheet.value,
    )
})

const displayNumberMap = computed(() => {
    const map = new Map<string, number>()
    const counters = new Map<string, number>()

    for (const row of visibleRows.value) {
        if (row.status === 'IGNORED') {
            continue
        }

        const current = counters.get(row.sheet) ?? 0
        const next = current + 1

        counters.set(row.sheet, next)
        map.set(rowKey(row), next)
    }

    return map
})

const displayNumber = (row: PreviewRow): string => {
    if (row.status === 'IGNORED') {
        return '-'
    }

    return String(
        displayNumberMap.value.get(rowKey(row)) ?? '-',
    )
}

const onFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement
    const file = target.files?.[0] ?? null

    selectedFile.value = file
    previewData.value = null
    importResult.value = null
    errorMessage.value = ''
    successMessage.value = ''
    editingKey.value = null
    excludedKeys.value = []
    showIgnoredRows.value = false
    activeSheet.value = 'ALL'
}

const buildImportPayload = (): ImportPayload => {
    if (!previewData.value) {
        return {
            overrides: [],
            exclude: [],
        }
    }

    return {
        overrides: previewData.value.rows
            .filter((row) => row.status !== 'IGNORED')
            .map((row) => ({
                sheet: row.sheet,
                line: row.line,
                nisn: row.nisn ?? '',
                nis: row.nis ?? '',
                name: row.name ?? '',
            })),
        exclude: excludedKeys.value.map((key) => {
            const separator = key.lastIndexOf('|')

            return {
                sheet: key.slice(0, separator),
                line: Number(
                    key.slice(separator + 1),
                ),
            }
        }),
    }
}

const validateFile = async () => {
    if (!selectedFile.value) {
        errorMessage.value =
            'Silakan pilih file Excel terlebih dahulu.'
        return
    }

    isUploading.value = true
    errorMessage.value = ''
    successMessage.value = ''

    const formData = new FormData()
    formData.append('file', selectedFile.value)

    try {
        const response =
            await apiFetch<PreviewData>(
                '/members/import/preview',
                {
                    method: 'POST',
                    body: formData,
                    headers: {
                        Authorization:
                            `Bearer ${authStore.token}`,
                    },
                },
            )

        previewData.value =
            response.data as PreviewData

        editingKey.value = null
        excludedKeys.value = []
        showIgnoredRows.value = false
        activeSheet.value = 'ALL'

        const data =
            response.data as PreviewData

        const sheetCount =
            data.sheets?.length ?? 0

        successMessage.value =
            `Validasi selesai. ${data.total} data siswa dari ` +
            `${sheetCount} sheet: ${data.valid} valid, ` +
            `${data.invalid} perlu diperbaiki, ` +
            `${data.ignored} baris diabaikan.`
    } catch (err: unknown) {
        const e = err as {
            data?: {
                message?: string
            }
        }

        errorMessage.value =
            e?.data?.message ||
            'Gagal melakukan validasi file.'
    } finally {
        isUploading.value = false
    }
}

const revalidateFile = async () => {
    if (!selectedFile.value || !previewData.value) {
        return
    }

    isUploading.value = true
    errorMessage.value = ''
    successMessage.value = ''

    const formData = new FormData()

    formData.append('file', selectedFile.value)
    formData.append(
        'rows',
        JSON.stringify(buildImportPayload()),
    )

    try {
        const response =
            await apiFetch<PreviewData>(
                '/members/import/preview',
                {
                    method: 'POST',
                    body: formData,
                    headers: {
                        Authorization:
                            `Bearer ${authStore.token}`,
                    },
                },
            )

        previewData.value =
            response.data as PreviewData

        editingKey.value = null

        const data =
            response.data as PreviewData

        successMessage.value =
            `Validasi ulang selesai. ${data.total} data siswa: ` +
            `${data.valid} valid, ${data.invalid} perlu diperbaiki, ` +
            `${data.ignored} baris diabaikan.`
    } catch (err: unknown) {
        const e = err as {
            data?: {
                message?: string
            }
        }

        errorMessage.value =
            e?.data?.message ||
            'Gagal memvalidasi ulang data.'
    } finally {
        isUploading.value = false
    }
}

const startEdit = (row: PreviewRow) => {
    if (row.status === 'IGNORED') {
        return
    }

    editingKey.value = rowKey(row)

    editDraft.sheet = row.sheet
    editDraft.line = row.line
    editDraft.nisn = row.nisn ?? ''
    editDraft.nis = row.nis ?? ''
    editDraft.name = row.name ?? ''

    errorMessage.value = ''
    successMessage.value = ''
}

const cancelEdit = () => {
    editingKey.value = null
}

const saveEditAndRevalidate = async () => {
    if (!previewData.value) {
        return
    }

    const key = `${editDraft.sheet}|${editDraft.line ?? ''}`

    const row = previewData.value.rows.find(
        (item) => rowKey(item) === key,
    )

    if (!row) {
        errorMessage.value =
            'Baris yang sedang diedit tidak ditemukan.'
        return
    }

    row.nisn = editDraft.nisn.trim()
    row.nis = editDraft.nis.trim()
    row.name = editDraft.name.trim()

    await revalidateFile()
}

const deleteRow = (row: PreviewRow) => {
    if (row.status === 'IGNORED') {
        return
    }

    const key = rowKey(row)

    if (!excludedKeys.value.includes(key)) {
        excludedKeys.value.push(key)
    }

    if (row.status === 'VALID') {
        previewData.value!.valid -= 1
        previewData.value!.total -= 1
    } else if (row.status === 'NEEDS_FIX') {
        previewData.value!.invalid -= 1
        previewData.value!.total -= 1
    }

    previewData.value!.ignored += 1

    row.status = 'IGNORED'
    row.is_valid = false
    row.errors = []
    row.ignored_reason =
        'Dihapus dari import oleh operator.'

    if (editingKey.value === key) {
        editingKey.value = null
    }

    successMessage.value =
        `Baris ${row.line ?? ''} ditandai untuk tidak diimport.`
}

const restoreRow = async (row: PreviewRow) => {
    const key = rowKey(row)

    excludedKeys.value = excludedKeys.value.filter(
        (item) => item !== key,
    )

    await revalidateFile()
}

const importData = async () => {
    if (!selectedFile.value || !previewData.value) {
        return
    }

    if (previewData.value.invalid > 0) {
        errorMessage.value =
            'Masih ada data yang perlu diperbaiki sebelum import.'
        return
    }

    isUploading.value = true
    errorMessage.value = ''

    const formData = new FormData()

    formData.append('file', selectedFile.value)
    formData.append(
        'rows',
        JSON.stringify(buildImportPayload()),
    )

    try {
        const response =
            await apiFetch<ImportResult>(
                '/members/import',
                {
                    method: 'POST',
                    body: formData,
                    headers: {
                        Authorization:
                            `Bearer ${authStore.token}`,
                    },
                },
            )

        importResult.value =
            response.data as ImportResult

        successMessage.value =
            response.message ??
            'Siswa berhasil diimport.'

        previewData.value = null
        selectedFile.value = null
        excludedKeys.value = []
        editingKey.value = null
        activeSheet.value = 'ALL'

        if (fileInput.value) {
            fileInput.value.value = ''
        }
    } catch (err: unknown) {
        const e = err as {
            data?: {
                message?: string
            }
        }

        errorMessage.value =
            e?.data?.message ||
            'Gagal mengimport data.'
    } finally {
        isUploading.value = false
    }
}
</script>

<template>
    <StaffShell active="members">
        <div class="space-y-6">
            <section
                class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"
            >
                <div class="flex items-center gap-2">
                    <NuxtLink
                        to="/staff/members"
                        class="text-sm font-medium text-slate-500 hover:text-slate-700"
                    >
                        Anggota
                    </NuxtLink>

                    <span class="text-slate-400">/</span>

                    <p class="text-sm font-medium text-slate-900">
                        Import Excel
                    </p>
                </div>

                <h1
                    class="mt-3 text-3xl font-bold tracking-tight text-slate-900"
                >
                    Bulk Import Siswa
                </h1>

                <p
                    class="mt-2 max-w-2xl text-sm text-slate-500"
                >
                    Satu file Excel dapat berisi sebanyak apa pun
                    sheet kelas yang ada di sekolah.
                </p>
            </section>

            <section
                class="rounded-2xl border border-blue-100 bg-blue-50 p-5 shadow-sm"
            >
                <h2
                    class="mb-3 text-sm font-semibold text-blue-900"
                >
                    Format Excel Sekolah
                </h2>

                <div
                    class="grid gap-4 text-sm text-blue-800 sm:grid-cols-2"
                >
                    <div>
                        <p class="mb-1 font-medium">
                            Kolom yang diperlukan:
                        </p>

                        <ul
                            class="list-disc space-y-0.5 pl-4 font-mono text-xs"
                        >
                            <li>NO - nomor urut (diabaikan)</li>
                            <li>NISN - nomor induk siswa nasional</li>
                            <li>NIS - nomor induk siswa (nomor anggota)</li>
                            <li>NAMA - nama lengkap siswa</li>
                            <li>JK - jenis kelamin (L/P, tidak disimpan)</li>
                        </ul>
                    </div>

                    <div>
                        <p class="mb-1 font-medium">
                            Sheet dibaca otomatis:
                        </p>

                        <ul
                            class="list-disc space-y-0.5 pl-4 text-xs"
                        >
                            <li>
                                Sistem mengikuti semua sheet yang ada di Excel.
                            </li>
                            <li>
                                Total Data Santri otomatis diabaikan.
                            </li>
                            <li>
                                X A / X B / XI A / XI B dan sheet sejenis dapat dibaca dinamis.
                            </li>
                            <li>
                                XII IPA / XII IPS 1 / XII IPS 2 dan jurusan tambahan mengikuti nama sheet.
                            </li>
                        </ul>
                    </div>
                </div>

                <div
                    class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800"
                >
                    <strong>Kredensial siswa:</strong>
                    Username = NISN | Password awal = NISN |
                    Siswa wajib mengganti password saat login pertama.
                </div>
            </section>

            <div
                v-if="errorMessage"
                class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
            >
                {{ errorMessage }}
            </div>

            <div
                v-if="successMessage && !importResult"
                class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700"
            >
                <template v-if="previewData">
                    Validasi selesai.
                    <strong class="text-slate-700">
                        {{ previewData.total }} data siswa
                    </strong>
                    dari
                    <strong class="text-slate-700">
                        {{ previewData.sheets.length }} sheet
                    </strong>:
                    <strong class="text-emerald-700">
                        {{ previewData.valid }} valid
                    </strong>,
                    <strong
                        :class="previewData.invalid > 0
                            ? 'text-rose-700'
                            : 'text-emerald-700'"
                    >
                        {{ previewData.invalid }} perlu diperbaiki
                    </strong>,
                    <strong class="text-slate-700">
                        {{ previewData.ignored }} baris diabaikan
                    </strong>.
                </template>

                <template v-else>
                    {{ successMessage }}
                </template>
            </div>

            <section
                v-if="!importResult"
                class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
            >
                <div class="mb-5">
                    <h2
                        class="text-base font-semibold text-slate-900"
                    >
                        Upload File Excel Sekolah
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Upload satu file .xlsx yang berisi sheet-sheet kelas.
                        Header di bawah judul/dekorasi akan dideteksi otomatis.
                    </p>
                </div>

                <div class="flex max-w-xl flex-col gap-4">
                    <input
                        ref="fileInput"
                        id="file-upload"
                        type="file"
                        accept=".xlsx,.xls"
                        aria-label="Pilih file Excel sekolah"
                        class="block w-full cursor-pointer text-sm text-slate-500 file:mr-4 file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200"
                        @change="onFileSelect"
                    >

                    <div
                        v-if="selectedFile"
                        class="text-sm text-slate-600"
                    >
                        File dipilih:
                        <span class="font-medium">
                            {{ selectedFile.name }}
                        </span>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button
                            type="button"
                            :disabled="!selectedFile || isUploading"
                            class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                            @click="validateFile"
                        >
                            {{
                                isUploading && !previewData
                                    ? 'Memvalidasi...'
                                    : 'Validasi File'
                            }}
                        </button>
                    </div>
                </div>
            </section>

            <section
                v-if="previewData"
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
            >
                <div
                    class="border-b border-slate-100 bg-slate-50 p-5"
                >
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div>
                            <h2
                                class="text-base font-semibold text-slate-900"
                            >
                                Preview Data
                            </h2>

                            <p
                                class="mt-1 text-sm text-slate-500"
                            >
                                {{ previewData.total }} data siswa:
                                <strong
                                    class="text-emerald-600"
                                >
                                    {{ previewData.valid }} Valid
                                </strong>
                                /
                                <strong
                                    class="text-rose-600"
                                >
                                    {{ previewData.invalid }} Perlu diperbaiki
                                </strong>
                                /
                                <strong
                                    class="text-slate-600"
                                >
                                    {{ previewData.ignored }} Diabaikan
                                </strong>
                            </p>

                            <p
                                class="mt-1 text-xs text-slate-500"
                            >
                                Nomor siswa dibuat berurutan per sheet.
                                Baris Excel tetap dicatat untuk Edit/Hapus.
                            </p>
                        </div>

                        <div
                            class="flex flex-wrap gap-3"
                        >
                            <button
                                type="button"
                                class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                @click="previewData = null; selectedFile = null; editingKey = null; excludedKeys = []; activeSheet = 'ALL'"
                            >
                                Batal
                            </button>

                            <button
                                type="button"
                                :disabled="previewData.invalid > 0 || isUploading"
                                class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="importData"
                            >
                                {{
                                    isUploading
                                        ? 'Memproses...'
                                        : `Import ${previewData.valid} Siswa`
                                }}
                            </button>
                        </div>
                    </div>

                    <div
                        class="mt-5 overflow-x-auto pb-1"
                    >
                        <div class="flex min-w-max gap-2">
                            <button
                                type="button"
                                :class="activeSheet === 'ALL'
                                    ? 'bg-slate-900 text-white'
                                    : 'bg-white text-slate-700 border border-slate-300'"
                                class="rounded-xl px-4 py-2 text-xs font-semibold shadow-sm"
                                @click="activeSheet = 'ALL'"
                            >
                                Semua ({{ previewData.total }})
                            </button>

                            <button
                                v-for="sheet in previewData.sheets"
                                :key="sheet.name"
                                type="button"
                                :class="activeSheet === sheet.name
                                    ? 'bg-slate-900 text-white'
                                    : 'bg-white text-slate-700 border border-slate-300'"
                                class="rounded-xl px-4 py-2 text-xs font-semibold shadow-sm"
                                @click="activeSheet = sheet.name"
                            >
                                {{ sheet.name }}
                                ({{ sheet.student_total }})
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="previewData.ignored > 0"
                        class="mt-3"
                    >
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            @click="showIgnoredRows = !showIgnoredRows"
                        >
                            {{
                                showIgnoredRows
                                    ? 'Sembunyikan Baris Diabaikan'
                                    : `Tampilkan ${previewData.ignored} Baris Diabaikan`
                            }}
                        </button>
                    </div>
                </div>

                <div
                    class="overflow-x-auto"
                >
                    <table
                        class="w-full min-w-[1500px] border-collapse text-left text-sm"
                    >
                        <thead>
                            <tr
                                class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500"
                            >
                                <th
                                    class="border-b border-slate-200 px-4 py-3"
                                >
                                    Sheet
                                </th>

                                <th
                                    class="w-16 border-b border-slate-200 px-4 py-3"
                                >
                                    No
                                </th>

                                <th
                                    class="w-20 border-b border-slate-200 px-4 py-3"
                                >
                                    Baris Excel
                                </th>

                                <th
                                    class="border-b border-slate-200 px-4 py-3"
                                >
                                    NISN
                                </th>

                                <th
                                    class="border-b border-slate-200 px-4 py-3"
                                >
                                    NIS
                                </th>

                                <th
                                    class="border-b border-slate-200 px-4 py-3"
                                >
                                    Nama
                                </th>

                                <th
                                    class="border-b border-slate-200 px-4 py-3"
                                >
                                    Kelas
                                </th>

                                <th
                                    class="border-b border-slate-200 px-4 py-3"
                                >
                                    Jurusan
                                </th>

                                <th
                                    class="w-64 border-b border-slate-200 px-4 py-3"
                                >
                                    Status
                                </th>

                                <th
                                    class="sticky right-0 z-20 border-b border-l border-slate-200 bg-slate-50 px-4 py-3 shadow-[-4px_0_8px_rgba(15,23,42,0.06)]"
                                >
                                    Aksi
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="row in filteredRows"
                                :key="rowKey(row)"
                                :class="{
                                    'bg-rose-50/50': row.status === 'NEEDS_FIX',
                                    'bg-slate-50/70': row.status === 'IGNORED',
                                }"
                            >
                                <td
                                    class="px-4 py-3 text-xs font-mono text-slate-500"
                                >
                                    {{ row.sheet }}
                                </td>

                                <td
                                    class="px-4 py-3 text-xs font-semibold text-slate-700"
                                >
                                    {{ displayNumber(row) }}
                                </td>

                                <td
                                    class="px-4 py-3 text-xs text-slate-500"
                                >
                                    {{ row.line }}
                                </td>

                                <td
                                    class="px-4 py-3 font-mono text-slate-700"
                                >
                                    <template
                                        v-if="editingKey === rowKey(row)"
                                    >
                                        <input
                                            v-model="editDraft.nisn"
                                            type="text"
                                            inputmode="numeric"
                                            class="w-44 rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm outline-none focus:border-slate-500"
                                            aria-label="NISN"
                                        >
                                    </template>

                                    <template v-else>
                                        {{ row.nisn || '-' }}
                                    </template>
                                </td>

                                <td
                                    class="px-4 py-3 font-mono text-xs text-slate-600"
                                >
                                    <template
                                        v-if="editingKey === rowKey(row)"
                                    >
                                        <input
                                            v-model="editDraft.nis"
                                            type="text"
                                            inputmode="numeric"
                                            class="w-56 rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm outline-none focus:border-slate-500"
                                            aria-label="NIS"
                                        >
                                    </template>

                                    <template v-else>
                                        {{ row.nis || '-' }}
                                    </template>
                                </td>

                                <td
                                    class="px-4 py-3 text-slate-900"
                                >
                                    <template
                                        v-if="editingKey === rowKey(row)"
                                    >
                                        <input
                                            v-model="editDraft.name"
                                            type="text"
                                            class="w-64 rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium outline-none focus:border-slate-500"
                                            aria-label="Nama siswa"
                                        >
                                    </template>

                                    <template v-else>
                                        <span
                                            class="font-medium"
                                        >
                                            {{ row.name || '-' }}
                                        </span>
                                    </template>
                                </td>

                                <td
                                    class="px-4 py-3 text-slate-600"
                                >
                                    {{ row.class || '-' }}
                                </td>

                                <td
                                    class="px-4 py-3 text-slate-600"
                                >
                                    {{ row.major || '-' }}
                                </td>

                                <td
                                    class="px-4 py-3 align-top"
                                >
                                    <span
                                        v-if="row.status === 'VALID'"
                                        class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700"
                                    >
                                        VALID
                                    </span>

                                    <span
                                        v-else-if="row.status === 'NEEDS_FIX'"
                                        class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700"
                                    >
                                        PERLU DIPERBAIKI
                                    </span>

                                    <span
                                        v-else
                                        class="inline-flex items-center rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700"
                                    >
                                        DIABAIKAN
                                    </span>

                                    <p
                                        v-if="row.ignored_reason"
                                        class="mt-2 text-xs text-slate-500"
                                    >
                                        {{ row.ignored_reason }}
                                    </p>

                                    <ul
                                        v-if="row.errors.length"
                                        class="mt-2 list-disc space-y-0.5 pl-4 text-xs text-rose-600"
                                    >
                                        <li
                                            v-for="(error, errorIndex) in row.errors"
                                            :key="errorIndex"
                                        >
                                            {{ error }}
                                        </li>
                                    </ul>
                                </td>

                                <td
                                    class="sticky right-0 z-10 border-l border-slate-200 bg-white px-4 py-3 shadow-[-4px_0_8px_rgba(15,23,42,0.06)]"
                                >
                                    <div
                                        v-if="row.status !== 'IGNORED' && editingKey !== rowKey(row)"
                                        class="flex min-w-[145px] flex-col gap-2"
                                    >
                                        <button
                                            type="button"
                                            class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800"
                                            @click="startEdit(row)"
                                        >
                                            Edit
                                        </button>

                                        <button
                                            type="button"
                                            class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                                            @click="deleteRow(row)"
                                        >
                                            Hapus
                                        </button>
                                    </div>

                                    <div
                                        v-else-if="editingKey === rowKey(row)"
                                        class="flex min-w-[180px] flex-col gap-2"
                                    >
                                        <button
                                            type="button"
                                            :disabled="isUploading"
                                            class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
                                            @click="saveEditAndRevalidate"
                                        >
                                            Simpan & Validasi Ulang
                                        </button>

                                        <button
                                            type="button"
                                            :disabled="isUploading"
                                            class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                                            @click="cancelEdit"
                                        >
                                            Batal
                                        </button>
                                    </div>

                                    <div
                                        v-else-if="isExcluded(row)"
                                        class="flex min-w-[145px] flex-col gap-2"
                                    >
                                        <button
                                            type="button"
                                            disabled
                                            class="cursor-not-allowed rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-400"
                                        >
                                            Edit
                                        </button>

                                        <button
                                            type="button"
                                            class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"
                                            @click="restoreRow(row)"
                                        >
                                            Kembalikan
                                        </button>
                                    </div>

                                    <div
                                        v-else
                                        class="flex min-w-[145px] flex-col gap-2"
                                    >
                                        <button
                                            type="button"
                                            disabled
                                            title="Baris rekap Excel memang tidak diimport."
                                            class="cursor-not-allowed rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-400"
                                        >
                                            Edit
                                        </button>

                                        <button
                                            type="button"
                                            disabled
                                            title="Baris rekap Excel memang tidak diimport."
                                            class="cursor-not-allowed rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-400"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                v-if="importResult"
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
            >
                <div
                    class="border-b border-slate-100 bg-emerald-50 p-6"
                >
                    <h2
                        class="text-lg font-semibold text-emerald-900"
                    >
                        Import Berhasil
                    </h2>

                    <p class="mt-1 text-sm text-emerald-700">
                        Berhasil mengimport
                        <strong>
                            {{ importResult.created }}
                        </strong>
                        siswa dari total
                        <strong>
                            {{ importResult.total }}
                        </strong>
                        data.
                    </p>

                    <div
                        class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800"
                    >
                        <strong
                            class="mb-1 block font-semibold"
                        >
                            Peringatan Keamanan
                        </strong>

                        Password awal setiap siswa adalah NISN mereka
                        masing-masing.
                        Siswa diwajibkan mengganti password saat pertama kali
                        login.
                        Jangan simpan atau distribusikan halaman ini.
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table
                        class="w-full min-w-[700px] border-collapse text-left text-sm"
                    >
                        <thead>
                            <tr
                                class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500"
                            >
                                <th class="border-b border-slate-200 px-4 py-3">
                                    Sheet
                                </th>
                                <th class="border-b border-slate-200 px-4 py-3">
                                    NISN
                                </th>
                                <th class="border-b border-slate-200 px-4 py-3">
                                    NIS
                                </th>
                                <th class="border-b border-slate-200 px-4 py-3">
                                    Nama
                                </th>
                                <th class="border-b border-slate-200 px-4 py-3">
                                    Kelas
                                </th>
                                <th class="border-b border-slate-200 px-4 py-3">
                                    Jurusan
                                </th>
                                <th class="border-b border-slate-200 px-4 py-3">
                                    Username
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="student in importResult.students"
                                :key="student.nisn"
                                class="hover:bg-slate-50/50"
                            >
                                <td class="px-4 py-3 text-xs font-mono text-slate-500">
                                    {{ student.sheet }}
                                </td>
                                <td class="px-4 py-3 font-mono text-slate-700">
                                    {{ student.nisn }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-600">
                                    {{ student.nis }}
                                </td>
                                <td class="px-4 py-3 font-medium text-slate-900">
                                    {{ student.name }}
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{ student.class }}
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{ student.major || '-' }}
                                </td>
                                <td class="px-4 py-3 font-mono font-semibold text-blue-700">
                                    {{ student.username }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end border-t border-slate-100 p-4">
                    <NuxtLink
                        to="/staff/members"
                        class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800"
                    >
                        Lihat Daftar Anggota
                    </NuxtLink>
                </div>
            </section>
        </div>
    </StaffShell>
</template>