export default defineNuxtRouteMiddleware(async () => {
    const authStore = useAuthStore()

    await authStore.hydrate()

    if (!authStore.isAuthenticated) {
        return navigateTo('/login')
    }

    if (!authStore.hasRole('SUPER_ADMIN')) {
        if (authStore.hasRole('LIBRARY_STAFF')) {
            return navigateTo('/staff')
        }
        if (authStore.hasRole('MEMBER')) {
            return navigateTo('/member')
        }
        return navigateTo('/')
    }
})
