// Use CDP setup when USE_CDP=1, otherwise default Playwright
const useCdp = process.env.USE_CDP === '1';
const { test, expect } = useCdp
  ? require('./cdp-setup')
  : require('@playwright/test');
import type { Page } from '@playwright/test';

// Helper: register a fresh user and return the page (logged in at /game)
async function registerUser(page: Page, username: string, email: string, password: string) {
  await page.goto('/register');
  await page.fill('input[name="username"]', username);
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForURL('/game');
}

// Helper: log in and return the page
async function loginUser(page: Page, identifier: string, password: string) {
  await page.goto('/login');
  await page.fill('input[name="identifier"]', identifier);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForURL('/game');
}

// --- Public Pages ---

test('landing page loads with correct title', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveTitle(/Shilla/);
  await expect(page.locator('text=Enter a World of Swords')).toBeVisible();
});

test('landing page has register CTA', async ({ page }) => {
  await page.goto('/');
  const cta = page.locator('a[href="/register"]', { hasText: 'Start Your Adventure' });
  await expect(cta).toBeVisible();
});

test('about page loads', async ({ page }) => {
  await page.goto('/about');
  await expect(page.locator('h1')).toContainText('About Shilla');
});

test('faq page loads with accordion', async ({ page }) => {
  await page.goto('/faq');
  await expect(page.locator('h1')).toContainText('Frequently Asked Questions');
  // Click first question to expand
  const firstQuestion = page.locator('button').first();
  await firstQuestion.click();
  // Answer should be visible
  await expect(page.locator('text=browser-based multiplayer RPG')).toBeVisible();
});

test('navigation links work from landing page', async ({ page }) => {
  await page.goto('/');
  await page.click('a[href="/about"]');
  await expect(page).toHaveURL('/about');
  await page.click('a[href="/faq"]');
  await expect(page).toHaveURL('/faq');
  await page.click('a[href="/"]');
  await expect(page).toHaveURL('/');
});

// --- Auth Pages ---

test('login page renders with form and social buttons', async ({ page }) => {
  await page.goto('/login');
  await expect(page.locator('input[name="identifier"]')).toBeVisible();
  await expect(page.locator('input[name="password"]')).toBeVisible();
  await expect(page.locator('button[type="submit"]')).toContainText('Log In');
  await expect(page.locator('a[href="/auth/discord"]')).toBeVisible();
  await expect(page.locator('a[href="/auth/google"]')).toBeVisible();
  await expect(page.locator('a[href="/auth/github"]')).toBeVisible();
});

test('register page renders with form and social buttons', async ({ page }) => {
  await page.goto('/register');
  await expect(page.locator('input[name="username"]')).toBeVisible();
  await expect(page.locator('input[name="email"]')).toBeVisible();
  await expect(page.locator('input[name="password"]')).toBeVisible();
  await expect(page.locator('button[type="submit"]')).toContainText('Create Account');
  await expect(page.locator('a[href="/auth/discord"]')).toBeVisible();
});

test('login page links to register', async ({ page }) => {
  await page.goto('/login');
  await page.click('a[href="/register"]');
  await expect(page).toHaveURL('/register');
});

test('register page links to login', async ({ page }) => {
  await page.goto('/register');
  await page.click('a[href="/login"]');
  await expect(page).toHaveURL('/login');
});

// --- Registration Flow ---

test('user can register and lands on character select', async ({ page }) => {
  const ts = Date.now();
  await registerUser(page, `testuser_${ts}`, `test_${ts}@example.com`, 'password123');

  await expect(page).toHaveURL('/game');
  await expect(page.locator('h2')).toContainText('Choose Your Character');
  await expect(page.locator('text=You don\'t have any characters yet')).toBeVisible();
});

test('register rejects duplicate username', async ({ page }) => {
  const ts = Date.now();
  await registerUser(page, `dupe_${ts}`, `dupe1_${ts}@example.com`, 'password123');

  // Try to register second user with same username
  await page.goto('/register');
  await page.fill('input[name="username"]', `dupe_${ts}`);
  await page.fill('input[name="email"]', `dupe2_${ts}@example.com`);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');

  // Should stay on register page with error
  await expect(page).toHaveURL('/register');
  await expect(page.locator('text=Username already taken')).toBeVisible();
});

// --- Login Flow (using seeded users) ---

test('seeded player can log in with username', async ({ page }) => {
  await loginUser(page, 'testplayer', 'password');
  await expect(page).toHaveURL('/game');
  await expect(page.getByText('testplayer', { exact: true })).toBeVisible();
});

test('seeded player can log in with email', async ({ page }) => {
  await loginUser(page, 'player@shilla.org', 'password');
  await expect(page).toHaveURL('/game');
});

test('seeded builder can log in', async ({ page }) => {
  await loginUser(page, 'testbuilder', 'password');
  await expect(page).toHaveURL('/game');
  await expect(page.getByText('testbuilder', { exact: true })).toBeVisible();
});

test('seeded admin can log in', async ({ page }) => {
  await loginUser(page, 'testadmin', 'password');
  await expect(page).toHaveURL('/game');
  await expect(page.getByText('testadmin', { exact: true })).toBeVisible();
});

test('login rejects invalid credentials', async ({ page }) => {
  await page.goto('/login');
  await page.fill('input[name="identifier"]', 'nonexistent@example.com');
  await page.fill('input[name="password"]', 'wrongpassword');
  await page.click('button[type="submit"]');

  await expect(page.locator('text=Invalid credentials')).toBeVisible();
});

// --- Lobby Layout ---

test('character select shows lobby layout not game shell', async ({ page }) => {
  await loginUser(page, 'testplayer', 'password');

  // Should see lobby elements
  await expect(page.locator('text=Log Out')).toBeVisible();
  await expect(page.getByText('testplayer', { exact: true })).toBeVisible();
  await expect(page.locator('text=character slots used')).toBeVisible();

  // Should NOT see game shell elements
  await expect(page.locator('text=Say something')).not.toBeVisible();
});

// --- Logout Flow ---

test('user can log out and is redirected to landing', async ({ page }) => {
  await loginUser(page, 'testplayer', 'password');
  await expect(page).toHaveURL('/game');

  // Click logout
  await page.click('button:has-text("Log Out")');
  await expect(page).toHaveURL('/');

  // Verify cannot access /game
  await page.goto('/game');
  await expect(page).toHaveURL('/login');
});

// --- Unauthenticated Access ---

test('unauthenticated user is redirected from /game to /login', async ({ page }) => {
  await page.goto('/game');
  await expect(page).toHaveURL('/login');
});
