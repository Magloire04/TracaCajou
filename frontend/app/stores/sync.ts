import { defineStore } from 'pinia'

export const useSyncStore = defineStore('sync', {
  state: () => ({
    pendingCount: 0,
    isSyncing: false,
  }),
})
