export default defineNuxtRouteMiddleware(async () => {
    const authStore = useAuthStore()

    await authStore.hydrate()

    if (!authStore.isAuthenticated) {
        return navigateTo('/login')
    }

    const isStaff =
        authStore.hasRole('LIBRARY_STAFF') ||
        authStore.hasRole('SUPER_ADMIN')

    if (!isStaff) {
        return navigateTo('/member')
    }
})