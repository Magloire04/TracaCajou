export function useAuth() {
  const store = useAuthStore()
  const api = useApi()

  async function login(email: string, password: string): Promise<void> {
    await api.initCsrf()
    const res = await api.post<{
      data: {
        id: string
        prenom: string
        nom: string
        cooperative_id: string
        cooperative_code: string
      }
    }>('/auth/login', { email, password })
    store.setAgent(res.data)
  }

  async function logout(): Promise<void> {
    await api.post('/auth/logout', {})
    store.clearAgent()
    await navigateTo('/login')
  }

  return {
    login,
    logout,
    agent: computed(() => store.agent),
    isAuthenticated: computed(() => store.isAuthenticated),
  }
}
