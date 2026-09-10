import fs from 'fs';
import path from 'path';
import { test as setup } from './test';
import {
  authStatePath,
  eventAuthStatePath,
  loginAsEventUser,
  loginAsTestAccount,
  requireEventLoginConfig,
  requireLoginConfig
} from './shared/auth-utils';

setup('authenticate admin test account', async ({ page }) => {
  requireLoginConfig();
  fs.mkdirSync(path.dirname(authStatePath), { recursive: true });

  await loginAsTestAccount(page);
  await page.context().storageState({ path: authStatePath });
});

setup('authenticate event test account', async ({ page }) => {
  requireEventLoginConfig();
  fs.mkdirSync(path.dirname(eventAuthStatePath), { recursive: true });

  await loginAsEventUser(page);
  await page.context().storageState({ path: eventAuthStatePath });
});
