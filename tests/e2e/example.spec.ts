import { test, expect } from '@playwright/test';

test('landing page loads', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveTitle(/Shilla/);
});

test('login page loads', async ({ page }) => {
  await page.goto('/login');
  await expect(page.locator('form')).toBeVisible();
});

test('register page loads', async ({ page }) => {
  await page.goto('/register');
  await expect(page.locator('form')).toBeVisible();
});

test('about page loads', async ({ page }) => {
  await page.goto('/about');
  await expect(page.locator('h1')).toContainText('About');
});

test('faq page loads', async ({ page }) => {
  await page.goto('/faq');
  await expect(page.locator('h1')).toContainText('FAQ');
});
