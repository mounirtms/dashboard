/**
 * Shared validation utilities
 */

export const PASSWORD_REGEX = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*(),.?":{}|<>\+\-=\[\]/\\~`;]).{8,}$/;

export const PASSWORD_REQUIREMENTS = 'Min 8 chars, uppercase, lowercase, number, special char';

/**
 * Validate a password against the strength requirements.
 * Returns an error message or empty string if valid.
 */
export function validatePassword(password: string): string {
  if (!PASSWORD_REGEX.test(password)) {
    return PASSWORD_REQUIREMENTS;
  }
  return '';
}

/**
 * Check if two passwords match.
 * Returns an error message or empty string if they match.
 */
export function validatePasswordMatch(password: string, confirmPassword: string): string {
  if (password !== confirmPassword) {
    return 'Passwords do not match';
  }
  return '';
}
