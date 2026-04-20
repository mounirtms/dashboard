// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Authentication and Account Test Suite
 * Tests login, registration, password reset, and account management
 */

test.describe('Authentication', () => {
  
  test('TC701: Successful login with valid credentials', async ({ page }) => {
    await page.goto('/customer/account/login/');
    await page.waitForLoadState('networkidle');
    
    // Fill login form
    await page.fill('#email', 'test@example.com');
    await page.fill('#pass', 'Test123456');
    await page.click('#send2');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    // Verify successful login
    await expect(page.locator('.welcome')).toBeVisible();
    await expect(page.locator('.welcome')).toContainText('Bonjour');
    
    // Verify account navigation
    await expect(page.locator('.customer-welcome')).toBeVisible();
  });

  test('TC702: Failed login with invalid credentials', async ({ page }) => {
    await page.goto('/customer/account/login/');
    await page.waitForLoadState('networkidle');
    
    // Fill with invalid credentials
    await page.fill('#email', 'nonexistent@example.com');
    await page.fill('#pass', 'WrongPassword123');
    await page.click('#send2');
    await page.waitForTimeout(2000);
    
    // Verify error message
    await expect(page.locator('.message-error')).toBeVisible();
    await expect(page.locator('.message-error')).toContainText('incorrect');
  });

  test('TC703: Login form validation - empty fields', async ({ page }) => {
    await page.goto('/customer/account/login/');
    await page.waitForLoadState('networkidle');
    
    // Submit empty form
    await page.click('#send2');
    await page.waitForTimeout(1000);
    
    // Verify validation errors
    const emailError = await page.locator('#email:invalid').count();
    expect(emailError).toBeGreaterThan(0);
  });

  test('TC704: Customer registration with valid data', async ({ page }) => {
    test.slow();
    
    const timestamp = Date.now();
    const email = `testuser${timestamp}@example.com`;
    
    await page.goto('/customer/account/create/');
    await page.waitForLoadState('networkidle');
    
    // Fill registration form
    await page.fill('#firstname', 'Test');
    await page.fill('#lastname', 'User');
    await page.fill('#email_address', email);
    await page.fill('#password', 'Test123456');
    await page.fill('#password-confirmation', 'Test123456');
    
    // Submit form
    await page.click('.action.submit.primary');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);
    
    // Verify successful registration
    await expect(page.locator('.message-success')).toBeVisible();
    await expect(page.locator('.welcome')).toContainText('Bonjour');
  });

  test('TC705: Registration validation - password mismatch', async ({ page }) => {
    await page.goto('/customer/account/create/');
    await page.waitForLoadState('networkidle');
    
    // Fill with mismatched passwords
    await page.fill('#firstname', 'Test');
    await page.fill('#lastname', 'User');
    await page.fill('#email_address', 'test@example.com');
    await page.fill('#password', 'Test123456');
    await page.fill('#password-confirmation', 'Different123');
    
    await page.click('.action.submit.primary');
    await page.waitForTimeout(2000);
    
    // Verify error message
    await expect(page.locator('.message-error')).toBeVisible();
  });

  test('TC706: Registration validation - invalid email format', async ({ page }) => {
    await page.goto('/customer/account/create/');
    await page.waitForLoadState('networkidle');
    
    // Fill with invalid email
    await page.fill('#firstname', 'Test');
    await page.fill('#lastname', 'User');
    await page.fill('#email_address', 'invalid-email');
    await page.fill('#password', 'Test123456');
    await page.fill('#password-confirmation', 'Test123456');
    
    await page.click('.action.submit.primary');
    await page.waitForTimeout(1000);
    
    // Verify email validation error
    const emailInput = page.locator('#email_address');
    await expect(emailInput).toBeInvalid();
  });

  test('TC707: Duplicate email registration', async ({ page }) => {
    await page.goto('/customer/account/create/');
    await page.waitForLoadState('networkidle');
    
    // Try to register with existing email
    await page.fill('#firstname', 'Test');
    await page.fill('#lastname', 'User');
    await page.fill('#email_address', 'test@example.com');
    await page.fill('#password', 'Test123456');
    await page.fill('#password-confirmation', 'Test123456');
    
    await page.click('.action.submit.primary');
    await page.waitForTimeout(2000);
    
    // Verify error about existing email
    await expect(page.locator('.message-error')).toBeVisible();
  });
});

test.describe('Password Recovery', () => {
  
  test('TC801: Password reset form accessible', async ({ page }) => {
    await page.goto('/customer/account/forgotpassword/');
    await page.waitForLoadState('networkidle');
    
    // Verify form
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
    await expect(page.locator('.page-title')).toContainText('Mot de passe');
  });

  test('TC802: Submit password reset for existing email', async ({ page }) => {
    await page.goto('/customer/account/forgotpassword/');
    await page.waitForLoadState('networkidle');
    
    // Submit valid email
    await page.fill('input[name="email"]', 'test@example.com');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2000);
    
    // Verify success message
    await expect(page.locator('.message-success')).toBeVisible();
    await expect(page.locator('.message-success')).toContainText('e-mail');
  });

  test('TC803: Submit password reset for non-existing email', async ({ page }) => {
    await page.goto('/customer/account/forgotpassword/');
    await page.waitForLoadState('networkidle');
    
    // Submit invalid email
    await page.fill('input[name="email"]', 'nonexistent@example.com');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2000);
    
    // Should still show success message (security best practice)
    // but no actual email is sent
    await expect(page.locator('.message-success')).toBeVisible();
  });
});

test.describe('Account Management', () => {
  
  test('TC901: View account dashboard', async ({ page }) => {
    // Login
    await page.goto('/customer/account/login/');
    await page.fill('#email', 'test@example.com');
    await page.fill('#pass', 'Test123456');
    await page.click('#send2');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    // Go to dashboard
    await page.goto('/customer/account/');
    await page.waitForLoadState('networkidle');
    
    // Verify dashboard sections
    await expect(page.locator('.block-customer-dashboard')).toBeVisible();
    
    // Verify account info
    await expect(page.locator('.box-information')).toBeVisible();
    
    // Verify order history link
    await expect(page.locator('text=Mes commandes')).toBeVisible();
  });

  test('TC902: Edit account information', async ({ page }) => {
    // Login
    await page.goto('/customer/account/login/');
    await page.fill('#email', 'test@example.com');
    await page.fill('#pass', 'Test123456');
    await page.click('#send2');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    // Go to account edit
    await page.goto('/customer/account/edit/');
    await page.waitForLoadState('networkidle');
    
    // Verify form pre-filled
    await expect(page.locator('#firstname')).toHaveValue('Test');
    await expect(page.locator('#lastname')).toHaveValue('User');
    
    // Update lastname
    await page.fill('#lastname', 'UserUpdated');
    await page.click('.action.save.primary');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    // Verify success
    await expect(page.locator('.message-success')).toBeVisible();
  });

  test('TC903: View and edit address book', async ({ page }) => {
    // Login
    await page.goto('/customer/account/login/');
    await page.fill('#email', 'test@example.com');
    await page.fill('#pass', 'Test123456');
    await page.click('#send2');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    // Go to address book
    await page.goto('/customer/address/');
    await page.waitForLoadState('networkidle');
    
    // Verify address book page
    await expect(page.locator('.page-title')).toContainText('adresse');
    
    // Add new address if button exists
    const addBtn = await page.locator('button.add').count();
    if (addBtn > 0) {
      await page.click('button.add');
      await page.waitForLoadState('networkidle');
      
      // Fill address form
      await page.fill('input[name="firstname"]', 'Test');
      await page.fill('input[name="lastname"]', 'User');
      await page.fill('input[name="street[0]"]', '456 Test Street');
      await page.fill('input[name="city"]', 'Oran');
      await page.fill('input[name="postcode"]', '31000');
      await page.fill('input[name="telephone"]', '0555987654');
      await page.selectOption('select[name="country_id"]', 'DZ');
      await page.waitForTimeout(1000);
      await page.selectOption('select[name="region_id"]', '31');
      
      await page.click('.action.save.primary');
      await page.waitForTimeout(2000);
      
      // Verify address saved
      await expect(page.locator('.message-success')).toBeVisible();
    }
  });

  test('TC904: Logout functionality', async ({ page }) => {
    // Login
    await page.goto('/customer/account/login/');
    await page.fill('#email', 'test@example.com');
    await page.fill('#pass', 'Test123456');
    await page.click('#send2');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    // Verify logged in
    await expect(page.locator('.customer-welcome')).toBeVisible();
    
    // Logout
    await page.click('a.action.logout');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    
    // Verify logged out
    await expect(page.locator('.customer-welcome')).not.toBeVisible();
    await expect(page.locator('.authorization-link')).toBeVisible();
  });
});
