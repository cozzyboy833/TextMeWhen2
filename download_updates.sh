#!/bin/bash
# download_updates.sh - Get latest AI improvements

echo "📥 Downloading Latest AI Improvements"
echo "====================================="
echo ""

if [ ! -d ".git" ]; then
    echo "❌ This doesn't seem to be a Git repository."
    echo "💡 Download the project from GitHub first!"
    exit 1
fi

echo "📊 Checking for updates..."
git fetch

# Check if there are updates
LOCAL=$(git rev-parse @)
REMOTE=$(git rev-parse @{u} 2>/dev/null)

if [ "$LOCAL" = "$REMOTE" ]; then
    echo "✅ You already have the latest version!"
else
    echo "📥 New improvements available! Downloading..."
    
    # Backup local changes if any
    if [ -n "$(git status --porcelain)" ]; then
        echo "💾 Backing up your local changes..."
        git stash push -m "Auto-backup before update $(date)"
    fi
    
    git pull
    
    # Restore local changes if we stashed them
    if git stash list | grep -q "Auto-backup before update"; then
        echo "🔄 Restoring your local changes..."
        git stash pop
    fi
    
    echo "✅ Updated successfully!"
    echo ""
    echo "🎯 What's new:"
    git log --oneline -5
fi

echo ""