/**
 * AgriConnect — Smart Contract Farming & Agritech Management Portal
 * Module: validation.js (Modular ES6 Client-Side Validation Engine)
 *
 * Provides real-time constraint validation, regex compliance, password strength
 * scoring, and accessible inline error rendering across all registration and input forms.
 */

class FormValidator {
    /**
     * @param {string|HTMLFormElement} formSelector Form element or query selector
     */
    constructor(formSelector) {
        this.form = typeof formSelector === 'string' ? document.querySelector(formSelector) : formSelector;
        if (!this.form) return;

        this.rules = {
            name: {
                regex: /^[A-Za-z\s.'-]{3,60}$/,
                error: 'Full name must contain at least 3 alphabetic characters.'
            },
            email: {
                regex: /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/,
                error: 'Please enter a valid email address (e.g., name@domain.com).'
            },
            phone: {
                regex: /^[6-9]\d{9}$/,
                error: 'Enter a valid 10-digit Indian mobile number (e.g., 9876543210).'
            },
            password: {
                minLength: 8,
                hasNumber: /\d/,
                hasSpecial: /[!@#$%^&*(),.?":{}|<>_\-]/,
                error: 'Password must be ≥ 8 characters with at least 1 digit and 1 special symbol.'
            },
            acreage: {
                min: 0.1,
                error: 'Land area must be greater than 0.1 acres.'
            },
            quantity: {
                min: 1,
                error: 'Quantity must be a positive integer (≥ 1 Quintal).'
            },
            price: {
                min: 100,
                error: 'Price per quintal must be at least ₹100.00.'
            }
        };

        this.init();
    }

    init() {
        // Attach blur and input listeners for all tracked fields
        const inputs = this.form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            // Live validation as user types (debounce / input)
            input.addEventListener('input', () => this.validateField(input));
            // Strict check when user unfocuses
            input.addEventListener('blur', () => this.validateField(input, true));
        });

        // Intercept Form Submission
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));

        // Password Strength Meter listener
        const passwordInput = this.form.querySelector('input[type="password"][name="password"], #password');
        if (passwordInput) {
            passwordInput.addEventListener('input', (e) => this.updatePasswordStrength(e.target.value));
        }

        // Confirm Password Match listener
        const confirmInput = this.form.querySelector('#confirm_password, input[name="confirm_password"]');
        if (confirmInput && passwordInput) {
            confirmInput.addEventListener('input', () => this.validatePasswordMatch(passwordInput, confirmInput));
        }
    }

    /**
     * Checks if a field should be validated (skips fields in inactive role sections or disabled fields)
     * @param {HTMLElement} field
     * @returns {boolean}
     */
    shouldValidateField(field) {
        if (!field || field.disabled) return false;

        // Skip fields inside explicitly hidden role-specific sections
        const hiddenRoleParent = field.closest('#buyerFields, #farmerFields, #buyerSection, #farmerSection');
        if (hiddenRoleParent && (hiddenRoleParent.style.display === 'none' || window.getComputedStyle(hiddenRoleParent).display === 'none')) {
            return false;
        }

        return true;
    }

    /**
     * Validates an individual form control
     * @param {HTMLElement} field
     * @param {boolean} isBlur
     * @returns {boolean}
     */
    validateField(field, isBlur = false) {
        if (!this.shouldValidateField(field)) {
            this.clearError(field);
            return true;
        }

        const val = field.value.trim();
        const fieldName = field.getAttribute('name') || field.id;
        const isRequired = field.hasAttribute('required') || field.dataset.required === 'true';

        // Check if empty on required field
        if (isRequired && val === '') {
            if (isBlur) {
                this.showError(field, 'This field is required.');
            }
            return false;
        }

        if (!isRequired && val === '') {
            this.clearError(field);
            return true;
        }

        // Validation by Type or Name
        if (fieldName.includes('name') && !fieldName.includes('crop') && !fieldName.includes('user')) {
            if (!this.rules.name.regex.test(val)) {
                this.showError(field, this.rules.name.error);
                return false;
            }
        } else if (fieldName.includes('email') || field.type === 'email') {
            if (!this.rules.email.regex.test(val)) {
                this.showError(field, this.rules.email.error);
                return false;
            }
        } else if (fieldName.includes('phone') || field.type === 'tel') {
            if (!this.rules.phone.regex.test(val)) {
                this.showError(field, this.rules.phone.error);
                return false;
            }
        } else if (fieldName === 'password' || (field.type === 'password' && !fieldName.includes('confirm'))) {
            const pwd = field.value;
            if (
                pwd.length < this.rules.password.minLength ||
                !this.rules.password.hasNumber.test(pwd) ||
                !this.rules.password.hasSpecial.test(pwd)
            ) {
                this.showError(field, this.rules.password.error);
                return false;
            }
        } else if (fieldName.includes('acre') || fieldName.includes('land')) {
            const num = parseFloat(val);
            if (isNaN(num) || num < this.rules.acreage.min) {
                this.showError(field, this.rules.acreage.error);
                return false;
            }
        } else if (fieldName.includes('quantity') || fieldName.includes('quintal')) {
            const num = parseInt(val, 10);
            if (isNaN(num) || num < this.rules.quantity.min) {
                this.showError(field, this.rules.quantity.error);
                return false;
            }
        } else if (fieldName.includes('price')) {
            const num = parseFloat(val);
            if (isNaN(num) || num < this.rules.price.min) {
                this.showError(field, this.rules.price.error);
                return false;
            }
        }

        // Field passed validation
        this.markValid(field);
        return true;
    }

    /**
     * Checks if confirm password matches password
     */
    validatePasswordMatch(pwdField, confirmField) {
        if (confirmField.value !== pwdField.value) {
            this.showError(confirmField, 'Passwords do not match.');
            return false;
        } else {
            this.markValid(confirmField);
            return true;
        }
    }

    /**
     * Calculates password entropy and updates UI meter
     * @param {string} password
     */
    updatePasswordStrength(password) {
        const meterFill = this.form.querySelector('#password-meter-fill');
        const meterText = this.form.querySelector('#password-meter-text');
        if (!meterFill) return;

        let score = 0;
        if (password.length >= 8) score++;
        if (password.length >= 12) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;

        meterFill.className = 'password-meter-fill';
        if (score <= 2) {
            meterFill.classList.add('weak');
            if (meterText) meterText.textContent = 'Weak: Add numbers, symbols, and length.';
        } else if (score <= 4) {
            meterFill.classList.add('medium');
            if (meterText) meterText.textContent = 'Moderate: Good, but can be stronger.';
        } else {
            meterFill.classList.add('strong');
            if (meterText) meterText.textContent = 'Strong: Excellent cryptographic resistance!';
        }
    }

    /**
     * Renders inline error message and visual cues
     * @param {HTMLElement} field
     * @param {string} message
     */
    showError(field, message) {
        field.classList.remove('is-valid');
        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');

        let errorSpan = this.getOrCreateErrorElement(field);
        errorSpan.innerHTML = `⚠️ ${message}`;
        errorSpan.classList.add('visible');
    }

    /**
     * Clears error indicator and marks field valid
     * @param {HTMLElement} field
     */
    markValid(field) {
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
        field.setAttribute('aria-invalid', 'false');

        let errorSpan = this.getOrCreateErrorElement(field);
        errorSpan.textContent = '';
        errorSpan.classList.remove('visible');
    }

    clearError(field) {
        field.classList.remove('is-invalid');
        field.classList.remove('is-valid');
        let errorSpan = this.getOrCreateErrorElement(field);
        errorSpan.textContent = '';
        errorSpan.classList.remove('visible');
    }

    getOrCreateErrorElement(field) {
        const errorId = `error-${field.id || field.name}`;
        let el = document.getElementById(errorId);
        if (!el) {
            el = document.createElement('span');
            el.id = errorId;
            el.className = 'error-msg';
            if (field.parentNode) {
                field.parentNode.appendChild(el);
            }
        }
        return el;
    }

    /**
     * Handles Form Submission Event
     * @param {Event} e
     */
    handleSubmit(e) {
        let isValid = true;
        let firstInvalidField = null;

        const inputs = this.form.querySelectorAll('input:not([type="hidden"]), select, textarea');
        inputs.forEach(input => {
            const fieldValid = this.validateField(input, true);
            if (!fieldValid && isValid) {
                isValid = false;
                firstInvalidField = input;
            }
        });

        // Special password match confirmation check
        const pwd = this.form.querySelector('#password, input[name="password"]');
        const confirm = this.form.querySelector('#confirm_password, input[name="confirm_password"]');
        if (pwd && confirm && confirm.value !== pwd.value) {
            this.showError(confirm, 'Passwords do not match.');
            isValid = false;
            if (!firstInvalidField) firstInvalidField = confirm;
        }

        if (!isValid) {
            e.preventDefault();
            if (firstInvalidField) {
                const parentPanel = firstInvalidField.closest('.step-panel');
                if (parentPanel && !parentPanel.classList.contains('active')) {
                    document.querySelectorAll('.step-panel').forEach(p => p.classList.remove('active'));
                    parentPanel.classList.add('active');
                    const step1Ind = document.getElementById('stepIndicator1');
                    const step2Ind = document.getElementById('stepIndicator2');
                    if (parentPanel.id === 'step1Panel' && step1Ind && step2Ind) {
                        step1Ind.classList.add('active');
                        step2Ind.classList.remove('active');
                    }
                }
                firstInvalidField.focus();
                firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    }
}

// Auto-initialize on any form marked with data-validate="true" or common IDs
document.addEventListener('DOMContentLoaded', () => {
    const formsToValidate = document.querySelectorAll('form[data-validate="true"], #registrationForm, #bidForm, #tenderForm, #loginForm');
    formsToValidate.forEach(form => new FormValidator(form));
});
