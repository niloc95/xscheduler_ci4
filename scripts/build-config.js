#!/usr/bin/env node

/**
 * Build Configuration Script for xScheduler
 * 
 * This script handles environment-specific builds and configurations
 * for the xScheduler application.
 */

import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const CONFIG = {
    environments: {
        development: {
            baseURL: 'http://localhost:8081/',
            forceHTTPS: false,
            CSPEnabled: false,
            logLevel: 9,
            buildMode: 'development'
        },
        staging: {
            baseURL: 'https://staging.yourdomain.com/',
            forceHTTPS: true,
            CSPEnabled: true,
            logLevel: 4,
            buildMode: 'production'
        },
        production: {
            baseURL: '',
            forceHTTPS: true,
            CSPEnabled: true,
            logLevel: 4,
            buildMode: 'production'
        }
    },
    
    paths: {
        env: '.env',
        envExample: '.env.example',
        buildOutput: 'public/build',
        deployOutput: 'xscheduler-deploy'
    }
};

class BuildManager {
    constructor() {
        this.currentEnv = process.env.NODE_ENV || 'development';
        this.targetEnv = this.currentEnv;
    }

    /**
     * Main build process
     */
    async build(environment = null) {
        try {
            this.targetEnv = environment || this.currentEnv;
            
            console.log(`🚀 Building xScheduler for ${this.targetEnv} environment...`);
            
            await this.validateEnvironment();
            await this.configureEnvironment();
            await this.buildAssets();
            await this.optimizeForEnvironment();
            await this.validateBuild();
            
            console.log(`✅ Build completed successfully for ${this.targetEnv} environment!`);
            
        } catch (error) {
            console.error(`❌ Build failed: ${error.message}`);
            process.exit(1);
        }
    }

    /**
     * Validate environment configuration
     */
    async validateEnvironment() {
        const config = CONFIG.environments[this.targetEnv];
        
        if (!config) {
            throw new Error(`Unknown environment: ${this.targetEnv}`);
        }

        // Check if .env file exists
        if (!fs.existsSync(CONFIG.paths.env)) {
            console.log('⚠️  .env file not found, creating from template...');
            await this.createEnvFromTemplate();
        }

        // Check Node.js dependencies
        if (!fs.existsSync('node_modules')) {
            console.log('📦 Installing Node.js dependencies...');
            execSync('npm install', { stdio: 'inherit' });
        }

        // Check PHP dependencies
        if (!fs.existsSync('vendor')) {
            console.log('📦 Installing PHP dependencies...');
            execSync('composer install --no-dev --optimize-autoloader', { stdio: 'inherit' });
        }

        console.log(`✅ Environment ${this.targetEnv} validated`);
    }

    /**
     * Configure environment variables
     */
    async configureEnvironment() {
        const config = CONFIG.environments[this.targetEnv];
        const envContent = fs.readFileSync(CONFIG.paths.env, 'utf8');
        
        let updatedContent = envContent;

        // Update environment-specific settings
        updatedContent = this.updateEnvVariable(updatedContent, 'CI_ENVIRONMENT', this.targetEnv);
        updatedContent = this.updateEnvVariable(updatedContent, 'app.baseURL', config.baseURL);
        updatedContent = this.updateEnvVariable(updatedContent, 'app.forceGlobalSecureRequests', config.forceHTTPS);
        updatedContent = this.updateEnvVariable(updatedContent, 'app.CSPEnabled', config.CSPEnabled);
        updatedContent = this.updateEnvVariable(updatedContent, 'logger.threshold', config.logLevel);

        // Generate encryption key if not exists
        if (!updatedContent.includes('encryption.key = hex2bin:')) {
            console.log('🔐 Generating encryption key...');
            try {
                execSync('php spark key:generate --force', { stdio: 'inherit' });
                console.log('✅ Encryption key generated');
            } catch (error) {
                console.log('⚠️  Could not generate encryption key automatically');
            }
        }

        // Write updated .env file
        fs.writeFileSync(CONFIG.paths.env, updatedContent);
        console.log(`✅ Environment configured for ${this.targetEnv}`);
    }

    /**
     * Build frontend assets
     */
    async buildAssets() {
        const config = CONFIG.environments[this.targetEnv];
        
        console.log('🎨 Building frontend assets...');
        
        // Set NODE_ENV for the build process
        process.env.NODE_ENV = config.buildMode;
        
        // Build assets with Vite
        if (config.buildMode === 'production') {
            execSync('npm run build', { stdio: 'inherit' });
        } else {
            execSync('npm run dev', { stdio: 'inherit' });
        }
        
        console.log('✅ Frontend assets built successfully');
    }

    /**
     * Optimize for specific environment
     */
    async optimizeForEnvironment() {
        const config = CONFIG.environments[this.targetEnv];
        
        if (config.buildMode === 'production') {
            console.log('⚡ Optimizing for production...');
            
            // Clear cache
            this.clearCache();
            
            // Optimize Composer autoloader
            execSync('composer dump-autoload --optimize --no-dev', { stdio: 'inherit' });
            
            console.log('✅ Production optimizations applied');
        }
    }

    /**
     * Validate build output
     */
    async validateBuild() {
        const requiredFiles = [
            'public/build/assets/style.css',
            'public/build/assets/main.js',
            'public/index.php',
            'app/Config/App.php'
        ];

        for (const file of requiredFiles) {
            if (!fs.existsSync(file)) {
                throw new Error(`Required file missing: ${file}`);
            }
        }

        console.log('✅ Build validation passed');
    }

    /**
     * Create .env file from template
     */
    async createEnvFromTemplate() {
        if (fs.existsSync(CONFIG.paths.envExample)) {
            fs.copyFileSync(CONFIG.paths.envExample, CONFIG.paths.env);
            console.log('✅ .env file created from template');
        } else {
            throw new Error('.env.example template not found');
        }
    }

    /**
     * Update environment variable in content
     */
    updateEnvVariable(content, key, value) {
        const regex = new RegExp(`^${key}\\s*=.*$`, 'm');
        const newLine = `${key} = ${value}`;
        
        if (regex.test(content)) {
            return content.replace(regex, newLine);
        } else {
            return content + `\n${newLine}`;
        }
    }

    /**
     * Clear application cache
     */
    clearCache() {
        const cacheDir = 'writable/cache';
        if (fs.existsSync(cacheDir)) {
            execSync(`rm -rf ${cacheDir}/*`, { stdio: 'inherit' });
            console.log('✅ Cache cleared');
        }
    }

    /**
     * Create deployment package
     */
    async createDeploymentPackage() {
        console.log('📦 Creating deployment package...');
        
        // Build for production first
        await this.build('production');
        
        // Run the existing package script
        execSync('node scripts/package.js', { stdio: 'inherit' });
        
        console.log('✅ Deployment package created');
    }
}

// CLI Interface
const args = process.argv.slice(2);
const command = args[0];
const environment = args[1];

const buildManager = new BuildManager();

switch (command) {
    case 'build':
        buildManager.build(environment);
        break;
    case 'deploy':
        buildManager.createDeploymentPackage();
        break;
    case 'env':
        buildManager.configureEnvironment();
        break;
    default:
        console.log(`
xScheduler Build Configuration Script

Usage:
  node scripts/build-config.js build [environment]    # Build for specific environment
  node scripts/build-config.js deploy                 # Create deployment package
  node scripts/build-config.js env                    # Configure environment

Environments:
  development  # Local development
  staging      # Staging server
  production   # Production server

Examples:
  node scripts/build-config.js build production
  node scripts/build-config.js deploy
  node scripts/build-config.js env
        `);
}

export default BuildManager;
