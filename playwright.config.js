// @ts-check
import { defineConfig, devices } from '@playwright/test';

/**
 * Read environment variables from file.
 * https://github.com/motdotla/dotenv
 */
// import dotenv from 'dotenv';
// import path from 'path';
// dotenv.config({ path: path.resolve(__dirname, '.env') });

/**
 * @see https://playwright.dev/docs/test-configuration
 */
export default defineConfig({
  testDir: './tests',
  fullyParallel: false,
  workers: 1,                // isa-isa lang, hindi sabay-sabay
  reporter: 'html',
  use: {
    baseURL: 'http://localhost/nobleaccounting/',
    headless: false,         // ipakita ang browser
    launchOptions: {
      slowMo: 1000,          // 1 segundo bawat aksyon
    },
    video: 'on',             // irekord ang bawat test
    screenshot: 'on',
    trace: 'on',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
});