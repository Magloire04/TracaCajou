import { database, type LotEnAttente } from './database'

type PushFn = (lot: Omit<LotEnAttente, 'id' | 'statut' | 'tentatives' | 'cree_le'>) => Promise<unknown>

const MAX_TENTATIVES = 3

export class SyncQueue {
  constructor(private readonly pushToApi: PushFn) {}

  async enqueue(lot: Omit<LotEnAttente, 'id' | 'statut' | 'tentatives' | 'cree_le'>): Promise<void> {
    await database.lotsEnAttente.add({
      ...lot,
      statut:     'en_attente',
      tentatives: 0,
      cree_le:    new Date().toISOString(),
    })
  }

  async getPendingCount(): Promise<number> {
    return database.lotsEnAttente.count()
  }

  async processAll(): Promise<void> {
    const lots = await database.lotsEnAttente.toArray()
    for (const lot of lots) {
      if (!lot.id) continue
      if (lot.tentatives >= MAX_TENTATIVES) continue
      await database.lotsEnAttente.update(lot.id, { statut: 'en_cours' })
      try {
        const { id: _id, statut: _statut, tentatives: _tentatives, cree_le: _cree_le, ...payload } = lot
        await this.pushToApi(payload as Parameters<typeof this.pushToApi>[0])
        await database.lotsEnAttente.delete(lot.id)
      } catch {
        const tentatives = lot.tentatives + 1
        await database.lotsEnAttente.update(lot.id, {
          statut:     tentatives >= MAX_TENTATIVES ? 'erreur' : 'en_attente',
          tentatives,
        })
      }
    }
  }
}
