export const usePermission = () => {
    const authStore = useAuthStore()

    const can = (permission: string): boolean => {
        return authStore.hasPermission(permission)
    }

    const hasRole = (role: string): boolean => {
        return authStore.hasRole(role)
    }

    return {
        can,
        hasRole,
    }
}
