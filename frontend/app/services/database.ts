import Dexie, { type Table } from 'dexie'

export interface LotEnAttente {
  id?:            number
  code:           string
  producteur_id:  string
  cooperative_id: string
  poids_kg:       number
  humidite_pct:   number
  prix_kg_fcfa:   number
  date_pesee:     string
  statut:         'en_attente' | 'en_cours' | 'erreur'
  tentatives:     number
  cree_le:        string
}

class TracaCajouDB extends Dexie {
  lotsEnAttente!: Table<LotEnAttente, number>

  constructor() {
    super('TracaCajouDB')
    this.version(1).stores({
      lotsEnAttente: '++id, code, statut, cree_le',
    })
  }
}

export const database = new TracaCajouDB()
