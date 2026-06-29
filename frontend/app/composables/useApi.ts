export function useApi() {
  const config = useRuntimeConfig()
  const baseURL = config.public.apiBase as string

  function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
    return match ? decodeURIComponent(match[1]) : ''
  }

  async function initCsrf(): Promise<void> {
    await $fetch('/sanctum/csrf-cookie', { baseURL, credentials: 'include' })
  }

  async function get<T>(path: string): Promise<T> {
    return $fetch<T>(`/api/v1${path}`, {
      baseURL,
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })
  }

  async function post<T>(path: string, body: unknown): Promise<T> {
    return $fetch<T>(`/api/v1${path}`, {
      method: 'POST',
      baseURL,
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
      body,
    })
  }

  async function del<T>(path: string): Promise<T> {
    return $fetch<T>(`/api/v1${path}`, {
      method: 'DELETE',
      baseURL,
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
    })
  }

  return { get, post, del, initCsrf }
}
