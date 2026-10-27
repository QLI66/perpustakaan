import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'

export interface MemberListItem {
    id: number
    member_number: string
    name: string
    identity_number: string | null
    member_type?: {
        id: number
        code: string
        name: string
    } | null
    class_level?: {
        id: number
        code: string
        name: string
    } | null
    major?: {
        id: number
        code: string
        name: string
    } | null
    user?: {
        id: number
        name: string
        email: string
    } | null
    joined_at: string | null
    status: 'ACTIVE' | 'INACTIVE' | 'SUSPENDED'
    notes: string | null
}

export interface MemberListResponse {
    items: MemberListItem[]
    pagination: {
        current_page: number
        per_page: number
        total: number
        last_page: number
    }
}

export interface UpdateMemberPayload {
    member_number?: string
    name?: string
    member_type_id?: number
    identity_number?: string | null
    class_level_id?: number | null
    major_id?: number | null
    joined_at?: string
    status?: 'ACTIVE' | 'INACTIVE' | 'SUSPENDED'
    notes?: string | null
    user_id?: number | null
}

export interface MemberReference {
    id: number
    name: string
}

export interface CreateMemberPayload {
    member_number: string
    name: string
    member_type_id: number
    identity_number: string | null
    class_level_id: number | null
    major_id: number | null
    joined_at: string
    status: 'ACTIVE' | 'INACTIVE' | 'SUSPENDED'
    notes: string | null
}

export const useMembersService = () => {
    const { apiFetch } = useApi()
    const authStore = useAuthStore()

    const getHeaders = () => ({
        Accept: 'application/json',
        Authorization: authStore.token
            ? `Bearer ${authStore.token}`
            : '',
    })

    const getMembers = async (
        params: Record<string, unknown> = {},
    ) => {
        return apiFetch<MemberListResponse>('/members', {
            method: 'GET',
            headers: getHeaders(),
            params,
        })
    }

    const getMember = async (id: number) => {
        return apiFetch<MemberListItem>(`/members/${id}`, {
            method: 'GET',
            headers: getHeaders(),
        })
    }

    const updateMember = async (
        id: number,
        payload: UpdateMemberPayload,
    ) => {
        return apiFetch<MemberListItem>(`/members/${id}`, {
            method: 'PATCH',
            headers: getHeaders(),
            body: payload,
        })
    }

    const deleteMember = async (id: number) => {
        return apiFetch<null>(`/members/${id}`, {
            method: 'DELETE',
            headers: getHeaders(),
        })
    }

    const createMember = async (payload: CreateMemberPayload) => {
        return apiFetch<MemberListItem>('/members', {
            method: 'POST',
            headers: getHeaders(),
            body: payload,
        })
    }

    const getMemberTypes = async () => {
        return apiFetch<MemberReference[]>('/member-types', {
            method: 'GET',
            headers: getHeaders(),
        })
    }

    const getClassLevels = async () => {
        return apiFetch<MemberReference[]>('/class-levels', {
            method: 'GET',
            headers: getHeaders(),
        })
    }

    const getMajors = async () => {
        return apiFetch<MemberReference[]>('/majors', {
            method: 'GET',
            headers: getHeaders(),
        })
    }

    return {
        getMembers,
        getMember,
        createMember,
        updateMember,
        deleteMember,
        getMemberTypes,
        getClassLevels,
        getMajors,
    }
}