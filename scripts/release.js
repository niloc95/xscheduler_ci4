#!/usr/bin/env node

/**
 * xScheduler Release Script
 * 
 * Automatically creates a GitHub Release by:
 * 1. Building production assets
 * 2. Bumping version in package.json
 * 3. Updating docs/changelog.md
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
const syncChangelogOnly = hasFlag('sync-changelog');

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

function readJson(relativePath) {
    return JSON.parse(fs.readFileSync(path.join(projectRoot, relativePath), 'utf8'));
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

function readReleaseConfig() {
    const configPath = path.join(projectRoot, '.github', 'release-config.json');
    if (!fs.existsSync(configPath)) {
        return null;
    }

    return readJson(path.join('.github', 'release-config.json'));
}

function getRemoteUrl() {
    return runSilent('git remote get-url origin');
}

function getRepoInfo() {
    const remote = getRemoteUrl();
    const match = remote.match(/github\.com[:/]([^/]+)\/([^/.]+)(?:\.git)?$/i);

    if (match) {
        return { owner: match[1], repo: match[2] };
    }

    return { owner: 'niloc95', repo: 'xscheduler_ci4' };
}

function getLatestTag(ref = 'HEAD') {
    return runSilent(`git describe --tags --abbrev=0 ${ref}`);
}

function getPreviousTag(tag) {
    return runSilent(`git describe --tags --abbrev=0 ${tag}^`);
}

function getTagDate(tag) {
    return runSilent(`git log -1 --format=%cI ${tag}`);
}

function getCommitSubjects(range) {
    if (!range) {
        return [];
    }

    const output = runSilent(`git log --no-merges --pretty=format:%s ${range}`);

    return output
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean)
        .filter((line) => !/^\{.*\}$/.test(line))
        .filter((line) => !/^chore:\s+release\s+v/i.test(line));
}

function categorizeFromTitle(subject) {
    const normalized = subject.toLowerCase();

    if (normalized.startsWith('feat') || normalized.startsWith('feature')) {
        return 'Added';
    }

    if (normalized.startsWith('fix') || normalized.includes(' bug')) {
        return 'Fixed';
    }

    if (normalized.startsWith('security')) {
        return 'Security';
    }

    if (
        normalized.startsWith('docs') ||
        normalized.startsWith('refactor') ||
        normalized.startsWith('perf') ||
        normalized.startsWith('chore') ||
        normalized.startsWith('build') ||
        normalized.startsWith('ci') ||
        normalized.startsWith('style') ||
        normalized.startsWith('test')
    ) {
        return 'Changed';
    }

    return 'Changed';
}

function categorizeFromLabels(labels = []) {
    const labelSet = new Set(labels.map((label) => label.toLowerCase()));

    if (labelSet.has('security')) {
        return 'Security';
    }

    if (labelSet.has('bug') || labelSet.has('fix')) {
        return 'Fixed';
    }

    if (labelSet.has('enhancement') || labelSet.has('feature')) {
        return 'Added';
    }

    return null;
}

function buildGroupedEntries() {
    return {
        Added: [],
        Changed: [],
        Fixed: [],
        Security: [],
    };
}

function dedupeEntries(entries) {
    return [...new Set(entries)];
}

async function githubRequest(url, token) {
    const response = await fetch(url, {
        headers: {
            Accept: 'application/vnd.github+json',
            Authorization: `Bearer ${token}`,
            'User-Agent': 'xscheduler-release-script',
        },
    });

    if (!response.ok) {
        throw new Error(`GitHub API request failed: ${response.status} ${response.statusText}`);
    }

    return response.json();
}

async function fetchMergedPullRequestsSince(sinceDate) {
    const token = process.env.GITHUB_TOKEN || process.env.GH_TOKEN;
    if (!token || !sinceDate) {
        return [];
    }

    const { owner, repo } = getRepoInfo();
    const pullRequests = [];
    let page = 1;

    while (page <= 5) {
        const url = `https://api.github.com/repos/${owner}/${repo}/pulls?state=closed&base=main&sort=updated&direction=desc&per_page=100&page=${page}`;
        const items = await githubRequest(url, token);

        if (!Array.isArray(items) || items.length === 0) {
            break;
        }

        let sawOlderPullRequest = false;

        for (const item of items) {
            if (!item.merged_at) {
                continue;
            }

            if (item.merged_at <= sinceDate) {
                sawOlderPullRequest = true;
                continue;
            }

            pullRequests.push({
                title: item.title,
                number: item.number,
                author: item.user?.login || 'unknown',
                labels: (item.labels || []).map((label) => label.name),
                mergedAt: item.merged_at,
            });
        }

        if (sawOlderPullRequest) {
            break;
        }

        page += 1;
    }

    return pullRequests.sort((a, b) => a.mergedAt.localeCompare(b.mergedAt));
}

function buildEntriesFromPullRequests(pullRequests) {
    const grouped = buildGroupedEntries();

    for (const pullRequest of pullRequests) {
        if (/^\{.*\}$/.test(pullRequest.title.trim())) {
            continue;
        }

        const category = categorizeFromLabels(pullRequest.labels) || categorizeFromTitle(pullRequest.title);
        grouped[category].push(`- ${pullRequest.title} (#${pullRequest.number}) by @${pullRequest.author}`);
    }

    return grouped;
}

function buildEntriesFromCommits(range) {
    const grouped = buildGroupedEntries();

    for (const subject of getCommitSubjects(range)) {
        const category = categorizeFromTitle(subject);
        grouped[category].push(`- ${subject}`);
    }

    return grouped;
}

function formatGroupedEntries(grouped) {
    const sections = [];

    for (const [heading, entries] of Object.entries(grouped)) {
        const uniqueEntries = dedupeEntries(entries);
        if (uniqueEntries.length === 0) {
            continue;
        }

        sections.push(`### ${heading}\n${uniqueEntries.join('\n')}`);
    }

    if (sections.length === 0) {
        return '### Changed\n- No unreleased changes documented yet.';
    }

    return sections.join('\n\n');
}

async function generateChangelogBody(referenceTag, targetRef = 'HEAD') {
    const sinceDate = referenceTag ? getTagDate(referenceTag) : '';
    const commitRange = referenceTag ? `${referenceTag}..${targetRef}` : targetRef;

    try {
        const pullRequests = await fetchMergedPullRequestsSince(sinceDate);
        if (pullRequests.length > 0) {
            return formatGroupedEntries(buildEntriesFromPullRequests(pullRequests));
        }
    } catch (error) {
        log.warn(`GitHub PR metadata unavailable, falling back to commit parsing (${error.message})`);
    }

    return formatGroupedEntries(buildEntriesFromCommits(commitRange));
}

function replaceUnreleasedSection(changelog, body) {
    const normalizedBody = body.trim();
    const marker = '## [Unreleased]';
    const startIndex = changelog.indexOf(marker);

    if (startIndex === -1) {
        return `## [Unreleased]\n\n${normalizedBody}\n\n${changelog}`;
    }

    const nextHeadingIndex = changelog.indexOf('\n## [', startIndex + marker.length);
    const before = changelog.slice(0, startIndex);
    const after = nextHeadingIndex === -1 ? '' : changelog.slice(nextHeadingIndex + 1);

    return `${before}## [Unreleased]\n\n${normalizedBody}\n\n${after}`;
}

function upsertReferenceLink(changelog, label, url) {
    const escapedLabel = label.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const linkPattern = new RegExp(`^\\[${escapedLabel}\\]: .*$`, 'm');
    const replacement = `[${label}]: ${url}`;

    if (linkPattern.test(changelog)) {
        return changelog.replace(linkPattern, replacement);
    }

    return `${changelog.trimEnd()}\n${replacement}\n`;
}

function syncUnreleasedChangelog(body) {
    const changelogPath = path.join(projectRoot, 'docs', 'changelog.md');
    let changelog = fs.readFileSync(changelogPath, 'utf8');
    const latestTag = getLatestTag();

    changelog = replaceUnreleasedSection(changelog, body);
    changelog = upsertReferenceLink(
        changelog,
        'Unreleased',
        `https://github.com/${getRepoInfo().owner}/${getRepoInfo().repo}/compare/${latestTag || 'HEAD'}...HEAD`
    );

    fs.writeFileSync(changelogPath, changelog);
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

// Update docs/changelog.md
function updateChangelog(newVersion, body, previousTag) {
    const changelogPath = path.join(projectRoot, 'docs', 'changelog.md');
    if (!fs.existsSync(changelogPath)) {
        log.warn('docs/changelog.md not found, skipping changelog update');
        return;
    }

    let changelog = fs.readFileSync(changelogPath, 'utf8');
    const today = new Date().toISOString().split('T')[0];

    const marker = '## [Unreleased]';
    const startIndex = changelog.indexOf(marker);
    if (startIndex === -1) {
        log.warn('Could not find [Unreleased] section in docs/changelog.md');
        return;
    }

    const nextHeadingIndex = changelog.indexOf('\n## [', startIndex + marker.length);
    const before = changelog.slice(0, startIndex);
    const after = nextHeadingIndex === -1 ? '' : changelog.slice(nextHeadingIndex + 1);
    const releaseSection = `## [Unreleased]\n\n### Changed\n- No unreleased changes documented yet.\n\n## [${newVersion}] - ${today}\n\n${body.trim()}\n\n`;

    changelog = `${before}${releaseSection}${after}`;

    const { owner, repo } = getRepoInfo();
    const baseCompare = previousTag
        ? `https://github.com/${owner}/${repo}/compare/${previousTag}...v${newVersion}`
        : `https://github.com/${owner}/${repo}/releases/tag/v${newVersion}`;

    changelog = upsertReferenceLink(changelog, 'Unreleased', `https://github.com/${owner}/${repo}/compare/v${newVersion}...HEAD`);
    changelog = upsertReferenceLink(changelog, newVersion, baseCompare);

    fs.writeFileSync(changelogPath, changelog);
    log.success(`Updated docs/changelog.md with version ${newVersion}`);
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

    const latestTag = getLatestTag();

    if (syncChangelogOnly) {
        log.step('Generating changelog entries for [Unreleased]...');
        const changelogBody = await generateChangelogBody(latestTag);

        if (dryRun) {
            log.warn('DRY RUN - No files were modified');
            console.log(`\n${changelogBody}\n`);
            process.exit(0);
        }

        syncUnreleasedChangelog(changelogBody);
        log.success('Synchronized docs/changelog.md [Unreleased] section');
        process.exit(0);
    }

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
    const changelogBody = await generateChangelogBody(latestTag);

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
        console.log(`\n${changelogBody}\n`);
        process.exit(0);
    }

    // Build assets
    if (!skipBuild) {
        log.step('Building production assets...');
        run('npm run build');
    }

    // Bump version in package.json first so the deployment package
    // and version.json are stamped with the NEW version number.
    log.step('Updating package.json version...');
    pkg.version = newVersion;
    writePackageJson(pkg);

    // Create deployment package (optional, GitHub Actions will also do this)
    if (!skipPackage) {
        log.step('Creating deployment package...');
        run('node scripts/package.js');
        log.success('Local deployment package created: webschedulr-deploy.zip');
        log.info('Note: GitHub Actions will create the official release package');
    }

    // Update docs/changelog.md
    log.step('Updating docs/changelog.md...');
    updateChangelog(newVersion, changelogBody, latestTag);

    // Git commit
    log.step('Creating git commit...');
    run('git add package.json docs/changelog.md');
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
