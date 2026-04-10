# E2E Tests

Run from Windows (not WSL2). Requires Playwright installed on Windows.

## Setup

```powershell
cd tests/e2e
npm init -y
npm install @playwright/test
npx playwright install chromium
```

## Run

```powershell
npx playwright test
```

Ensure the Marko dev server is running in WSL2 before running tests:

```bash
# In WSL2
cd /home/necro/greenfield/shilla
php -S localhost:8000 -t public/
```

## Environment

Set `BASE_URL` if the dev server runs on a different port:

```powershell
$env:BASE_URL="http://localhost:9000"
npx playwright test
```
