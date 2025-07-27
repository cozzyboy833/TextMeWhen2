#!/bin/bash
# auto_github_setup.sh - Automatically set up GitHub repository

echo "🚀 Automatic GitHub Setup for TextMeWhen AI"
echo "============================================"
echo ""

# Check if git is installed
if ! command -v git &> /dev/null; then
    echo "❌ Git not found. Please install Git first:"
    echo "   Download from: https://git-scm.com/"
    exit 1
fi

# Check if we're already in a git repository
if [ -d ".git" ]; then
    echo "✅ Already in a Git repository"
else
    echo "📝 Initializing Git repository..."
    git init
fi

# Get GitHub username and repository name
echo "📋 GitHub Setup Information:"
echo ""
read -p "🔑 Your GitHub username: " GITHUB_USERNAME
read -p "📁 Repository name (default: TextMeWhen2): " REPO_NAME
REPO_NAME=${REPO_NAME:-TextMeWhen2}

echo ""
echo "📦 Setting up repository: $GITHUB_USERNAME/$REPO_NAME"
echo ""

# Create .gitignore if it doesn't exist
if [ ! -f ".gitignore" ]; then
    echo "📝 Creating .gitignore file..."
    cat > .gitignore << 'EOF'
# Logs
*.log
server.log
.server_pid

# Temporary files
*.tmp
*.temp

# Mac files
.DS_Store
**/.DS_Store
._*
.Spotlight-V100
.Trashes

# Windows files
ehthumbs.db
Thumbs.db

# Optional: Uncomment to ignore database (keep training data local)
# textmewhen.db
EOF
fi

# Create README.md if it doesn't exist
if [ ! -f "README.md" ]; then
    echo "📝 Creating README.md..."
    cat > README.md << EOF
# 🤖 TextMeWhen AI Training System

> Help train an AI to better understand reminder requests!

## 🚀 Quick Start for Mac Users

### Setup (One Time):
\`\`\`bash
git clone https://github.com/$GITHUB_USERNAME/$REPO_NAME.git
cd $REPO_NAME
chmod +x *.sh
./setup_mac.sh
\`\`\`

### Daily Usage:
\`\`\`bash
./start_training.sh    # Opens training interface
# Fix AI mistakes in browser
./upload_changes.sh    # Share improvements
\`\`\`

## 🎯 What You're Training

Help the AI distinguish between:
- ✅ "Yankees game **ends**" (game completion)
- ✅ "Yankees game **starts**" (game beginning)  
- ✅ Stock prices, weather, time-based reminders

## 📞 Need Help?
Run: \`./help.sh\`

## 🤝 Contributing
1. Fix AI mistakes in the training interface
2. Upload improvements with \`./upload_changes.sh\`
3. Download others' improvements with \`./download_updates.sh\`

---
*Generated on $(date)*
EOF
fi

# Make all shell scripts executable
echo "🔧 Making scripts executable..."
chmod +x *.sh 2>/dev/null || echo "   (No .sh files found yet - that's OK)"

# Set up git remote
echo "🔗 Setting up GitHub remote..."
git remote remove origin 2>/dev/null  # Remove if exists
git remote add origin "https://github.com/$GITHUB_USERNAME/$REPO_NAME.git"

# Add all files
echo "📦 Adding files to Git..."
git add .

# Check if there are any changes to commit
if [ -z "$(git status --porcelain)" ]; then
    echo "⚠️  No changes to commit. Add your files first!"
    echo ""
    echo "📋 Next steps:"
    echo "1. Copy all your TextMeWhen files to this directory"
    echo "2. Run this script again"
    exit 0
fi

# Create initial commit
echo "💾 Creating initial commit..."
git commit -m "Initial TextMeWhen AI training system

- Added AI training interface
- Added Mac setup scripts
- Added user-friendly training tools
- Ready for collaborative AI improvement"

# Check if repository exists on GitHub
echo ""
echo "📤 Ready to push to GitHub!"
echo ""
echo "⚠️  IMPORTANT: Make sure you've created the repository on GitHub first!"
echo "   Go to: https://github.com/new"
echo "   Repository name: $REPO_NAME"
echo "   Make it Public ✅"
echo "   Don't add README (we have one) ❌"
echo ""
read -p "✅ Have you created the GitHub repository? (y/n): " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "🚀 Pushing to GitHub..."
    
    if git push -u origin main 2>/dev/null || git push -u origin master 2>/dev/null; then
        echo ""
        echo "🎉 SUCCESS! Your repository is online!"
        echo "=================================="
        echo ""
        echo "🔗 Repository URL: https://github.com/$GITHUB_USERNAME/$REPO_NAME"
        echo ""
        echo "📤 Share this with your friend:"
        echo "git clone https://github.com/$GITHUB_USERNAME/$REPO_NAME.git"
        echo ""
        echo "📋 What they should do:"
        echo "1. Download: git clone https://github.com/$GITHUB_USERNAME/$REPO_NAME.git"
        echo "2. Setup: cd $REPO_NAME && chmod +x *.sh && ./setup_mac.sh"
        echo "3. Train: ./start_training.sh"
        echo "4. Share: ./upload_changes.sh \"description\""
        echo ""
        echo "✅ All done! Your AI training system is ready for collaboration!"
        
    else
        echo ""
        echo "❌ Push failed. Common issues:"
        echo "1. Repository doesn't exist on GitHub"
        echo "2. Wrong username/repository name"
        echo "3. No permission to push"
        echo ""
        echo "🔧 Manual push command:"
        echo "git push -u origin main"
    fi
else
    echo ""
    echo "📋 Create the repository first, then run:"
    echo "git push -u origin main"
    echo ""
    echo "🔗 Create repository at: https://github.com/new"
fi

echo ""
echo "💡 Helpful commands for later:"
echo "   git add . && git commit -m \"message\" && git push    # Upload changes"
echo "   git pull                                            # Download updates"
echo "   ./upload_changes.sh \"description\"                   # Easy upload script"
echo ""