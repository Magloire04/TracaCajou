import { defineStore } from 'pinia'

interface Agent {
  id: string
  prenom: string
  nom: string
  cooperative_id: string
  cooperative_code: string
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    agent: null as Agent | null,
  }),
  getters: {
    isAuthenticated: (state) => state.agent !== null,
  },
  actions: {
    setAgent(agent: Agent) {
      this.agent = agent
    },
    clearAgent() {
      this.agent = null
    },
  },
  persist: true,
})
