import { test, expect } from '@playwright/test';
import { accounts } from './accounts.js';

const sales = accounts.find((a) => a.role === 'Sales');
const FORM_ROUTE = 'crmsales';   // ← palitan kung ibang route ang may form

async function loginAsSales(page) {
  await page.goto('loginadmin');
  await page.fill('#email', sales.email);
  await page.fill('#password', sales.password);
  await Promise.all([
    page.waitForURL(/\/(salesmarket|crmsales)$/),
    page.click('button[type="submit"]'),
  ]);
  await page.goto(FORM_ROUTE);
}

async function fillStep1(page, { name, contact = '09171234567' }) {
  await page.fill('#crm_client_name', name);
  await page.fill('#crm_house_street', 'Unit 2B, 123 Rizal Street');

  // Galing sa psgc.cloud API ang region/city/barangay, kaya kailangan ng internet
  await expect(page.locator('#crm_region option')).not.toHaveCount(1, { timeout: 15000 });
  await page.selectOption('#crm_region', '1300000000');          // NCR

  await expect(page.locator('#crm_city')).toBeEnabled({ timeout: 15000 });
  await page.selectOption('#crm_city', { index: 1 });            // unang city

  await expect(page.locator('#crm_barangay')).toBeEnabled({ timeout: 15000 });
  await page.selectOption('#crm_barangay', { index: 1 });        // unang barangay

  await page.fill('#crm_contact_number', contact);
}

test('sales: napupunan at nasusumite ang CRM inquiry', async ({ page }) => {
  // Kung may lumabas na alert() habang tumatakbo, ibig sabihin may kulang sa form
  const dialogs = [];
  page.on('dialog', async (d) => {
    dialogs.push(d.message());
    await d.dismiss();
  });

  const clientName = `TEST Playwright ${Date.now()}`;
  await loginAsSales(page);

  // ── Step 1: Client Info ──
  await expect(page.locator('#crm-step-1')).toHaveClass(/active/);
  await fillStep1(page, { name: clientName });
  await page.getByRole('button', { name: 'Next: Project Details' }).click();

  // ── Step 2: Project Details ──
  await expect(page.locator('#crm-step-2')).toHaveClass(/active/);
  await page.locator('label:has(#crm_nature_modular)').click();
  await expect(page.locator('#crm_mode_sitevisit')).toBeChecked();   // auto-set
  await expect(page.locator('#crm_mode_ready')).toBeDisabled();

  await page.fill('#crm_project_type', 'Residential');

  for (const group of ['#project_scope_checkboxes', '#measuring_space_checkboxes']) {
    const boxes = page.locator(`${group} input[type="checkbox"]`);
    if (await boxes.count()) await boxes.first().check();
  }
  await page.getByRole('button', { name: 'Next: Assignment' }).click();

  // ── Step 3: Assignment ──
  await expect(page.locator('#crm-step-3')).toHaveClass(/active/);
  await page.getByRole('button', { name: 'Next: Review' }).click();

  // ── Step 4: Review ──
  await expect(page.locator('#crm-step-4')).toHaveClass(/active/);
  const review = page.locator('#crm_review_content');
  await expect(review).toContainText(clientName);
  await expect(review).toContainText('09171234567');
  await expect(review).toContainText('Modular');

  // ── Submit ──
  await page.getByRole('button', { name: 'Submit Form' }).click();
  await expect(page.locator('#crmToastContainer'))
    .toContainText('Inquiry submitted successfully', { timeout: 10000 });

  console.log('TOAST:', await page.locator('#crmToastContainer').innerText());
  expect(dialogs).toEqual([]);
});

test('step 1: may alert kapag walang laman', async ({ page }) => {
  await loginAsSales(page);
  const dialog = page.waitForEvent('dialog');
  await page.getByRole('button', { name: 'Next: Project Details' }).click();
  const d = await dialog;
  expect(d.message()).toContain('Client Name');
  await d.dismiss();
});

test('step 1: 10 digits lang ang contact number, hindi papasa', async ({ page }) => {
  await loginAsSales(page);
  await fillStep1(page, { name: 'TEST validation', contact: '0917123456' });
  const dialog = page.waitForEvent('dialog');
  await page.getByRole('button', { name: 'Next: Project Details' }).click();
  const d = await dialog;
  expect(d.message()).toContain('exactly 11 digits');
  await d.dismiss();
});