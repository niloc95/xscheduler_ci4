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
            { id: 'admin_email', validator: this.validateAdminEmail },
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
        const bars = passwordStrength.querySelectorAll('.strength-bar');
        const colors = ['bg-red-500', 'bg-orange-500', 'bg-yellow-500', 'bg-green-500'];
        const labels = ['Weak', 'Fair', 'Good', 'Strong'];
        
        bars.forEach((bar, index) => {
            // Reset classes
            bar.className = 'h-1 w-full rounded strength-bar';
            if (index < strength) {
                bar.classList.add(colors[Math.min(strength - 1, 3)]);
            } else {
                bar.classList.add('bg-gray-200');
            }
        });

        const label = document.getElementById('password_strength_text');
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

    validateAdminEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email.trim()) && email.trim().length <= 100;
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
            'admin_email': 'Please enter a valid email address',
            'admin_userid': 'User ID must be 3-20 alphanumeric characters',
            'admin_password': 'Password must be at least 8 characters with good strength'
        };
        return messages[fieldId] || 'Invalid input';
    }

    async testConnection() {
        // Get the CSRF token from the meta tag or window object
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                         window.appConfig?.csrfToken || 
                         document.querySelector('input[name="csrf_test_name"]')?.value;

        const connectionData = {
            db_driver: 'MySQLi',
            db_hostname: document.getElementById('mysql_hostname').value,
            db_port: document.getElementById('mysql_port').value || '3306',
            db_database: document.getElementById('mysql_database').value,
            db_username: document.getElementById('mysql_username').value,
            db_password: document.getElementById('mysql_password').value
        };

        this.setConnectionTestState(true);

        try {
            // Use relative URL for better compatibility and send JSON
            const response = await fetch('setup/test-connection', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken && { 'X-CSRF-TOKEN': csrfToken })
                },
                body: JSON.stringify(connectionData)
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            this.showConnectionResult(result.success, result.message, {
                env_updated: result.env_updated,
                setup_reset: result.setup_reset,
                warning: result.warning
            });
        } catch (error) {
            console.error('Connection test error:', error);
            this.showConnectionResult(false, `Connection test failed: ${error.message}`);
        } finally {
            this.setConnectionTestState(false);
        }
    }

    setConnectionTestState(testing) {
        if (testing) {
            this.testConnectionBtn.disabled = true;
            this.testConnectionBtn.innerHTML = `
                <svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Testing...
            `;
        } else {
            this.testConnectionBtn.disabled = false;
            this.testConnectionBtn.innerHTML = `
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                </svg>
                Test Connection
            `;
        }
    }

    showConnectionResult(success, message, extraInfo = {}) {
        this.connectionResult.className = `mt-2 p-3 rounded-lg text-sm flex items-start ${
            success 
                ? 'bg-green-100 text-green-800 border border-green-200' 
                : 'bg-red-100 text-red-800 border border-red-200'
        }`;
        
        const iconSvg = success 
            ? '<svg class="w-4 h-4 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>'
            : '<svg class="w-4 h-4 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>';
        
        let content = `${iconSvg}<div class="flex-1"><div class="font-medium">${message}</div>`;
        
        // Add extra information for successful connections
        if (success && extraInfo) {
            if (extraInfo.env_updated) {
                content += '<div class="text-xs mt-1 text-green-700">✓ Database credentials saved to configuration file</div>';
            }
            if (extraInfo.setup_reset) {
                content += '<div class="text-xs mt-1 text-green-700">✓ Setup flags reset - you can now re-run the setup process</div>';
            }
            if (extraInfo.warning) {
                content += `<div class="text-xs mt-1 text-orange-700">⚠ Warning: ${extraInfo.warning}</div>`;
            }
        }
        
        content += '</div>';
        
        this.connectionResult.innerHTML = content;
        this.connectionResult.classList.remove('hidden');
        
        // If credentials were updated successfully, show additional success notification
        if (success && extraInfo.env_updated) {
            this.showNotification('success', 'Database credentials have been saved! You can now proceed with setup or run it again if needed.');
        }
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
            
            // Use relative URL for better compatibility
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                              window.appConfig?.csrfToken || 
                              document.querySelector('input[name="csrf_test_name"]')?.value;

            const response = await fetch('setup/process', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    ...(csrfToken && { 'X-CSRF-TOKEN': csrfToken })
                },
                body: formData
            });

            // If server redirected (non-AJAX fallback), follow it in the browser
            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                // Likely HTML due to redirect; navigate to the final URL or login
                const baseUrl = typeof window !== 'undefined' ? String(window.__BASE_URL__ || '').replace(/\/+$/, '') : '';
                window.location.href = response.url || `${baseUrl}/auth/login`;
                return;
            }

            const result = await response.json();

            if (result.success) {
                this.updateProgress(80, 'Finalizing setup...');
                await this.delay(500);
                this.updateProgress(100, 'Setup complete! Redirecting...');
                await this.delay(600);
                const baseUrl = typeof window !== 'undefined' ? String(window.__BASE_URL__ || '').replace(/\/+$/, '') : '';
                window.location.href = result.redirect || `${baseUrl}/auth/login`;
            } else {
                this.hideLoadingOverlay();
                this.showFormErrors(result.errors || { general: [result.message] });
            }
        } catch (error) {
            this.hideLoadingOverlay();
            // If the request ended up as a non-JSON redirect, just go to login
            if (error?.name === 'SyntaxError') {
                const baseUrl = typeof window !== 'undefined' ? String(window.__BASE_URL__ || '').replace(/\/+$/, '') : '';
                window.location.href = `${baseUrl}/auth/login`;
                return;
            }
            this.showFormErrors({ general: ['Setup failed. Please try again.'] });
        }
    }

    validateForm() {
        let isValid = true;
        
        // Clear previous errors
        document.querySelectorAll('[id$="_error"]').forEach(el => el.classList.add('hidden'));

        // Validate admin fields
        const adminName = document.getElementById('admin_name').value.trim();
        const adminEmail = document.getElementById('admin_email').value.trim();
        const adminUserId = document.getElementById('admin_userid').value.trim();
        const adminPassword = document.getElementById('admin_password').value;
        const adminPasswordConfirm = document.getElementById('admin_password_confirm').value;

        if (!this.validateAdminName(adminName)) {
            this.showFieldError('admin_name', this.getValidationMessage('admin_name'));
            isValid = false;
        }

        if (!this.validateAdminEmail(adminEmail)) {
            this.showFieldError('admin_email', this.getValidationMessage('admin_email'));
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
        // Remove any existing notifications
        document.querySelectorAll('.notification').forEach(n => n.remove());
        
        // Create a new notification
        const notification = document.createElement('div');
        notification.className = `notification fixed top-4 right-4 z-50 max-w-sm p-4 rounded-lg shadow-lg ${
            type === 'error' 
                ? 'bg-red-100 text-red-800 border border-red-200' 
                : 'bg-green-100 text-green-800 border border-green-200'
        }`;
        
        const iconSvg = type === 'error' 
            ? '<svg class="w-5 h-5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>'
            : '<svg class="w-5 h-5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>';
        
        notification.innerHTML = `
            <div class="flex items-start">
                ${iconSvg}
                <div class="flex-1 text-sm">${message}</div>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-gray-400 hover:text-gray-600 flex-shrink-0">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                    </svg>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove after 7 seconds with fade-out animation
        setTimeout(() => {
            if (notification.parentElement) {
                notification.classList.add('fade-out');
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.remove();
                    }
                }, 300);
            }
        }, 7000);
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
        this.progressBar.value = Math.max(0, Math.min(100, percentage));
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
