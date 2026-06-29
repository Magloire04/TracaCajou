import { SyncQueue } from '~/services/syncQueue'

export function useOfflineSync() {
  const api       = useApi()
  const syncStore = useSyncStore()
  const authStore = useAuthStore()

  const pushToApi = (lot: Record<string, unknown>) =>
    api.post(`/cooperatives/${authStore.agent?.cooperative_id}/lots`, lot)

  const queue = new SyncQueue(pushToApi as Parameters<typeof SyncQueue>[0])

  async function syncNow() {
    if (!navigator.onLine) return
    await syncStore.syncAll(queue)
  }

  onMounted(async () => {
    await syncStore.refreshCount(queue)
    window.addEventListener('online', syncNow)
  })

  onUnmounted(() => {
    window.removeEventListener('online', syncNow)
  })

  return {
    syncNow,
    queue,
    isSyncing:    computed(() => syncStore.isSyncing),
    pendingCount: computed(() => syncStore.pendingCount),
  }
}
