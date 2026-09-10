import type { Page } from '@playwright/test';
import { requireLoginConfig } from './auth-utils';
import { expect, test } from '../test';
import { waitForKnownBlockingUi } from './ui-settle';

export const adminShell = '#sidebar';
export const mainContent = '#main-content';

export const openAdmin = async (page: Page) => {
  requireLoginConfig();

  await page.goto('/admin.php');
  await page.waitForURL(/admin\.php|user_events\.php|login\.php/, { timeout: 15000 });
  test.skip(
    !page.url().includes('/admin.php'),
    'The configured login is not a main admin account with access to admin.php.'
  );

  await expect(page.locator(adminShell)).toBeVisible();
  await waitForKnownBlockingUi(page);
};

export const openAdminRoute = async (page: Page, route: string) => {
  await openAdmin(page);
  await page.goto(`/admin.php#!/${route}`);
  await expect(page).toHaveURL(new RegExp(`admin\\.php#!/${route.replace('#', '\\#')}`));
  await expect(page.locator(mainContent)).toBeVisible();
  await waitForKnownBlockingUi(page);
};
