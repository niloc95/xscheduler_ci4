#!/usr/bin/env node

/**
 * xScheduler Release Script
 * 
 * Automatically creates a GitHub Release by:
 * 1. Building production assets
 * 2. Bumping version in package.json
 * 3. Updating CHANGELOG.md
 * 4. Creating git commit
 * 5. Creating and pushing git tag
 * 6. GitHub Actions then creates the release automatically
 * 
 * Usage:
 *   npm run release              # Bump patch version (1.0.0 -> 1.0.1)
 *   npm run release:minor        # Bump minor version (1.0.0 -> 1.1.0)
 *   npm run release:major        # Bump major version (1.0.0 -> 2.0.0)
 *   npm run release -- --version=1.2.3  # Specific version
 *   npm run release -- --beta    # Create beta release (1.0.0-beta.1)
 *   npm run release -- --rc      # Create release candidate (1.0.0-rc.1)
 *   npm run release -- --skip-package  # Skip local deployment package creation
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { execSync } from 'child_process';
import readline from 'readline';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..');

// Parse command line arguments
const args = process.argv.slice(2);
const getArg = (name) => {
    const arg = args.find(a => a.startsWith(`--${name}=`));
    return arg ? arg.split('=')[1] : null;
};
const hasFlag = (name) => args.includes(`--${name}`);

// Determine version bump type
let bumpType = 'patch'; // default
if (args.includes('minor')) bumpType = 'minor';
if (args.includes('major')) bumpType = 'major';
if (hasFlag('beta')) bumpType = 'beta';
if (hasFlag('rc')) bumpType = 'rc';
if (hasFlag('alpha')) bumpType = 'alpha';

const specificVersion = getArg('version');
const dryRun = hasFlag('dry-run');
const skipBuild = hasFlag('skip-build');
const skipPackage = hasFlag('skip-package');
const force = hasFlag('force');

// Colors for console output
const colors = {
    reset: '\x1b[0m',
    bright: '\x1b[1m',
    red: '\x1b[31m',
    green: '\x1b[32m',
    yellow: '\x1b[33m',
    blue: '\x1b[34m',
    cyan: '\x1b[36m',
};

const log = {
    info: (msg) => console.log(`${colors.blue}ℹ${colors.reset} ${msg}`),
    success: (msg) => console.log(`${colors.green}✓${colors.reset} ${msg}`),
    warn: (msg) => console.log(`${colors.yellow}⚠${colors.reset} ${msg}`),
    error: (msg) => console.log(`${colors.red}✗${colors.reset} ${msg}`),
    step: (msg) => console.log(`${colors.cyan}→${colors.reset} ${msg}`),
    title: (msg) => console.log(`\n${colors.bright}${colors.blue}${msg}${colors.reset}\n`),
};

// Helper to run shell commands
function run(command, options = {}) {
    const { silent = false, allowFail = false } = options;
    try {
        const result = execSync(command, { 
            cwd: projectRoot, 
            encoding: 'utf8',
            stdio: silent ? 'pipe' : 'inherit'
        });
        return result;
    } catch (error) {
        if (!allowFail) {
            log.error(`Command failed: ${command}`);
            process.exit(1);
        }
        return null;
    }
}

// Helper to run command and get output
function runSilent(command) {
    try {
        return execSync(command, { cwd: projectRoot, encoding: 'utf8' }).trim();
    } catch {
        return '';
    }
}

// Read package.json
function readPackageJson() {
    const packagePath = path.join(projectRoot, 'package.json');
    return JSON.parse(fs.readFileSync(packagePath, 'utf8'));
}

// Write package.json
function writePackageJson(data) {
    const packagePath = path.join(projectRoot, 'package.json');
    fs.writeFileSync(packagePath, JSON.stringify(data, null, 2) + '\n');
}

// Parse semver version
function parseVersion(version) {
    const match = version.match(/^(\d+)\.(\d+)\.(\d+)(?:-(alpha|beta|rc)\.(\d+))?$/);
    if (!match) return null;
    return {
        major: parseInt(match[1]),
        minor: parseInt(match[2]),
        patch: parseInt(match[3]),
        prerelease: match[4] || null,
        prereleaseNum: match[5] ? parseInt(match[5]) : 0,
    };
}

// Format version object to string
function formatVersion(v) {
    let version = `${v.major}.${v.minor}.${v.patch}`;
    if (v.prerelease) {
        version += `-${v.prerelease}.${v.prereleaseNum}`;
    }
    return version;
}

// Bump version based on type
function bumpVersion(currentVersion, type) {
    const v = parseVersion(currentVersion);
    if (!v) {
        log.error(`Invalid current version: ${currentVersion}`);
        process.exit(1);
    }

    switch (type) {
        case 'major':
            v.major++;
            v.minor = 0;
            v.patch = 0;
            v.prerelease = null;
            v.prereleaseNum = 0;
            break;
        case 'minor':
            v.minor++;
            v.patch = 0;
            v.prerelease = null;
            v.prereleaseNum = 0;
            break;
        case 'patch':
            if (v.prerelease) {
                // If current is prerelease, patch just removes prerelease
                v.prerelease = null;
                v.prereleaseNum = 0;
            } else {
                v.patch++;
            }
            break;
        case 'alpha':
        case 'beta':
        case 'rc':
            if (v.prerelease === type) {
                v.prereleaseNum++;
            } else {
                if (!v.prerelease) v.patch++;
                v.prerelease = type;
                v.prereleaseNum = 1;
            }
            break;
    }

    return formatVersion(v);
}

// Update CHANGELOG.md
function updateChangelog(newVersion) {
    const changelogPath = path.join(projectRoot, 'CHANGELOG.md');
    if (!fs.existsSync(changelogPath)) {
        log.warn('CHANGELOG.md not found, skipping changelog update');
        return;
    }

    let changelog = fs.readFileSync(changelogPath, 'utf8');
    const today = new Date().toISOString().split('T')[0];
    
    // Replace [Unreleased] section header with new version
    const unreleasedRegex = /## \[Unreleased\]/;
    if (unreleasedRegex.test(changelog)) {
        changelog = changelog.replace(
            unreleasedRegex,
            `## [Unreleased]\n\n## [${newVersion}] - ${today}`
        );
        
        // Update links at bottom
        const pkg = readPackageJson();
        const repoUrl = 'https://github.com/niloc95/xscheduler_ci4';
        
        // Add new version link if not exists
        if (!changelog.includes(`[${newVersion}]:`)) {
            const linkRegex = /(\[Unreleased\]:.*)/;
            changelog = changelog.replace(
                linkRegex,
                `$1\n[${newVersion}]: ${repoUrl}/releases/tag/v${newVersion}`
            );
        }
        
        fs.writeFileSync(changelogPath, changelog);
        log.success(`Updated CHANGELOG.md with version ${newVersion}`);
    } else {
        log.warn('Could not find [Unreleased] section in CHANGELOG.md');
    }
}

// Check for uncommitted changes
function checkGitStatus() {
    const status = runSilent('git status --porcelain');
    return status.length === 0;
}

// Get current branch
function getCurrentBranch() {
    return runSilent('git rev-parse --abbrev-ref HEAD');
}

// Check if tag exists
function tagExists(tag) {
    const result = runSilent(`git tag -l "${tag}"`);
    return result === tag;
}

// Prompt user for confirmation
async function confirm(message) {
    const rl = readline.createInterface({
        input: process.stdin,
        output: process.stdout,
    });

    return new Promise((resolve) => {
        rl.question(`${colors.yellow}?${colors.reset} ${message} (y/N): `, (answer) => {
            rl.close();
            resolve(answer.toLowerCase() === 'y' || answer.toLowerCase() === 'yes');
        });
    });
}

// Main release function
async function release() {
    log.title('🚀 xScheduler Release Script');

    // Pre-flight checks
    log.step('Running pre-flight checks...');

    // Check if on main branch
    const branch = getCurrentBranch();
    if (branch !== 'main' && !force) {
        log.error(`You must be on 'main' branch to release. Currently on: ${branch}`);
        log.info('Use --force to override');
        process.exit(1);
    }

    // Check for uncommitted changes
    if (!checkGitStatus() && !force) {
        log.error('You have uncommitted changes. Commit or stash them first.');
        log.info('Use --force to override');
        process.exit(1);
    }

    // Pull latest changes
    log.step('Pulling latest changes...');
    run('git pull origin main', { silent: true, allowFail: true });

    // Determine new version
    const pkg = readPackageJson();
    const currentVersion = pkg.version;
    const newVersion = specificVersion || bumpVersion(currentVersion, bumpType);
    const tag = `v${newVersion}`;

    log.info(`Current version: ${currentVersion}`);
    log.info(`New version: ${newVersion}`);
    log.info(`Git tag: ${tag}`);
    log.info(`Release type: ${bumpType}`);

    // Check if tag already exists
    if (tagExists(tag)) {
        log.error(`Tag ${tag} already exists!`);
        process.exit(1);
    }

    // Confirm with user
    if (!dryRun) {
        const proceed = await confirm(`Create release ${tag}?`);
        if (!proceed) {
            log.warn('Release cancelled');
            process.exit(0);
        }
    }

    if (dryRun) {
        log.warn('DRY RUN - No changes will be made');
        log.info(`Would bump version: ${currentVersion} -> ${newVersion}`);
        log.info(`Would create tag: ${tag}`);
        process.exit(0);
    }

    // Build assets
    if (!skipBuild) {
        log.step('Building production assets...');
        run('npm run build');
    }

    // Create deployment package (optional, GitHub Actions will also do this)
    if (!skipPackage) {
        log.step('Creating deployment package...');
        run('node scripts/package.js');
        log.success('Local deployment package created: webschedulr-deploy.zip');
        log.info('Note: GitHub Actions will create the official release package');
    }

    // Update version in package.json
    log.step('Updating package.json version...');
    pkg.version = newVersion;
    writePackageJson(pkg);

    // Update CHANGELOG.md
    log.step('Updating CHANGELOG.md...');
    updateChangelog(newVersion);

    // Git commit
    log.step('Creating git commit...');
    run('git add package.json CHANGELOG.md');
    run(`git commit -m "chore: release v${newVersion}"`, { allowFail: true });

    // Create tag
    log.step(`Creating tag ${tag}...`);
    run(`git tag -a ${tag} -m "Release ${newVersion}"`);

    // Push to GitHub
    log.step('Pushing to GitHub...');
    run('git push origin main');
    run(`git push origin ${tag}`);

    // Success!
    log.title('🎉 Release Created Successfully!');
    log.success(`Version: ${newVersion}`);
    log.success(`Tag: ${tag}`);
    log.info('');
    log.info('GitHub Actions is now building your release...');
    log.info(`Watch progress: https://github.com/niloc95/xscheduler_ci4/actions`);
    log.info(`Release will appear at: https://github.com/niloc95/xscheduler_ci4/releases/tag/${tag}`);
}

// Run the release
release().catch((error) => {
    log.error(`Release failed: ${error.message}`);
    process.exit(1);
});
