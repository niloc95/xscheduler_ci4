#!/bin/bash

# GitHub Repository Label Setup Script
# Creates all necessary labels for xScheduler issue tracking

echo "🏷️  Setting up GitHub labels for xScheduler..."
echo ""

# Repository
REPO="niloc95/xscheduler_ci4"

# Check if gh CLI is installed
if ! command -v gh &> /dev/null; then
    echo "❌ GitHub CLI (gh) is not installed."
    echo "📥 Install it from: https://cli.github.com/"
    echo ""
    echo "Or use manual label creation from .github/SETUP_GUIDE.md"
    exit 1
fi

# Check if authenticated
if ! gh auth status &> /dev/null; then
    echo "🔐 Please authenticate with GitHub CLI first:"
    echo "   gh auth login"
    exit 1
fi

echo "Creating priority labels..."

gh label create "priority: high" \
    --repo $REPO \
    --color d73a4a \
    --description "Critical issue requiring immediate attention" \
    --force

gh label create "priority: medium" \
    --repo $REPO \
    --color fbca04 \
    --description "Important but not critical" \
    --force

gh label create "priority: low" \
    --repo $REPO \
    --color 0e8a16 \
    --description "Nice to have, low urgency" \
    --force

echo "✅ Priority labels created"
echo ""

echo "Creating status labels..."

gh label create "needs-info" \
    --repo $REPO \
    --color d876e3 \
    --description "Waiting for more information from reporter" \
    --force

gh label create "confirmed" \
    --repo $REPO \
    --color 0e8a16 \
    --description "Bug confirmed and ready to fix" \
    --force

gh label create "in-progress" \
    --repo $REPO \
    --color 1d76db \
    --description "Currently being worked on" \
    --force

echo "✅ Status labels created"
echo ""

echo "Verifying default labels exist..."

# These should already exist, but create if missing
gh label create "bug" \
    --repo $REPO \
    --color d73a4a \
    --description "Something isn't working" \
    --force 2>/dev/null || echo "  'bug' already exists"

gh label create "enhancement" \
    --repo $REPO \
    --color a2eeef \
    --description "New feature or request" \
    --force 2>/dev/null || echo "  'enhancement' already exists"

gh label create "documentation" \
    --repo $REPO \
    --color 0075ca \
    --description "Improvements or additions to documentation" \
    --force 2>/dev/null || echo "  'documentation' already exists"

gh label create "question" \
    --repo $REPO \
    --color d876e3 \
    --description "Further information is requested" \
    --force 2>/dev/null || echo "  'question' already exists"

echo "✅ Default labels verified"
echo ""

echo "🎉 All labels created successfully!"
echo ""
echo "📋 Labels created:"
echo "   Priority: high, medium, low"
echo "   Status: needs-info, confirmed, in-progress"
echo "   Type: bug, enhancement, documentation, question"
echo ""
echo "Next steps:"
echo "1. Enable GitHub Discussions in repository settings"
echo "2. Commit and push issue templates: git add .github/ && git commit && git push"
echo "3. Test by creating a new issue"
echo ""
echo "See .github/SETUP_GUIDE.md for complete setup instructions"
