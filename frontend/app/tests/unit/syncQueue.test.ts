import { describe, it, expect, vi, beforeEach } from 'vitest'
import { SyncQueue } from '~/services/syncQueue'

// Mock Dexie (IndexedDB non disponible en jsdom)
vi.mock('~/services/database', () => ({
  database: {
    lotsEnAttente: {
      add:     vi.fn().mockResolvedValue(1),
      toArray: vi.fn().mockResolvedValue([
        { id: 1, code: 'AGPKL001', poids_kg: 100, statut: 'en_attente', tentatives: 0 },
      ]),
      delete:  vi.fn().mockResolvedValue(undefined),
      update:  vi.fn().mockResolvedValue(undefined),
      count:   vi.fn().mockResolvedValue(1),
    },
  },
}))

describe('SyncQueue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('enqueue ajoute un lot à la base locale', async () => {
    const queue = new SyncQueue(vi.fn())
    await queue.enqueue({
      code:           'AGPKL001',
      producteur_id:  'p1',
      cooperative_id: 'c1',
      poids_kg:       100,
      humidite_pct:   7,
      prix_kg_fcfa:   270,
      date_pesee:     '2026-06-29',
    })
    const { database } = await import('~/services/database')
    expect(database.lotsEnAttente.add).toHaveBeenCalled()
  })

  it('getPendingCount retourne le nombre de lots en attente', async () => {
    const queue = new SyncQueue(vi.fn())
    const count = await queue.getPendingCount()
    expect(count).toBe(1)
  })

  it('processAll appelle le callback pour chaque lot en attente', async () => {
    const pushFn = vi.fn().mockResolvedValue({ data: { certificat: { public_uuid: 'uuid1' } } })
    const queue  = new SyncQueue(pushFn)
    await queue.processAll()
    expect(pushFn).toHaveBeenCalledTimes(1)
  })
})
