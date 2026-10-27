import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'

export interface User {
    id: number
    name: string
    email: string
    roles: Array<{ id: number, name: string }>
    created_at: string
}

export interface Role {
    id: number
    name: string
    permissions: Array<{ id: number, name: string }>
}

export interface Permission {
    id: number
    name: string
}

export interface UsersListResponse {
    items: User[]
    pagination: {
        total: number
        per_page: number
        current_page: number
        last_page: number
    }
}

export interface CreateUserPayload {
    name: string
    email: string
    password: string
    roles?: string[]
}

export interface UpdateUserPayload {
    name?: string
    email?: string
    password?: string | null
    roles?: string[]
}

export interface CreateRolePayload {
    name: string
    permissions?: string[]
}

export interface UpdateRolePayload {
    name?: string
    permissions?: string[]
}

export const useAdminService = () => {
    const { apiFetch } = useApi()
    const authStore = useAuthStore()

    const headers = () => ({
        Accept: 'application/json',
        Authorization: authStore.token ? `Bearer ${authStore.token}` : '',
    })

    // Users
    const getUsers = async (params: Record<string, unknown> = {}) => {
        return apiFetch<UsersListResponse>('/users', {
            method: 'GET',
            headers: headers(),
            params,
        })
    }

    const getUser = async (id: number | string) => {
        return apiFetch<User>(`/users/${id}`, {
            method: 'GET',
            headers: headers(),
        })
    }

    const createUser = async (payload: CreateUserPayload) => {
        return apiFetch<User>('/users', {
            method: 'POST',
            headers: headers(),
            body: payload,
        })
    }

    const updateUser = async (id: number | string, payload: UpdateUserPayload) => {
        return apiFetch<User>(`/users/${id}`, {
            method: 'PATCH',
            headers: headers(),
            body: payload,
        })
    }

    const deleteUser = async (id: number | string) => {
        return apiFetch<null>(`/users/${id}`, {
            method: 'DELETE',
            headers: headers(),
        })
    }

    // Roles
    const getRoles = async (params: Record<string, unknown> = {}) => {
        return apiFetch<Role[]>('/roles', {
            method: 'GET',
            headers: headers(),
            params,
        })
    }
    
    const getRole = async (id: number | string) => {
        return apiFetch<Role>(`/roles/${id}`, {
            method: 'GET',
            headers: headers(),
        })
    }

    const createRole = async (payload: CreateRolePayload) => {
        return apiFetch<Role>('/roles', {
            method: 'POST',
            headers: headers(),
            body: payload,
        })
    }

    const updateRole = async (id: number | string, payload: UpdateRolePayload) => {
        return apiFetch<Role>(`/roles/${id}`, {
            method: 'PATCH',
            headers: headers(),
            body: payload,
        })
    }

    const deleteRole = async (id: number | string) => {
        return apiFetch<null>(`/roles/${id}`, {
            method: 'DELETE',
            headers: headers(),
        })
    }

    // Permissions
    const getPermissions = async () => {
        return apiFetch<Permission[]>('/permissions', {
            method: 'GET',
            headers: headers(),
        })
    }

    return {
        getUsers, getUser, createUser, updateUser, deleteUser,
        getRoles, getRole, createRole, updateRole, deleteRole,
        getPermissions
    }
}
