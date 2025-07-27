@echo off
REM setup_github_windows.bat - Windows GitHub setup

echo 🚀 GitHub Setup for TextMeWhen AI (Windows)
echo =============================================
echo.

REM Check if git is installed
git --version >nul 2>&1
if errorlevel 1 (
    echo ❌ Git not found! Please install Git first:
    echo    Download from: https://git-scm.com/download/win
    echo    Then run this script again.
    pause
    exit /b 1
)

echo ✅ Git found!
echo.

REM Get GitHub info
set /p GITHUB_USERNAME="🔑 Your GitHub username: "
set /p REPO_NAME="📁 Repository name (default: TextMeWhen2): "
if "%REPO_NAME%"=="" set REPO_NAME=TextMeWhen2

echo.
echo 📦 Setting up repository: %GITHUB_USERNAME%/%REPO_NAME%
echo.

REM Initialize git if not already done
if not exist ".git" (
    echo 📝 Initializing Git repository...
    git init
)

REM Create .gitignore
echo 📝 Creating .gitignore file...
(
echo # Logs
echo *.log
echo server.log
echo.
echo # Temporary files
echo *.tmp
echo *.temp
echo.
echo # Windows files
echo Thumbs.db
echo ehthumbs.db
echo.
echo # Mac files ^(for your friend^)
echo .DS_Store
echo **/.DS_Store
) > .gitignore

REM Create README.md
echo 📝 Creating README.md...
(
echo # 🤖 TextMeWhen AI Training System
echo.
echo ^> Help train an AI to better understand reminder requests!
echo.
echo ## 🚀 Quick Start for Mac Users
echo.
echo ### Setup ^(One Time^):
echo ```bash
echo git clone https://github.com/%GITHUB_USERNAME%/%REPO_NAME%.git
echo cd %REPO_NAME%
echo chmod +x *.sh
echo ./setup_mac.sh
echo ```
echo.
echo ### Daily Usage:
echo ```bash
echo ./start_training.sh    # Opens training interface
echo # Fix AI mistakes in browser
echo ./upload_changes.sh    # Share improvements
echo ```
echo.
echo ## 🎯 What You're Training
echo.
echo Help the AI distinguish between:
echo - ✅ "Yankees game **ends**" ^(game completion^)
echo - ✅ "Yankees game **starts**" ^(game beginning^)  
echo - ✅ Stock prices, weather, time-based reminders
echo.
echo ## 📞 Need Help?
echo Run: `./help.sh`
echo.
echo ## 🤝 Contributing
echo 1. Fix AI mistakes in the training interface
echo 2. Upload improvements with `./upload_changes.sh`
echo 3. Download others' improvements with `./download_updates.sh`
) > README.md

REM Set up git remote
echo 🔗 Setting up GitHub remote...
git remote remove origin >nul 2>&1
git remote add origin https://github.com/%GITHUB_USERNAME%/%REPO_NAME%.git

REM Add all files
echo 📦 Adding files to Git...
git add .

REM Check if there are changes
git diff --cached --quiet
if errorlevel 1 (
    echo 💾 Creating initial commit...
    git commit -m "Initial TextMeWhen AI training system - Added AI training interface - Added Mac setup scripts - Added user-friendly training tools - Ready for collaborative AI improvement"
) else (
    echo ⚠️  No changes to commit. Add your files first!
    echo.
    echo 📋 Next steps:
    echo 1. Copy all your TextMeWhen files to this directory
    echo 2. Run this script again
    pause
    exit /b 0
)

echo.
echo 📤 Ready to push to GitHub!
echo.
echo ⚠️  IMPORTANT: Make sure you've created the repository on GitHub first!
echo    Go to: https://github.com/new
echo    Repository name: %REPO_NAME%
echo    Make it Public ✅
echo    Don't add README ^(we have one^) ❌
echo.
set /p CONTINUE="✅ Have you created the GitHub repository? (y/n): "

if /i "%CONTINUE%"=="y" (
    echo 🚀 Pushing to GitHub...
    
    git push -u origin main
    if errorlevel 1 (
        git push -u origin master
        if errorlevel 1 (
            echo.
            echo ❌ Push failed. Common issues:
            echo 1. Repository doesn't exist on GitHub
            echo 2. Wrong username/repository name
            echo 3. No permission to push
            echo.
            echo 🔧 Manual push command:
            echo git push -u origin main
            pause
            exit /b 1
        )
    )
    
    echo.
    echo 🎉 SUCCESS! Your repository is online!
    echo ==================================
    echo.
    echo 🔗 Repository URL: https://github.com/%GITHUB_USERNAME%/%REPO_NAME%
    echo.
    echo 📤 Share this with your friend:
    echo git clone https://github.com/%GITHUB_USERNAME%/%REPO_NAME%.git
    echo.
    echo 📋 What they should do:
    echo 1. Download: git clone https://github.com/%GITHUB_USERNAME%/%REPO_NAME%.git
    echo 2. Setup: cd %REPO_NAME% ^&^& chmod +x *.sh ^&^& ./setup_mac.sh
    echo 3. Train: ./start_training.sh
    echo 4. Share: ./upload_changes.sh "description"
    echo.
    echo ✅ All done! Your AI training system is ready for collaboration!
    
) else (
    echo.
    echo 📋 Create the repository first, then run:
    echo git push -u origin main
    echo.
    echo 🔗 Create repository at: https://github.com/new
)

echo.
echo 💡 Helpful commands for later:
echo    git add . ^&^& git commit -m "message" ^&^& git push    # Upload changes
echo    git pull                                            # Download updates
echo.
pause