export default defineNuxtRouteMiddleware((to) => {
  const store = useAuthStore()
  if (!store.isAuthenticated && to.path !== '/login') {
    return navigateTo('/login')
  }
  if (store.isAuthenticated && to.path === '/login') {
    return navigateTo('/lots')
  }
})
