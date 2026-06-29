import { test, expect } from '@playwright/test'

test.describe('Parcours complet agent TraçaCajou', () => {
  test('login → dashboard lots visible', async ({ page }) => {
    await page.goto('/login')
    await expect(page.locator('text=TraçaCajou')).toBeVisible()
    await page.getByLabel(/email/i).fill('agent@agpk.bj')
    await page.getByLabel(/mot de passe|password/i).fill('Demo@2026!')
    await page.getByRole('button', { name: /connexion|sign in/i }).click()
    await expect(page).toHaveURL('/lots')
    await expect(page.locator('text=Lots')).toBeVisible()
  })

  test('page de vérification publique accessible sans auth', async ({ page }) => {
    // This test uses a known UUID from the DemoSeeder
    // In practice, get the UUID from the seed output
    const testUuid = 'REPLACE_WITH_SEED_UUID'
    await page.goto(`/certificats/${testUuid}/verify`)
    await expect(page.locator('text=TraçaCajou')).toBeVisible()
    // Page loads without redirect to login
    await expect(page).not.toHaveURL('/login')
  })
})
