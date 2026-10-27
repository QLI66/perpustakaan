<script setup lang="ts">
import { useSchoolIdentityStore } from '~/stores/schoolIdentity'

definePageMeta({
    middleware: 'admin',
})

const identityStore = useSchoolIdentityStore()
const toast = useToast()

const schoolName = ref(identityStore.schoolName)
const themeColor = ref(identityStore.themeColor)
const isSaving = ref(false)
const isUploadingLogo = ref(false)
const isDeletingLogo = ref(false)
const errorMessage = ref('')

const selectedFile = ref<File | null>(null)
const previewLogoUrl = ref<string | null>(null)
const fileInputRef = ref<HTMLInputElement | null>(null)

const presetColors = [
    { label: 'Biru (Default)', hex: '#1d4ed8' },
    { label: 'Zamrud (Emerald)', hex: '#059669' },
    { label: 'Ungu (Violet)', hex: '#7c3aed' },
    { label: 'Indigo', hex: '#4338ca' },
    { label: 'Mawar (Rose)', hex: '#e11d48' },
    { label: 'Kuning Jingga (Amber)', hex: '#d97706' },
]

onMounted(async () => {
    await identityStore.fetchIdentity(true)
    schoolName.value = identityStore.schoolName
    themeColor.value = identityStore.themeColor
})

const handleFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement
    const file = target.files?.[0]
    if (!file) return

    if (file.size > 2 * 1024 * 1024) {
        toast.add({
            severity: 'error',
            summary: 'File Terlalu Besar',
            detail: 'Ukuran file logo maksimal 2 MB.',
            life: 4000,
        })
        return
    }

    selectedFile.value = file
    previewLogoUrl.value = URL.createObjectURL(file)
}

const saveIdentity = async () => {
    errorMessage.value = ''
    if (!schoolName.value.trim()) {
        errorMessage.value = 'Nama sekolah wajib diisi.'
        return
    }

    if (!/^#[0-9A-Fa-f]{6}$/.test(themeColor.value)) {
        errorMessage.value = 'Format warna harus hex 6 digit (contoh: #1d4ed8).'
        return
    }

    isSaving.value = true
    try {
        await identityStore.updateIdentity({
            school_name: schoolName.value.trim(),
            theme_color: themeColor.value,
        })

        toast.add({
            severity: 'success',
            summary: 'Berhasil',
            detail: 'Identitas sekolah berhasil diperbarui.',
            life: 3000,
        })
    } catch (e: unknown) {
        errorMessage.value = e instanceof Error ? e.message : 'Gagal menyimpan pengaturan.'
    } finally {
        isSaving.value = false
    }
}

const uploadSelectedLogo = async () => {
    if (!selectedFile.value) return

    isUploadingLogo.value = true
    try {
        await identityStore.uploadLogo(selectedFile.value)
        selectedFile.value = null
        if (fileInputRef.value) {
            fileInputRef.value.value = ''
        }

        toast.add({
            severity: 'success',
            summary: 'Logo Diperbarui',
            detail: 'Logo sekolah berhasil diunggah dan disimpan.',
            life: 3000,
        })
    } catch (e: unknown) {
        toast.add({
            severity: 'error',
            summary: 'Gagal Upload',
            detail: e instanceof Error ? e.message : 'Gagal mengunggah logo.',
            life: 4000,
        })
    } finally {
        isUploadingLogo.value = false
    }
}

const deleteLogo = async () => {
    isDeletingLogo.value = true
    try {
        await identityStore.deleteLogo()
        selectedFile.value = null
        previewLogoUrl.value = null
        if (fileInputRef.value) {
            fileInputRef.value.value = ''
        }

        toast.add({
            severity: 'info',
            summary: 'Logo Dihapus',
            detail: 'Logo sekolah telah dikembalikan ke logo bawaan perpustakaan.',
            life: 3000,
        })
    } catch (e: unknown) {
        toast.add({
            severity: 'error',
            summary: 'Gagal',
            detail: e instanceof Error ? e.message : 'Gagal menghapus logo.',
            life: 4000,
        })
    } finally {
        isDeletingLogo.value = false
    }
}
</script>

<template>
    <AdminShell>
        <div class="mx-auto max-w-5xl space-y-8 px-4 sm:px-6 lg:px-8">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-xl text-blue-700">
                        ⚙
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500">Konfigurasi Sistem</p>
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                            Pengaturan Identitas Sekolah
                        </h1>
                    </div>
                </div>
                <p class="mt-3 text-sm text-slate-600">
                    Kustomisasi nama sekolah, logo resmi, dan tema warna utama aplikasi perpustakaan. Pengaturan ini otomatis diterapkan pada seluruh halaman portal, navbar, dan layar login.
                </p>
            </section>

            <div v-if="errorMessage" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                {{ errorMessage }}
            </div>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                <!-- Form Settings -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- School Name & Theme Form -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-bold text-slate-900">Nama & Tema Warna</h2>
                        <p class="mt-1 text-xs text-slate-500">Sesuaikan nama lembaga dan identitas visual utama.</p>

                        <div class="mt-6 space-y-5">
                            <div>
                                <label for="school-name-input" class="block text-sm font-semibold text-slate-700">
                                    Nama Sekolah / Lembaga
                                </label>
                                <input
                                    id="school-name-input"
                                    v-model="schoolName"
                                    type="text"
                                    maxlength="100"
                                    placeholder="Contoh: SMA Negeri 1 Bandung"
                                    class="mt-1.5 block w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-600 focus:ring-2 focus:ring-blue-100"
                                >
                            </div>

                            <div>
                                <label for="theme-color-input" class="block text-sm font-semibold text-slate-700">
                                    Warna Utama (Primary Color)
                                </label>
                                <div class="mt-1.5 flex items-center gap-3">
                                    <input
                                        id="theme-color-picker"
                                        v-model="themeColor"
                                        type="color"
                                        class="h-10 w-12 cursor-pointer rounded-lg border border-slate-300 bg-transparent p-1"
                                    >
                                    <input
                                        id="theme-color-input"
                                        v-model="themeColor"
                                        type="text"
                                        maxlength="7"
                                        placeholder="#1d4ed8"
                                        class="block w-36 rounded-xl border border-slate-300 px-3 py-2 text-sm font-mono uppercase text-slate-900 shadow-sm outline-none transition focus:border-blue-600 focus:ring-2 focus:ring-blue-100"
                                    >
                                    <span class="text-xs text-slate-500">Pilih palet atau masukkan kode hex.</span>
                                </div>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button
                                        v-for="preset in presetColors"
                                        :key="preset.hex"
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-medium transition"
                                        :class="themeColor.toLowerCase() === preset.hex.toLowerCase() ? 'border-slate-800 bg-slate-100 font-bold' : 'border-slate-200 hover:bg-slate-50'"
                                        @click="themeColor = preset.hex"
                                    >
                                        <span class="h-3 w-3 rounded-full shadow-sm" :style="{ backgroundColor: preset.hex }" />
                                        {{ preset.label }}
                                    </button>
                                </div>
                            </div>

                            <div class="pt-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition disabled:opacity-50"
                                    :style="{ backgroundColor: themeColor }"
                                    :disabled="isSaving"
                                    @click="saveIdentity"
                                >
                                    <span v-if="isSaving">Menyimpan...</span>
                                    <span v-else>Simpan Perubahan</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Logo Upload Section -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-bold text-slate-900">Logo Sekolah</h2>
                        <p class="mt-1 text-xs text-slate-500">Unggah logo resmi sekolah (PNG, JPG, WEBP, atau SVG max 2MB).</p>

                        <div class="mt-6 flex flex-col gap-6 sm:flex-row sm:items-center">
                            <!-- Current / Preview Logo Box -->
                            <div class="flex h-28 w-28 shrink-0 items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 p-2 shadow-inner">
                                <img
                                    v-if="previewLogoUrl || identityStore.hasCustomLogo"
                                    :src="previewLogoUrl || identityStore.resolvedLogoUrl || ''"
                                    alt="Logo Sekolah"
                                    class="h-full w-full object-contain"
                                >
                                <div v-else class="text-center text-slate-400">
                                    <svg class="mx-auto h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M12 7v14" />
                                        <path d="M3 18V5a2 2 0 0 1 2-2h3a4 4 0 0 1 4 4 4 4 0 0 1 4-4h3a2 2 0 0 1 2 2v13h-5a4 4 0 0 0-4 3 4 4 0 0 0-4-3H3Z" />
                                    </svg>
                                    <span class="mt-1 block text-[10px]">Logo Bawaan</span>
                                </div>
                            </div>

                            <div class="flex-1 space-y-3">
                                <input
                                    ref="fileInputRef"
                                    type="file"
                                    accept=".png,.jpg,.jpeg,.webp,.svg"
                                    class="block w-full text-xs text-slate-500 file:mr-3 file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2.5 file:text-xs file:font-semibold file:text-slate-700 hover:file:bg-slate-200"
                                    @change="handleFileChange"
                                >
                                <p class="text-xs text-slate-400">Rekomendasi rasio 1:1 (persegi) atau horizontal dengan latar belakang transparan.</p>

                                <div class="flex flex-wrap gap-2.5 pt-1">
                                    <button
                                        v-if="selectedFile"
                                        type="button"
                                        class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:opacity-50"
                                        :disabled="isUploadingLogo"
                                        @click="uploadSelectedLogo"
                                    >
                                        <span v-if="isUploadingLogo">Mengunggah...</span>
                                        <span v-else>Unggah Logo</span>
                                    </button>

                                    <button
                                        v-if="identityStore.hasCustomLogo"
                                        type="button"
                                        class="inline-flex items-center rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 disabled:opacity-50"
                                        :disabled="isDeletingLogo"
                                        @click="deleteLogo"
                                    >
                                        <span v-if="isDeletingLogo">Menghapus...</span>
                                        <span v-else>Hapus & Gunakan Bawaan</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Live Preview Card -->
                <div class="space-y-6">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-bold text-slate-900">Pratinjau Tampilan (Live Preview)</h3>
                        <p class="mt-1 text-xs text-slate-500">Begini tampilan identitas di Navbar dan Layar Login:</p>

                        <!-- Preview Navbar -->
                        <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-slate-50 p-3 shadow-inner">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Pratinjau Header Navbar</p>
                            <div class="mt-2 flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm">
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg text-white shadow-sm"
                                    :style="{ backgroundColor: themeColor }"
                                >
                                    <img
                                        v-if="previewLogoUrl || identityStore.hasCustomLogo"
                                        :src="previewLogoUrl || identityStore.resolvedLogoUrl || ''"
                                        alt="Logo"
                                        class="h-full w-full object-contain p-1"
                                    >
                                    <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M12 7v14" />
                                        <path d="M3 18V5a2 2 0 0 1 2-2h3a4 4 0 0 1 4 4 4 4 0 0 1 4-4h3a2 2 0 0 1 2 2v13h-5a4 4 0 0 0-4 3 4 4 0 0 0-4-3H3Z" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-bold text-slate-900">{{ schoolName || 'Perpustakaan Sekolah' }}</p>
                                    <p class="text-[10px] text-slate-400">Portal Perpustakaan</p>
                                </div>
                            </div>
                        </div>

                        <!-- Preview Login Header -->
                        <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-slate-50 p-3 shadow-inner">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Pratinjau Kartu Login</p>
                            <div class="mt-2 rounded-lg border border-slate-200 bg-white p-4 text-center shadow-sm">
                                <div
                                    class="mx-auto flex h-12 w-12 items-center justify-center overflow-hidden rounded-xl text-white shadow-sm"
                                    :style="{ backgroundColor: themeColor }"
                                >
                                    <img
                                        v-if="previewLogoUrl || identityStore.hasCustomLogo"
                                        :src="previewLogoUrl || identityStore.resolvedLogoUrl || ''"
                                        alt="Logo"
                                        class="h-full w-full object-contain p-1"
                                    >
                                    <svg v-else class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M12 7v14" />
                                        <path d="M3 18V5a2 2 0 0 1 2-2h3a4 4 0 0 1 4 4 4 4 0 0 1 4-4h3a2 2 0 0 1 2 2v13h-5a4 4 0 0 0-4 3 4 4 0 0 0-4-3H3Z" />
                                    </svg>
                                </div>
                                <p class="mt-2 text-xs font-bold text-slate-900">{{ schoolName || 'Perpustakaan Sekolah' }}</p>
                                <p class="text-[10px] text-slate-500">Masuk untuk mengakses akun perpustakaan Anda</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminShell>
</template>
