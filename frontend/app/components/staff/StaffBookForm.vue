<script setup lang="ts">
import type {
    StaffBookForm,
    StaffLookupItem,
} from '~/types/staff'
import { getApiErrorMessage } from '~/composables/useApi'

interface Props {
    initialValue: StaffBookForm
    submitLabel: string
    loading: boolean
    errorMessage?: string
}

const props = defineProps<Props>()

const emit = defineEmits<{
    submit: [value: StaffBookForm]
}>()

const { 
    listCategories,
    listAuthors,
    listPublishers,
    listCollectionTypes,
    createAuthor,
    createCategory,
} = useStaffBooks()

const form = reactive<StaffBookForm>({
    isbn: props.initialValue.isbn,
    title: props.initialValue.title,
    subtitle: props.initialValue.subtitle,
    description: props.initialValue.description,
    publication_year: props.initialValue.publication_year,
    edition: props.initialValue.edition,
    language: props.initialValue.language,
    collection_type_id: props.initialValue.collection_type_id,
    publisher_id: props.initialValue.publisher_id,
    cover: props.initialValue.cover,
    author_ids: [...props.initialValue.author_ids],
    category_ids: [...props.initialValue.category_ids],
})

const categories = ref<StaffLookupItem[]>([])
const authors = ref<StaffLookupItem[]>([])
const showAddCategory = ref(false)
const newCategoryName = ref('')
const savingCategory = ref(false)
const categoryCreateError = ref('')
const showAddAuthor = ref(false)
const newAuthorName = ref('')
const savingAuthor = ref(false)
const authorCreateError = ref('')

const addCategory = async () => {
    const name = newCategoryName.value.trim()

    if (!name || savingCategory.value) {
        return
    }

    savingCategory.value = true
    categoryCreateError.value = ''

    try {
        const item = await createCategory(name)

        if (!categories.value.some((category) => category.id === item.id)) {
            categories.value = [...categories.value, item]
        }

        form.category_ids = Array.from(
            new Set([...form.category_ids, item.id]),
        )
        newCategoryName.value = ''
        showAddCategory.value = false
    } catch (error: unknown) {
        categoryCreateError.value = getApiErrorMessage(
            error,
            'Gagal menambahkan kategori.',
        )
    } finally {
        savingCategory.value = false
    }
}

const addAuthor = async () => {
  const name = newAuthorName.value.trim()
  if (!name || savingAuthor.value) return

  savingAuthor.value = true
  authorCreateError.value = ''
  try {
    const item = await createAuthor(name)

    if (!authors.value.some(a => String(a.id) === String(item.id))) {
      authors.value = [...authors.value, item]
    }
    form.author_ids = Array.from(
      new Set([...(form.author_ids ?? []), item.id]),
    )
    newAuthorName.value = ''
    showAddAuthor.value = false
  } catch (error: unknown) {
    authorCreateError.value = getApiErrorMessage(
      error,
      'Gagal menambahkan penulis.',
    )
  } finally {
    savingAuthor.value = false
  }
}
const publishers = ref<StaffLookupItem[]>([])
const collectionTypes = ref<StaffLookupItem[]>([])

const loadingLookups = ref(true)

const submit = () => {
    if (!form.title.trim()) {
        return
    }

    emit('submit', {
        ...form,
        title: form.title.trim(),
    })
}

const loadLookups = async () => {
    loadingLookups.value = true

    try {
        const [
            categoryResult,
            authorResult,
            publisherResult,
            collectionTypeResult,
        ] = await Promise.all([
            listCategories(),
            listAuthors(),
            listPublishers(),
            listCollectionTypes(),
        ])

        categories.value = categoryResult
        authors.value = authorResult
        publishers.value = publisherResult
        collectionTypes.value = collectionTypeResult
    } finally {
        loadingLookups.value = false
    }
}

onMounted(loadLookups)
</script>

<template>
    <form
        class="space-y-6"
        @submit.prevent="submit"
    >
        <div
            v-if="errorMessage"
            class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
        >
            {{ errorMessage }}
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <div class="lg:col-span-2">
                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Judul Buku <span class="text-rose-600">*</span>
                </label>

                <input
                    v-model="form.title"
                    type="text"
                    required
                    maxlength="255"
                    placeholder="Contoh: Laskar Pelangi"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
            </div>

            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    ISBN
                </label>

                <input
                    v-model="form.isbn"
                    type="text"
                    maxlength="20"
                    placeholder="978..."
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
            </div>

            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Subjudul
                </label>

                <input
                    v-model="form.subtitle"
                    type="text"
                    maxlength="255"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
            </div>

            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Tahun Terbit
                </label>

                <input
                    v-model.number="form.publication_year"
                    type="number"
                    min="0"
                    max="65535"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
            </div>

            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Edisi
                </label>

                <input
                    v-model="form.edition"
                    type="text"
                    maxlength="50"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
            </div>

            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Bahasa
                </label>

                <input
                    v-model="form.language"
                    type="text"
                    maxlength="10"
                    placeholder="id"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
            </div>

            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Jenis Koleksi <span class="text-rose-600">*</span>
                </label>

                <select
                    v-model.number="form.collection_type_id"
                    required
                    :disabled="loadingLookups"
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
                    <option :value="null">
                        Pilih jenis koleksi
                    </option>

                    <option
                        v-for="item in collectionTypes"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.name }}
                    </option>
                </select>
            </div>

            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Penerbit
                </label>

                <select
                    v-model.number="form.publisher_id"
                    :disabled="loadingLookups"
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
                    <option :value="null">
                        Tidak ada / pilih penerbit
                    </option>

                    <option
                        v-for="item in publishers"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.name }}
                    </option>
                </select>
            </div>

            <div class="lg:col-span-2">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
  <label class="block text-sm font-semibold text-slate-700">Penulis</label>
  <button type="button"
    class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"
    @click="showAddAuthor = !showAddAuthor; authorCreateError = ''">
    + Tambah Penulis
  </button>
</div>
<div v-if="showAddAuthor" class="mb-3 rounded-xl border border-emerald-200 bg-emerald-50/60 p-3">
  <div class="flex flex-col gap-2 sm:flex-row">
    <input v-model="newAuthorName" type="text" maxlength="255"
      autocomplete="off" placeholder="Masukkan nama penulis baru"
      aria-label="Nama penulis baru"
      class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
      :disabled="savingAuthor" @keydown.enter.prevent="addAuthor" />
    <button type="button"
      class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
      :disabled="savingAuthor || !newAuthorName.trim()" @click="addAuthor">
      {{ savingAuthor ? 'Menyimpan...' : 'Simpan' }}
    </button>
    <button type="button"
      class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
      :disabled="savingAuthor"
      @click="showAddAuthor = false; newAuthorName = ''; authorCreateError = ''">
      Batal
    </button>
  </div>
  <p v-if="authorCreateError" class="mt-2 text-sm text-rose-600">
    {{ authorCreateError }}
  </p>
</div>

                <select
                    v-model="form.author_ids"
                    multiple
                    size="5"
                    :disabled="loadingLookups"
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
                    <option
                        v-for="item in authors"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.name }}
                    </option>
                </select>

                <p class="mt-2 text-xs text-slate-500">
                    Gunakan Ctrl/Cmd untuk memilih lebih dari satu penulis.
                </p>
            </div>

            <div class="lg:col-span-2">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <label class="block text-sm font-semibold text-slate-700">
                        Kategori
                    </label>

                    <button
                        type="button"
                        class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"
                        @click="showAddCategory = !showAddCategory; categoryCreateError = ''"
                    >
                        + Tambah Kategori
                    </button>
                </div>

                <div
                    v-if="showAddCategory"
                    class="mb-3 rounded-xl border border-emerald-200 bg-emerald-50/60 p-3"
                >
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input
                            v-model="newCategoryName"
                            type="text"
                            maxlength="100"
                            autocomplete="off"
                            placeholder="Masukkan nama kategori baru"
                            aria-label="Nama kategori baru"
                            class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                            :disabled="savingCategory"
                            @keydown.enter.prevent="addCategory"
                        >
                        <button
                            type="button"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                            :disabled="savingCategory || !newCategoryName.trim()"
                            @click="addCategory"
                        >
                            {{ savingCategory ? 'Menyimpan...' : 'Simpan' }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                            :disabled="savingCategory"
                            @click="showAddCategory = false; newCategoryName = ''; categoryCreateError = ''"
                        >
                            Batal
                        </button>
                    </div>

                    <p
                        v-if="categoryCreateError"
                        class="mt-2 text-sm text-rose-600"
                        role="alert"
                    >
                        {{ categoryCreateError }}
                    </p>
                </div>

                <select
                    v-model="form.category_ids"
                    multiple
                    size="5"
                    :disabled="loadingLookups"
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
                    <option
                        v-for="item in categories"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.name }}
                    </option>
                </select>

                <p class="mt-2 text-xs text-slate-500">
                    Gunakan Ctrl/Cmd untuk memilih lebih dari satu kategori.
                </p>
            </div>

            <div class="lg:col-span-2">
                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Deskripsi
                </label>

                <textarea
                    v-model="form.description"
                    rows="5"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                />
            </div>

            <div class="lg:col-span-2">
                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Cover
                </label>

                <input
                    v-model="form.cover"
                    type="text"
                    maxlength="255"
                    placeholder="Path / URL cover bila sudah tersedia"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
            </div>
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end">
            <NuxtLink
                to="/staff/books"
                class="rounded-xl border border-slate-200 px-5 py-3 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
                Batal
            </NuxtLink>

            <button
                type="submit"
                class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="loading || loadingLookups"
            >
                {{ loading ? 'Menyimpan...' : submitLabel }}
            </button>
        </div>
    </form>
</template>