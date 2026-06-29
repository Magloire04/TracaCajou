import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '~/stores/auth'

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('est non authentifié par défaut', () => {
    const store = useAuthStore()
    expect(store.isAuthenticated).toBe(false)
    expect(store.agent).toBeNull()
  })

  it("setAgent met à jour l'état", () => {
    const store = useAuthStore()
    store.setAgent({
      id: '01H...',
      prenom: 'Kossi',
      nom: 'Hounsou',
      cooperative_id: 'c1',
      cooperative_code: 'AGPK',
    })
    expect(store.isAuthenticated).toBe(true)
    expect(store.agent?.cooperative_code).toBe('AGPK')
  })

  it("clearAgent réinitialise l'état", () => {
    const store = useAuthStore()
    store.setAgent({
      id: '01H...',
      prenom: 'Kossi',
      nom: 'H',
      cooperative_id: 'c1',
      cooperative_code: 'AGPK',
    })
    store.clearAgent()
    expect(store.isAuthenticated).toBe(false)
  })
})
