import { expect, test } from '../test';
import {
  publicEventName,
  publicEventNamePattern,
  publicEventPathPattern,
  publicEventRegisterPathPattern,
  publicEventsPath,
  publicEventsPathPattern
} from '../shared/e2e-config';
import { waitForKnownBlockingUi } from '../shared/ui-settle';

function buildAttendee() {
  const token = Date.now().toString();
  return {
    token,
    email: `playwright.attendee.${token}@example.com`,
    firstName: 'Playwright',
    lastName: `Attendee${token.slice(-4)}`,
    title: 'QA Analyst',
    business: 'Playwright Test Co',
    address1: '123 Test Street',
    address2: 'Suite 200',
    city: 'Chicago',
    state: 'IL',
    zip: '60601',
    phone: '3125550101',
    website: 'https://example.com',
    ecName: 'Emergency Contact',
    ecEmail: `emergency.${token}@example.com`,
    ecPhone: '3125550199'
  };
}

function futureExpiry() {
  const now = new Date();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const year = String((now.getFullYear() + 2) % 100).padStart(2, '0');
  return {
    month,
    year,
    slash: `${month}/${year}`,
    spaced: `${month} / ${year}`
  };
}

async function fillIfVisible(locator: any, value: string) {
  if (!(await locator.count())) return false;
  const target = locator.first();
  if (!(await target.isVisible().catch(() => false))) return false;
  await target.fill(value);
  return true;
}

async function selectIfVisible(locator: any, value: string) {
  if (!(await locator.count())) return false;
  const target = locator.first();
  if (!(await target.isVisible().catch(() => false))) return false;
  await target.selectOption(value);
  return true;
}

async function selectFirstRegistrationType(page: any) {
  const select = page.locator('select[ng-model="reg.registration_typeid"]').first();
  if ((await select.count()) && (await select.isVisible().catch(() => false))) {
    await page.waitForFunction(() => {
      const el = document.querySelector('select[ng-model="reg.registration_typeid"]') as HTMLSelectElement | null;
      return !!el && el.options.length > 0;
    });

    const value = await select.locator('option').first().getAttribute('value');
    if (value) {
      await select.selectOption(value);
      return;
    }
  }

  await page.evaluate(() => {
    const form = document.querySelector('form[name="regForm"]');
    if (!form || !(window as any).angular) return;
    const scope = (window as any).angular.element(form).scope();
    const reg = scope?.registrations?.[0];
    const types = scope?.availableRegTypes?.() || [];
    if (!reg || !types.length || reg.registration_typeid) return;
    reg.registration_typeid = types[0].id;
    scope.setSelectedRegType?.();
    scope.$apply();
  });
}

async function fillDynamicRequiredFields(page: any, attendee: ReturnType<typeof buildAttendee>) {
  await page.evaluate((payload) => {
    const form = document.querySelector('form[name="regForm"]');
    if (!form || !(window as any).angular) return;

    const scope = (window as any).angular.element(form).scope();
    const reg = scope?.registrations?.[0];
    if (!reg) return;

    const setIfBlank = (key: string, value: string) => {
      if (reg[key] === undefined || reg[key] === null || reg[key] === '') reg[key] = value;
    };

    setIfBlank('title', payload.title);
    setIfBlank('business', payload.business);
    setIfBlank('address1', payload.address1);
    setIfBlank('address2', payload.address2);
    setIfBlank('city', payload.city);
    setIfBlank('state', payload.state);
    setIfBlank('zip', payload.zip);
    setIfBlank('phone', payload.phone);
    setIfBlank('web_address', payload.website);
    setIfBlank('vendor_access', '1');
    setIfBlank('dietary_restrictions', 'None');
    setIfBlank('ec1_name', payload.ecName);
    setIfBlank('ec1_email', payload.ecEmail);
    setIfBlank('ec1_phone_prim', payload.ecPhone);
    setIfBlank('ec1_phone_alt', payload.ecPhone);
    setIfBlank('ec2_name', payload.ecName);
    setIfBlank('ec2_email', payload.ecEmail);
    setIfBlank('ec2_phone_prim', payload.ecPhone);
    setIfBlank('ec2_phone_alt', payload.ecPhone);

    const extraFields = Object.values(scope?.eventData?.extraFields || {});
    for (const field of extraFields as any[]) {
      if (String(field?.required) !== '1' || !field.field) continue;

      if (field.type === 't') reg[field.field] = reg[field.field] || `Playwright ${payload.token}`;
      if (field.type === 'e') reg[field.field] = reg[field.field] || payload.email;
      if (field.type === 's') {
        const optionKeys = Array.isArray(field.options)
          ? field.options.map((_: string, idx: number) => String(idx))
          : Object.keys(field.options || {});
        if (!reg[field.field] && optionKeys.length) reg[field.field] = optionKeys[0];
      }
      if (field.type === 'r') reg[field.field] = reg[field.field] || '0';
      if (field.type === 'c') {
        if (!Array.isArray(reg[field.field]) || !reg[field.field].length) {
          const firstOption = Array.isArray(field.options) ? field.options[0] : Object.values(field.options || {})[0];
          reg[field.field] = firstOption ? [firstOption] : [];
        }
      }
      if (field.type === 'a') reg[field.field] = 'yes';
    }

    scope.$apply();
  }, attendee);
}

async function fillTokenizerField(page: any, selectors: string[], value: string) {
  for (const selector of selectors) {
    const directField = page.locator(`#tokenizer-container ${selector}`).first();
    if ((await directField.count()) && (await directField.isVisible().catch(() => false))) {
      await directField.fill(value);
      return true;
    }
  }

  for (const frame of page.frames()) {
    if (frame === page.mainFrame()) continue;
    for (const selector of selectors) {
      const framedField = frame.locator(selector).first();
      if ((await framedField.count()) && (await framedField.isVisible().catch(() => false))) {
        await framedField.fill(value);
        return true;
      }
    }
  }

  return false;
}

async function fillTokenizerSelect(page: any, selectors: string[], value: string) {
  for (const selector of selectors) {
    const directSelect = page.locator(`#tokenizer-container ${selector}`).first();
    if ((await directSelect.count()) && (await directSelect.isVisible().catch(() => false))) {
      await directSelect.selectOption(value);
      return true;
    }
  }

  for (const frame of page.frames()) {
    if (frame === page.mainFrame()) continue;
    for (const selector of selectors) {
      const framedSelect = frame.locator(selector).first();
      if ((await framedSelect.count()) && (await framedSelect.isVisible().catch(() => false))) {
        await framedSelect.selectOption(value);
        return true;
      }
    }
  }

  return false;
}

async function completeBasysPayment(page: any, attendee: ReturnType<typeof buildAttendee>) {
  const expiry = futureExpiry();

  await page.waitForTimeout(1500);

  await fillTokenizerField(page, ['input[placeholder*="First Name" i]', 'input[name*="first_name" i]', 'input[id*="first" i]'], attendee.firstName);
  await fillTokenizerField(page, ['input[placeholder*="Last Name" i]', 'input[name*="last_name" i]', 'input[id*="last" i]'], attendee.lastName);
  await fillTokenizerField(page, ['input[type="email"]', 'input[placeholder*="Email" i]', 'input[name*="email" i]'], attendee.email);
  await fillTokenizerField(page, ['input[placeholder="0000 0000 0000 0000"]', 'input[placeholder*="Card" i]', 'input[name*="card" i]', 'input[id*="card" i]'], '4111111111111111');

  const expFilled =
    (await fillTokenizerField(page, ['input[placeholder*="MM/YY" i]', 'input[placeholder*="MM / YY" i]', 'input[name*="exp" i]', 'input[id*="exp" i]'], expiry.slash)) ||
    (await fillTokenizerField(page, ['input[placeholder*="MM / YY" i]'], expiry.spaced));

  if (!expFilled) {
    await fillTokenizerSelect(page, ['select[name*="month" i]', 'select[id*="month" i]'], expiry.month);
    await fillTokenizerSelect(page, ['select[name*="year" i]', 'select[id*="year" i]'], `20${expiry.year}`);
  }

  await fillTokenizerField(page, ['input[placeholder*="CVV" i]', 'input[placeholder*="CVC" i]', 'input[name*="cvv" i]', 'input[id*="cvv" i]'], '123');
  await fillTokenizerField(page, ['input[placeholder*="Address" i]', 'input[name*="address" i]', 'input[id*="address" i]'], attendee.address1);
  await fillTokenizerField(page, ['input[placeholder*="City" i]', 'input[name*="city" i]', 'input[id*="city" i]'], attendee.city);
  await fillTokenizerField(page, ['input[placeholder*="Zip" i]', 'input[placeholder*="Postal" i]', 'input[name*="zip" i]', 'input[id*="zip" i]'], attendee.zip);
  await fillTokenizerField(page, ['input[placeholder*="State" i]', 'input[name*="state" i]', 'input[id*="state" i]'], attendee.state);
  await fillTokenizerSelect(page, ['select[name*="state" i]', 'select[id*="state" i]'], attendee.state);
}

test.describe('Attendee registration coverage', () => {
  test(`can register an attendee for ${publicEventName} with a credit card`, async ({ page }) => {
    test.setTimeout(120_000);

    const attendee = buildAttendee();

    await page.goto(publicEventsPath);
    await expect(page).toHaveURL(publicEventsPathPattern);
    await waitForKnownBlockingUi(page);

    const testEventCard = page.locator('.card').filter({
      has: page.getByRole('heading', { name: publicEventNamePattern }),
      hasText: publicEventName
    }).first();

    await expect(testEventCard).toBeVisible();
    await testEventCard.getByRole('link', { name: /learn more/i }).click();

    await expect(page).toHaveURL(publicEventPathPattern);
    if (!/\/register(?:\/|$)/.test(page.url())) {
      await page.getByRole('link', { name: /^register$/i }).click();
    }

    await page.waitForURL(publicEventRegisterPathPattern);
    await expect(page.locator('form[name="regForm"]')).toBeVisible();
    await waitForKnownBlockingUi(page);

    await fillIfVisible(page.locator('input[ng-model="reg.email"]'), attendee.email);
    await fillIfVisible(page.locator('input[ng-model="reg.first_name"]'), attendee.firstName);
    await fillIfVisible(page.locator('input[ng-model="reg.last_name"]'), attendee.lastName);
    await fillIfVisible(page.locator('input[ng-model="reg.title"]'), attendee.title);
    await fillIfVisible(page.locator('input[ng-model="reg.business"]'), attendee.business);
    await fillIfVisible(page.locator('input[ng-model="reg.address1"]'), attendee.address1);
    await fillIfVisible(page.locator('input[ng-model="reg.address2"]'), attendee.address2);
    await fillIfVisible(page.locator('input[ng-model="reg.city"]'), attendee.city);
    await selectIfVisible(page.locator('select[ng-model="reg.state"]'), attendee.state);
    await fillIfVisible(page.locator('input[ng-model="reg.zip"]'), attendee.zip);
    await fillIfVisible(page.locator('input[ng-model="reg.phone"]'), attendee.phone);
    await fillIfVisible(page.locator('input[ng-model="reg.web_address"]'), attendee.website);
    await fillIfVisible(page.locator('input[ng-model="reg.ec1_name"]'), attendee.ecName);
    await fillIfVisible(page.locator('input[ng-model="reg.ec1_email"]'), attendee.ecEmail);
    await fillIfVisible(page.locator('input[ng-model="reg.ec1_phone_prim"]'), attendee.ecPhone);
    await fillIfVisible(page.locator('input[ng-model="reg.ec1_phone_alt"]'), attendee.ecPhone);
    await fillIfVisible(page.locator('input[ng-model="reg.ec2_name"]'), attendee.ecName);
    await fillIfVisible(page.locator('input[ng-model="reg.ec2_email"]'), attendee.ecEmail);
    await fillIfVisible(page.locator('input[ng-model="reg.ec2_phone_prim"]'), attendee.ecPhone);
    await fillIfVisible(page.locator('input[ng-model="reg.ec2_phone_alt"]'), attendee.ecPhone);
    await selectIfVisible(page.locator('select[ng-model="reg.vendor_access"]'), '1');

    const dietarySelect = page.locator('select[ng-model="reg.dietary_restrictions"]').first();
    if ((await dietarySelect.count()) && (await dietarySelect.isVisible().catch(() => false))) {
      const optionCount = await dietarySelect.locator('option').count();
      if (optionCount > 1) {
        const firstDietaryValue = await dietarySelect.locator('option').nth(1).getAttribute('value');
        if (firstDietaryValue) await dietarySelect.selectOption(firstDietaryValue);
      }
    }

    await selectFirstRegistrationType(page);
    await fillDynamicRequiredFields(page, attendee);

    await expect(page.getByRole('button', { name: /Continue To Payment/i })).toBeVisible();
    await page.getByRole('button', { name: /Continue To Payment/i }).click();

    const paymentMethod = page.locator('select[ng-model="payment_method"]').first();
    if ((await paymentMethod.count()) && (await paymentMethod.isVisible().catch(() => false))) {
      await paymentMethod.selectOption('cc');
    }

    await expect(page.locator('#tokenizer-container')).toBeVisible({ timeout: 20000 });
    await completeBasysPayment(page, attendee);

    await page.getByRole('button', { name: /Complete Order/i }).click();

    const successMessage = page.locator('#resultMessage .alert.alert-success').filter({ hasText: 'Registration Complete' }).last();
    await expect(successMessage).toBeVisible({ timeout: 60000 });
    await expect(successMessage).toContainText('Thank you for your payment.');
    await expect(successMessage).toContainText('successfully processed');
    await expect(successMessage).toContainText(attendee.firstName);
    await expect(successMessage).toContainText(attendee.lastName);
  });
});
