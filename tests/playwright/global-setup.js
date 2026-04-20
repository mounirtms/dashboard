// Global setup - runs once before all tests
module.exports = async () => {
  console.log('Starting Playwright test suite for TechnoStationery Magento 2');
  console.log(`Base URL: ${process.env.BASE_URL || 'https://dev.technostationery.com'}`);
  console.log(`Timestamp: ${new Date().toISOString()}`);
};
