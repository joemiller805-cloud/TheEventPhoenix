import { expect, test } from './test';

test.describe('Public smoke coverage', () => {
  test('landing page loads core marketing content', async ({ page }) => {
    await page.goto('/landing.php');

    await expect(page).toHaveTitle(/The Event Phoenix Event Management Software/i);
    await expect(page.locator('#pgHeader')).toHaveText('Dynamic Event Management Software');
    await expect(page.getByText('Centralized Event Management', { exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'PSUG Events' })).toBeVisible();
  });

  test('admin login form renders expected fields', async ({ page }) => {
    await page.goto('/login.php');

    await expect(page.getByRole('heading', { name: 'Admin/Staff Login' })).toBeVisible();
    await expect(page.getByText('Please log in to continue:')).toBeVisible();
    await expect(page.locator('select[name="accountid"]')).toBeVisible();
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="pass"]')).toBeVisible();
    await expect(page.getByRole('link', { name: 'I forgot my password' })).toHaveAttribute(
      'href',
      /reset_password\.php/
    );
  });

  test('admin login rejects invalid credentials when account data is available', async ({ page }) => {
    await page.goto('/login.php');

    const accountSelect = page.locator('select[name="accountid"]');
    await expect(accountSelect).toBeVisible();
    await page.waitForTimeout(1500);

    const optionCount = await accountSelect.locator('option').count();
    test.skip(
      optionCount <= 1,
      'No account records were loaded in this environment, so invalid-login submission cannot be exercised yet.'
    );

    const firstAccount = await accountSelect.locator('option').nth(1).getAttribute('value');
    expect(firstAccount).toBeTruthy();

    await accountSelect.selectOption(firstAccount!);
    await page.locator('input[name="email"]').fill('e2e-invalid@example.com');
    await page.locator('input[name="pass"]').fill('definitely-not-the-right-password');
    await page.getByRole('button', { name: 'Login' }).click();

    await expect(page.locator('.alert.alert-danger')).toBeVisible();
    await expect(page.locator('.alert.alert-danger')).toContainText('Invalid login credentials');
    await expect(page).toHaveURL(/login\.php/);
  });
});
