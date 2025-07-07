/**
 * Setup View JavaScript
 * Handles form validation, database testing, and setup submission
 */

class SetupWizard {
    constructor() {
        this.form = document.getElementById('setupForm');
        this.mysqlConfig = document.getElementById('mysql_config');
        this.sqliteConfig = document.getElementById('sqlite_config');
        this.testConnectionBtn = document.getElementById('test_connection_btn');
        this.connectionResult = document.getElementById('connection_result');
        this.loadingOverlay = document.getElementById('loading_overlay');
        this.progressBar = document.getElementById('progress_bar');
        this.progressText = document.getElementById('progress_text');

        this.init();
    }

    init() {
        this.bindEvents();
        this.initializePasswordStrength();
    }

    bindEvents() {
        // Database type toggle
        const dbRadios = document.querySelectorAll('input[name="database_type"]');
        dbRadios.forEach(radio => {
            radio.addEventListener('change', (e) => this.toggleDatabaseConfig(e.target.value));
        });

        // Password validation
        const passwordInput = document.getElementById('admin_password');
        passwordInput.addEventListener('input', (e) => this.checkPasswordStrength(e.target.value));

        const passwordConfirm = document.getElementById('admin_password_confirm');
        passwordConfirm.addEventListener('input', (e) => this.validatePasswordConfirmation(e.target.value));

        // Test connection
        this.testConnectionBtn.addEventListener('click', () => this.testConnection());

        // Form submission
        this.form.addEventListener('submit', (e) => this.handleSubmission(e));

        // Real-time validation
        this.bindRealTimeValidation();
    }

    bindRealTimeValidation() {
        const fields = [
            { id: 'admin_name', validator: this.validateAdminName },
            { id: 'admin_userid', validator: this.validateAdminUserId },
            { id: 'admin_password', validator: this.validateAdminPassword }
        ];

        fields.forEach(field => {
            const input = document.getElementById(field.id);
            input.addEventListener('blur', () => {
                const isValid = field.validator.call(this, input.value);
                if (!isValid) {
                    this.showFieldError(field.id, this.getValidationMessage(field.id));
                } else {
                    this.clearFieldError(field.id);
                }
            });
        });
    }

    toggleDatabaseConfig(type) {
        if (type === 'mysql') {
            this.mysqlConfig.classList.remove('hidden');
            this.sqliteConfig.classList.add('hidden');
        } else {
            this.mysqlConfig.classList.add('hidden');
            this.sqliteConfig.classList.remove('hidden');
        }
    }

    initializePasswordStrength() {
        const passwordInput = document.getElementById('admin_password');
        passwordInput.addEventListener('input', (e) => {
            const strength = this.calculatePasswordStrength(e.target.value);
            this.updatePasswordStrengthIndicator(strength);
        });
    }

    calculatePasswordStrength(password) {
        let strength = 0;
        const checks = [
            password.length >= 8,
            /[a-z]/.test(password) && /[A-Z]/.test(password),
            /\d/.test(password),
            /[^a-zA-Z\d]/.test(password)
        ];

        return checks.filter(check => check).length;
    }

    updatePasswordStrengthIndicator(strength) {
        const passwordStrength = document.getElementById('password_strength');
        const bars = passwordStrength.querySelectorAll('.h-1');
        const colors = ['bg-red-500', 'bg-orange-500', 'bg-yellow-500', 'bg-green-500'];
        const labels = ['Weak', 'Fair', 'Good', 'Strong'];
        
        bars.forEach((bar, index) => {
            bar.className = 'h-1 w-full rounded';
            if (index < strength) {
                bar.classList.add(colors[Math.min(strength - 1, 3)]);
            } else {
                bar.classList.add('bg-gray-200');
            }
        });

        const label = passwordStrength.querySelector('p');
        if (strength > 0) {
            label.textContent = `Password strength: ${labels[Math.min(strength - 1, 3)]}`;
            label.className = `text-xs mt-1 ${strength >= 3 ? 'text-green-600' : strength >= 2 ? 'text-yellow-600' : 'text-red-600'}`;
        } else {
            label.textContent = 'Password strength indicator';
            label.className = 'text-xs text-gray-500 mt-1';
        }
    }

    checkPasswordStrength(password) {
        const strength = this.calculatePasswordStrength(password);
        this.updatePasswordStrengthIndicator(strength);
        return strength >= 2; // Require at least "Good" strength
    }

    validatePasswordConfirmation(confirmPassword) {
        const password = document.getElementById('admin_password').value;
        if (confirmPassword && confirmPassword !== password) {
            this.showFieldError('admin_password_confirm', 'Passwords do not match');
            return false;
        } else {
            this.clearFieldError('admin_password_confirm');
            return true;
        }
    }

    validateAdminName(name) {
        return name.trim().length >= 2;
    }

    validateAdminUserId(userid) {
        return /^[a-zA-Z0-9]{3,20}$/.test(userid.trim());
    }

    validateAdminPassword(password) {
        return password.length >= 8 && this.calculatePasswordStrength(password) >= 2;
    }

    getValidationMessage(fieldId) {
        const messages = {
            'admin_name': 'Full name must be at least 2 characters',
            'admin_userid': 'User ID must be 3-20 alphanumeric characters',
            'admin_password': 'Password must be at least 8 characters with good strength'
        };
        return messages[fieldId] || 'Invalid input';
    }

    async testConnection() {
        const formData = new FormData();
        formData.append('database_type', 'mysql');
        
        const fields = ['mysql_hostname', 'mysql_port', 'mysql_database', 'mysql_username', 'mysql_password'];
        fields.forEach(field => {
            formData.append(field, document.getElementById(field).value);
        });

        this.setConnectionTestState(true);

        try {
            const response = await fetch('/setup/test-connection', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            this.showConnectionResult(result.success, result.message);
        } catch (error) {
            this.showConnectionResult(false, 'Connection test failed');
        } finally {
            this.setConnectionTestState(false);
        }
    }

    setConnectionTestState(testing) {
        if (testing) {
            this.testConnectionBtn.disabled = true;
            this.testConnectionBtn.innerHTML = '<span class="animate-spin inline-block w-4 h-4 border-2 border-white border-r-transparent rounded-full mr-2"></span>Testing...';
        } else {
            this.testConnectionBtn.disabled = false;
            this.testConnectionBtn.innerHTML = '<md-icon slot="icon">wifi_protected_setup</md-icon>Test Connection';
        }
    }

    showConnectionResult(success, message) {
        this.connectionResult.className = `mt-2 p-3 rounded-lg text-sm flex items-center ${
            success 
                ? 'bg-green-100 text-green-800 border border-green-200' 
                : 'bg-red-100 text-red-800 border border-red-200'
        }`;
        
        const icon = success ? 'check_circle' : 'error';
        this.connectionResult.innerHTML = `
            <md-icon class="mr-2">${icon}</md-icon>
            ${message}
        `;
        this.connectionResult.classList.remove('hidden');
    }

    async handleSubmission(e) {
        e.preventDefault();
        
        if (!this.validateForm()) {
            return;
        }

        this.showLoadingOverlay();
        this.updateProgress(20, 'Validating configuration...');

        const formData = new FormData(this.form);

        try {
            this.updateProgress(40, 'Setting up database...');
            
            const response = await fetch('/setup/process', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.updateProgress(80, 'Finalizing setup...');
                await this.delay(500);
                this.updateProgress(100, 'Setup complete! Redirecting...');
                await this.delay(1000);
                window.location.href = result.redirect || '/dashboard';
            } else {
                this.hideLoadingOverlay();
                this.showFormErrors(result.errors || { general: [result.message] });
            }
        } catch (error) {
            this.hideLoadingOverlay();
            this.showFormErrors({ general: ['Setup failed. Please try again.'] });
        }
    }

    validateForm() {
        let isValid = true;
        
        // Clear previous errors
        document.querySelectorAll('[id$="_error"]').forEach(el => el.classList.add('hidden'));

        // Validate admin fields
        const adminName = document.getElementById('admin_name').value.trim();
        const adminUserId = document.getElementById('admin_userid').value.trim();
        const adminPassword = document.getElementById('admin_password').value;
        const adminPasswordConfirm = document.getElementById('admin_password_confirm').value;

        if (!this.validateAdminName(adminName)) {
            this.showFieldError('admin_name', this.getValidationMessage('admin_name'));
            isValid = false;
        }

        if (!this.validateAdminUserId(adminUserId)) {
            this.showFieldError('admin_userid', this.getValidationMessage('admin_userid'));
            isValid = false;
        }

        if (!this.validateAdminPassword(adminPassword)) {
            this.showFieldError('admin_password', this.getValidationMessage('admin_password'));
            isValid = false;
        }

        if (!this.validatePasswordConfirmation(adminPasswordConfirm)) {
            isValid = false;
        }

        // Validate MySQL fields if selected
        const databaseType = document.querySelector('input[name="database_type"]:checked').value;
        if (databaseType === 'mysql') {
            const requiredFields = [
                { id: 'mysql_hostname', message: 'Hostname is required' },
                { id: 'mysql_database', message: 'Database name is required' },
                { id: 'mysql_username', message: 'Username is required' }
            ];
            
            requiredFields.forEach(field => {
                if (!document.getElementById(field.id).value.trim()) {
                    this.showFieldError(field.id, field.message);
                    isValid = false;
                }
            });

            const port = document.getElementById('mysql_port').value;
            if (!port || isNaN(port) || port < 1 || port > 65535) {
                this.showFieldError('mysql_port', 'Port must be a valid number between 1 and 65535');
                isValid = false;
            }
        }

        return isValid;
    }

    showFieldError(fieldName, message) {
        const errorElement = document.getElementById(fieldName + '_error');
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.classList.remove('hidden');
        }
    }

    clearFieldError(fieldName) {
        const errorElement = document.getElementById(fieldName + '_error');
        if (errorElement) {
            errorElement.classList.add('hidden');
        }
    }

    showFormErrors(errors) {
        Object.keys(errors).forEach(field => {
            if (field === 'general') {
                this.showNotification('error', errors[field].join('\n'));
            } else {
                this.showFieldError(field, Array.isArray(errors[field]) ? errors[field].join('\n') : errors[field]);
            }
        });
    }

    showNotification(type, message) {
        // Create a temporary notification
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 max-w-sm p-4 rounded-lg shadow-lg ${
            type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' : 'bg-green-100 text-green-800 border border-green-200'
        }`;
        notification.innerHTML = `
            <div class="flex items-start">
                <md-icon class="mr-2 mt-0.5">${type === 'error' ? 'error' : 'check_circle'}</md-icon>
                <div class="flex-1">${message}</div>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-gray-400 hover:text-gray-600">
                    <md-icon>close</md-icon>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }

    showLoadingOverlay() {
        this.loadingOverlay.classList.remove('hidden');
        this.loadingOverlay.classList.add('flex');
    }

    hideLoadingOverlay() {
        this.loadingOverlay.classList.add('hidden');
        this.loadingOverlay.classList.remove('flex');
    }

    updateProgress(percentage, text) {
        this.progressBar.style.width = percentage + '%';
        this.progressText.textContent = text;
    }

    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

// Initialize setup wizard when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    new SetupWizard();
});
