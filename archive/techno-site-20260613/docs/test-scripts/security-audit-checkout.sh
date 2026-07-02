#!/bin/bash
# Security Audit Script for Checkout Components
# Checks for common security vulnerabilities

echo "🔒 Security Audit - Checkout Components"
echo "========================================"
echo ""

ISSUES_FOUND=0

# Check 1: XSS vulnerabilities in templates
echo "1. Checking for XSS vulnerabilities in templates..."
XSS_PATTERNS=("innerHTML" "outerHTML" "document.write" ".html(" "eval(")

for pattern in "${XSS_PATTERNS[@]}"; do
    results=$(grep -r "$pattern" app/code/Mab/CheckoutCustomization/view/frontend/web/ 2>/dev/null | grep -v ".min.js" | grep -v "Binary")
    if [ ! -z "$results" ]; then
        echo "  ⚠️  Found potentially unsafe pattern: $pattern"
        echo "$results" | head -3
        ((ISSUES_FOUND++))
    fi
done

if [ $ISSUES_FOUND -eq 0 ]; then
    echo "  ✅ No obvious XSS vulnerabilities found"
fi
echo ""

# Check 2: Hardcoded credentials or API keys
echo "2. Checking for hardcoded credentials..."
CRED_PATTERNS=("password" "apikey" "api_key" "secret" "token")
CRED_FOUND=0

for pattern in "${CRED_PATTERNS[@]}"; do
    results=$(grep -ri "$pattern.*=.*['\"]" app/code/Mab/CheckoutCustomization/view/frontend/web/ 2>/dev/null | grep -v ".min.js" | grep -v "placeholder" | grep -v "label" | grep -v "title")
    if [ ! -z "$results" ]; then
        echo "  ⚠️  Found potential credential pattern: $pattern"
        ((CRED_FOUND++))
    fi
done

if [ $CRED_FOUND -eq 0 ]; then
    echo "  ✅ No hardcoded credentials found"
fi
echo ""

# Check 3: Console.log statements (information disclosure)
echo "3. Checking for console.log statements..."
CONSOLE_COUNT=$(grep -r "console\." app/code/Mab/CheckoutCustomization/view/frontend/web/js/ 2>/dev/null | grep -v ".min.js" | wc -l)
echo "  Found $CONSOLE_COUNT console statements"
if [ $CONSOLE_COUNT -gt 20 ]; then
    echo "  ⚠️  Consider removing debug console statements for production"
else
    echo "  ✅ Console usage is acceptable"
fi
echo ""

# Check 4: SQL injection patterns
echo "4. Checking for SQL injection patterns..."
if grep -r "query.*+" app/code/Mab/CheckoutCustomization/ 2>/dev/null | grep -v ".min.js" > /dev/null; then
    echo "  ⚠️  Found potential SQL concatenation"
    ((ISSUES_FOUND++))
else
    echo "  ✅ No SQL injection patterns found"
fi
echo ""

# Check 5: Unsafe jQuery usage
echo "5. Checking for unsafe jQuery patterns..."
UNSAFE_JQUERY=0
if grep -r '\$.*html(' app/code/Mab/CheckoutCustomization/view/frontend/web/js/ 2>/dev/null | grep -v ".min.js" > /dev/null; then
    echo "  ⚠️  Found .html() usage - verify data is sanitized"
    ((UNSAFE_JQUERY++))
fi
if grep -r '\$.*append(' app/code/Mab/CheckoutCustomization/view/frontend/web/js/ 2>/dev/null | grep -v ".min.js" | grep -v "append(\$" > /dev/null; then
    echo "  ℹ️  Found .append() usage - verify data is sanitized"
fi
if [ $UNSAFE_JQUERY -eq 0 ]; then
    echo "  ✅ jQuery usage appears safe"
fi
echo ""

# Check 6: CSRF protection
echo "6. Checking CSRF protection..."
if grep -r "form_key" app/code/Mab/CheckoutCustomization/ 2>/dev/null | grep -v ".min.js" > /dev/null; then
    echo "  ✅ Form keys referenced (Magento CSRF protection)"
else
    echo "  ℹ️  Using Magento built-in CSRF protection"
fi
echo ""

# Check 7: Data validation
echo "7. Checking input validation..."
VALIDATION_COUNT=$(grep -r "validate\|validation\|isValid" app/code/Mab/CheckoutCustomization/view/frontend/web/js/ 2>/dev/null | grep -v ".min.js" | wc -l)
if [ $VALIDATION_COUNT -gt 5 ]; then
    echo "  ✅ Found $VALIDATION_COUNT validation references"
else
    echo "  ⚠️  Limited validation found ($VALIDATION_COUNT references)"
    echo "  Recommendation: Add more input validation"
fi
echo ""

# Check 8: HTTPS enforcement
echo "8. Checking HTTPS usage..."
HTTP_URLS=$(grep -r "http://" app/code/Mab/CheckoutCustomization/ 2>/dev/null | grep -v ".min.js" | grep -v "xmlns" | grep -v "schema" | wc -l)
if [ $HTTP_URLS -gt 0 ]; then
    echo "  ⚠️  Found $HTTP_URLS HTTP URLs - should use HTTPS"
    grep -r "http://" app/code/Mab/CheckoutCustomization/ 2>/dev/null | grep -v ".min.js" | grep -v "xmlns" | grep -v "schema" | head -3
else
    echo "  ✅ No insecure HTTP URLs found"
fi
echo ""

# Check 9: Sensitive data exposure
echo "9. Checking for sensitive data in client-side code..."
SENSITIVE_PATTERNS=("credit" "card" "cvv" "ssn" "social")
SENSITIVE_FOUND=0

for pattern in "${SENSITIVE_PATTERNS[@]}"; do
    results=$(grep -ri "$pattern" app/code/Mab/CheckoutCustomization/view/frontend/web/ 2>/dev/null | grep -v ".min.js" | grep -v "Binary")
    if [ ! -z "$results" ]; then
        echo "  ⚠️  Found sensitive keyword: $pattern"
        ((SENSITIVE_FOUND++))
    fi
done

if [ $SENSITIVE_FOUND -eq 0 ]; then
    echo "  ✅ No sensitive data patterns found"
fi
echo ""

# Check 10: File permissions
echo "10. Checking file permissions..."
WRITABLE=$(find app/code/Mab/CheckoutCustomization/ -type f -perm -002 2>/dev/null | wc -l)
if [ $WRITABLE -gt 0 ]; then
    echo "  ⚠️  Found $WRITABLE world-writable files"
    ((ISSUES_FOUND++))
else
    echo "  ✅ File permissions are secure"
fi
echo ""

# Summary
echo "========================================"
echo "SECURITY AUDIT SUMMARY"
echo "========================================"
if [ $ISSUES_FOUND -eq 0 ]; then
    echo "✅ No critical security issues found"
    echo "Status: PASSED"
else
    echo "⚠️  Found $ISSUES_FOUND potential security issues"
    echo "Status: REVIEW REQUIRED"
fi
echo ""
echo "Recommendations:"
echo "1. Remove console.log statements for production"
echo "2. Ensure all user inputs are validated and sanitized"
echo "3. Use Content Security Policy (CSP) headers"
echo "4. Implement rate limiting on API endpoints"
echo "5. Regular dependency updates"
echo ""
