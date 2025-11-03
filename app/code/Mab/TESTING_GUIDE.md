# MAB Modules Testing Guide

This guide provides comprehensive instructions for testing MAB Magento 2 modules to ensure they are production-ready.

## 🧪 Testing Overview

The MAB modules include both unit tests and integration tests to ensure code quality, functionality, and compatibility.

## 📁 Test Directory Structure

```
app/code/Mab/
├── Core/
│   └── Test/
│       ├── Unit/
│       │   ├── Helper/
│       │   │   └── DataTest.php
│       │   └── ...
│       └── Integration/
│           └── ModuleConfigTest.php
├── SocialLogin/
│   └── Test/
│       └── Unit/
│           └── Helper/
│               └── DataTest.php
└── phpunit.xml
```

## 🚀 Running Tests

### Prerequisites

1. Ensure you're in the Magento root directory
2. Make sure all MAB modules are installed and registered
3. Ensure development dependencies are installed

### Running Unit Tests

```bash
# Run all MAB unit tests
./vendor/bin/phpunit -c app/code/Mab/phpunit.xml --testsuite "All MAB Module Tests"

# Run specific module tests
./vendor/bin/phpunit -c app/code/Mab/phpunit.xml --testsuite "MAB Core Tests"
./vendor/bin/phpunit -c app/code/Mab/phpunit.xml --testsuite "MAB SocialLogin Tests"

# Run individual test files
./vendor/bin/phpunit app/code/Mab/Core/Test/Unit/Helper/DataTest.php
```

### Running Integration Tests

```bash
# Run integration tests
cd dev/tests/integration
../../../vendor/bin/phpunit ../../../app/code/Mab/Core/Test/Integration/ModuleConfigTest.php
```

## 🔍 Automated Syntax and Configuration Testing

### Shell Script Testing

Run the provided syntax checking script:

```bash
# Make script executable
chmod +x test-mab-syntax.sh

# Run syntax checks
./test-mab-syntax.sh
```

### PHP Script Testing

Run the comprehensive PHP testing script:

```bash
php test-mab-modules.php
```

## 🧱 Unit Testing Guidelines

### What to Test

1. **Helper Classes** - Business logic methods
2. **Model Classes** - Data handling and processing
3. **Block Classes** - Template data preparation
4. **Controller Classes** - Request handling (with mocks)
5. **Plugin Classes** - Method interception logic

### Test Structure

```php
<?php
namespace Mab\ModuleName\Test\Unit\Helper;

use PHPUnit\Framework\TestCase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

class ExampleTest extends TestCase
{
    protected function setUp(): void
    {
        // Set up test environment
    }

    public function testMethodName()
    {
        // Arrange - Set up test data and mocks
        
        // Act - Call the method being tested
        
        // Assert - Verify the results
        $this->assertEquals(expected, actual);
    }
}
```

## 🔌 Integration Testing Guidelines

### What to Test

1. **Module Registration** - Ensure modules are properly registered
2. **Configuration Values** - Verify system configuration is accessible
3. **Dependency Injection** - Confirm objects can be instantiated
4. **Database Operations** - Test data persistence (with transactions)
5. **API Endpoints** - Validate REST/SOAP API functionality

### Test Structure

```php
<?php
namespace Mab\ModuleName\Test\Integration;

use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class ExampleIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        // Set up integration test environment
    }

    public function testConfigurationValue()
    {
        $objectManager = Bootstrap::getObjectManager();
        $config = $objectManager->get(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        
        $value = $config->getValue('section/group/field');
        $this->assertNotEmpty($value);
    }
}
```

## 🎯 Quality Assurance Checklist

Before deploying to production, ensure all tests pass and verify:

### ✅ Syntax and Structure
- [ ] All PHP files pass syntax check
- [ ] All XML configuration files are valid
- [ ] Module registration files are correct
- [ ] No empty configuration files

### ✅ Unit Tests
- [ ] All unit tests pass with 100% success rate
- [ ] Code coverage is acceptable (>80% for critical components)
- [ ] No skipped or incomplete tests
- [ ] Mock objects are properly configured

### ✅ Integration Tests
- [ ] Module registration is verified
- [ ] Configuration values are accessible
- [ ] Dependencies are satisfied
- [ ] Database operations work correctly

### ✅ Performance Tests
- [ ] Memory usage is within acceptable limits
- [ ] Execution time is reasonable
- [ ] Caching is working effectively
- [ ] No memory leaks detected

### ✅ Security Tests
- [ ] Input validation is working
- [ ] SQL injection protection is in place
- [ ] XSS prevention is effective
- [ ] Access controls are properly configured

## 🛠️ Troubleshooting

### Common Issues

1. **Class Not Found Errors**
   ```bash
   # Clear generated code
   rm -rf generated/code/*
   
   # Re-run tests
   ./vendor/bin/phpunit app/code/Mab/Core/Test/Unit/Helper/DataTest.php
   ```

2. **Configuration Cache Issues**
   ```bash
   # Clear cache
   php bin/magento cache:clean
   
   # Run tests
   ./vendor/bin/phpunit -c app/code/Mab/phpunit.xml
   ```

3. **Database Connection Errors (Integration Tests)**
   ```bash
   # Check test database configuration
   cat dev/tests/integration/etc/install-config-mysql.php
   ```

### Debugging Test Failures

1. Run tests with verbose output:
   ```bash
   ./vendor/bin/phpunit -v --debug app/code/Mab/Core/Test/Unit/Helper/DataTest.php
   ```

2. Use test filtering to isolate specific tests:
   ```bash
   ./vendor/bin/phpunit --filter testIsEnabled app/code/Mab/SocialLogin/Test/Unit/Helper/DataTest.php
   ```

## 📊 Test Reports

Generate detailed test reports:

```bash
# Generate code coverage report
./vendor/bin/phpunit -c app/code/Mab/phpunit.xml --coverage-html var/test-reports/

# Generate JUnit XML report
./vendor/bin/phpunit -c app/code/Mab/phpunit.xml --log-junit var/test-reports/junit.xml
```

## 🔄 Continuous Integration

Example CI configuration for GitHub Actions:

```yaml
name: MAB Modules Testing
on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'
      - name: Install dependencies
        run: composer install
      - name: Run unit tests
        run: ./vendor/bin/phpunit -c app/code/Mab/phpunit.xml
```

## 📞 Support

For testing assistance:
- Review existing tests for patterns and best practices
- Consult Magento DevDocs for testing guidelines
- Contact the development team for complex scenarios

---

*This testing guide ensures MAB modules meet professional quality standards before production deployment.*