/**
 * Alpine component shared by the sign-up and reset-password forms.
 *
 * Usage: x-data="authForm({ name: '', email: '', password: '', password_confirmation: '' }, serverErrors)"
 * Only the fields passed in are validated. A field is checked when it loses focus, then on every keystroke.
 */
document.addEventListener('alpine:init', () => {
    const rules = {
        name(value) {
            if (!value) return 'Please enter your full name.';
            if (value.length < 2) return 'The full name must be at least 2 characters.';
            if (!/^[\p{L}\p{M}][\p{L}\p{M}\s.'-]*$/u.test(value)) return 'The name may only contain letters, spaces, dots, apostrophes and hyphens.';
            return '';
        },
        email(value) {
            if (!value) return 'Please enter your email.';
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return 'Please enter a valid email address.';
            return '';
        },
        current_password(value, fields) {
            return fields.current_password ? '' : 'Please enter your current password.';
        },
        password(value, fields) {
            if (!fields.password) return 'Please choose a password.';
            if (fields.password.length < 8) return 'The password must be at least 8 characters.';
            return '';
        },
        password_confirmation(value, fields) {
            if (!fields.password_confirmation) return 'Please confirm your password.';
            if (fields.password_confirmation !== fields.password) return 'The passwords do not match.';
            return '';
        },
    };

    Alpine.data('authForm', (initialFields, serverErrors = {}) => ({
        fields: { ...initialFields },
        errors: { ...Object.fromEntries(Object.keys(initialFields).map((field) => [field, ''])), ...serverErrors },
        touched: {},
        showPassword: false,
        submitting: false,

        init() {
            // Fields that came back with a server error count as touched, so they re-check as you type.
            Object.keys(serverErrors).forEach((field) => this.touched[field] = true);

            Object.keys(this.fields).forEach((field) => {
                this.$watch(`fields.${field}`, () => {
                    if (this.touched[field]) this.validate(field);
                    if (field === 'password' && this.touched.password_confirmation) this.validate('password_confirmation');
                });
            });
        },

        touch(field) {
            this.touched[field] = true;
            this.validate(field);
        },

        validate(field) {
            this.errors[field] = rules[field] ? rules[field](String(this.fields[field]).trim(), this.fields) : '';
            return this.errors[field] === '';
        },

        stateClass(field) {
            if (!this.touched[field]) return '';
            return this.errors[field] ? 'is-invalid' : 'is-valid';
        },

        get strength() {
            const password = this.fields.password ?? '';
            let score = 0;
            if (password.length >= 8) score++;
            if (password.length >= 12) score++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
            if (/\d/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;

            if (password.length < 8 || score <= 2) return { label: 'Weak', percent: 33, barClass: 'bg-danger', textClass: 'text-danger' };
            if (score <= 3) return { label: 'Medium', percent: 66, barClass: 'bg-warning', textClass: 'text-warning-emphasis' };
            return { label: 'Strong', percent: 100, barClass: 'bg-success', textClass: 'text-success' };
        },

        submit(event) {
            const fields = Object.keys(this.fields);
            fields.forEach((field) => this.touched[field] = true);
            const valid = fields.map((field) => this.validate(field)).every(Boolean);

            if (!valid) {
                event.preventDefault();
                document.getElementById(fields.find((field) => this.errors[field]))?.focus();
                return;
            }

            this.submitting = true;
        },
    }));
});
