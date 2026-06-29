import { defineStore } from 'pinia'
import type { SyncQueue } from '~/services/syncQueue'

export const useSyncStore = defineStore('sync', {
  state: () => ({
    pendingCount: 0,
    isSyncing:    false,
  }),
  actions: {
    async refreshCount(queue: SyncQueue): Promise<void> {
      this.pendingCount = await queue.getPendingCount()
    },
    async syncAll(queue: SyncQueue): Promise<void> {
      if (this.isSyncing) return
      this.isSyncing = true
      try {
        await queue.processAll()
      } finally {
        this.isSyncing = false
        await this.refreshCount(queue)
      }
    },
  },
})
