#!/bin/bash
# upload_changes.sh - Upload your AI improvements to GitHub

echo "📤 Uploading Your AI Improvements"
echo "=================================="
echo ""

# Check if we're in a git repository
if [ ! -d ".git" ]; then
    echo "❌ This doesn't seem to be a Git repository."
    echo "💡 Download the project from GitHub first!"
    exit 1
fi

# Get the commit message from user
if [ -z "$1" ]; then
    echo "💬 What did you improve? (e.g., 'Fixed Yankees game detection')"
    read -p "Description: " COMMIT_MESSAGE
else
    COMMIT_MESSAGE="$1"
fi

if [ -z "$COMMIT_MESSAGE" ]; then
    COMMIT_MESSAGE="Improved AI training data"
fi

echo "📊 Checking what changed..."

# Show what files changed
git status --porcelain

# Check if there are any changes
if [ -z "$(git status --porcelain)" ]; then
    echo "❌ No changes to upload!"
    echo "💡 Make some AI corrections first, then try again."
    exit 0
fi

echo ""
echo "📝 Changes to upload:"
echo "- Database updates (your corrections)"
echo "- Enhanced AI parser (if generated)"
echo "- Training improvements"
echo ""

# Confirm upload
read -p "🚀 Ready to upload? (y/n): " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "📤 Uploading to GitHub..."
    
    # Add all changes
    git add .
    
    # Commit with timestamp and message
    TIMESTAMP=$(date "+%Y-%m-%d %H:%M")
    git commit -m "AI Training Update ($TIMESTAMP): $COMMIT_MESSAGE"
    
    # Try to push
    if git push; then
        echo ""
        echo "✅ Upload Complete!"
        echo "🎉 Your AI improvements are now shared!"
        echo ""
        echo "📋 What was uploaded:"
        echo "- Your training corrections"
        echo "- Improved AI models"
        echo "- Updated database"
        echo ""
        echo "💡 Others can now download your improvements with:"
        echo "   git pull"
    else
        echo ""
        echo "❌ Upload failed. Common fixes:"
        echo "1. Check your internet connection"
        echo "2. Make sure you have push permissions"
        echo "3. Try: git pull first (in case others made changes)"
        echo ""
        echo "🔧 Manual fix commands:"
        echo "   git pull"
        echo "   git push"
    fi
else
    echo "❌ Upload cancelled."
    echo "💡 Your changes are saved locally. Upload anytime with:"
    echo "   ./upload_changes.sh"
fi

echo ""