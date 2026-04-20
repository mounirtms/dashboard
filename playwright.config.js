// @ts-check
const { defineConfig, devices } = require('@playwright/test');

/**
 * Playwright Test Configuration
 * TechnoStationery Magento 2 - Comprehensive Test Suite
 */
module.exports = defineConfig({
  testDir: './tests/playwright',
  
  // Run tests in parallel across files
  fullyParallel: true,
  
  // Fail the build on CI if you accidentally left test.only in the source code
  forbidOnly: !!process.env.CI,
  
  // Retry on CI only
  retries: process.env.CI ? 2 : 0,
  
  // Opt out of parallel tests on CI
  workers: process.env.CI ? 1 : undefined,
  
  // Timeout for each test
  timeout: 60000,
  
  // Reporter configuration
  reporter: [
    ['html', { outputFolder: 'tests/playwright-report', open: 'never' }],
    ['list'],
    ['json', { outputFile: 'tests/playwright-results.json' }]
  ],
  
  // Shared settings for all tests
  use: {
    // Base URL for navigation
    baseURL: process.env.BASE_URL || 'https://dev.technostationery.com',
    
    // Collect trace when retrying the failed test
    trace: 'on-first-retry',
    
    // Screenshot on failure
    screenshot: 'only-on-failure',
    
    // Video on failure
    video: 'retain-on-failure',
    
    // Accept cookies by default
    locale: 'fr-FR',
    timezoneId: 'Africa/Algiers',
    
    // Viewport for desktop
    viewport: { width: 1280, height: 720 },
  },
  
  // Project configurations
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
    },
    // Mobile tests (uncomment if needed)
    // {
    //   name: 'Mobile Chrome',
    //   use: { ...devices['Pixel 5'] },
    // },
    // {
    //   name: 'Mobile Safari',
    //   use: { ...devices['iPhone 12'] },
    // },
  ],
  
  // Global setup
  globalSetup: require.resolve('./tests/playwright/global-setup.js'),
  
  // Output directory for screenshots and videos
  outputDir: 'tests/test-results',
});
