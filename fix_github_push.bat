@echo off
REM fix_github_push.bat - Fix the GitHub push error

echo 🔧 Fixing GitHub Push Error
echo ============================
echo.

echo The error happened because GitHub created some files (like README) 
echo that your local computer doesn't have. Let's fix this!
echo.

echo Step 1: Pull remote changes...
git pull origin main --allow-unrelated-histories

if errorlevel 1 (
    echo Trying with master branch...
    git pull origin master --allow-unrelated-histories
)

echo.
echo Step 2: Push your files...
git push origin main

if errorlevel 1 (
    echo Trying with master branch...
    git push origin master
)

echo.
if errorlevel 1 (
    echo ❌ Still having issues. Let's try the force method:
    echo.
    echo ⚠️  This will replace everything on GitHub with your local files.
    echo Is this OK? Your files will become the "master" version.
    echo.
    set /p FORCE="🚨 Force push? This overwrites GitHub content (y/n): "
    
    if /i "!FORCE!"=="y" (
        echo 💪 Force pushing...
        git push origin main --force
        
        if errorlevel 1 (
            git push origin master --force
        )
        
        echo ✅ Force push complete!
    ) else (
        echo ❌ Push cancelled. Manual fix needed.
        echo.
        echo 🔧 Manual commands to try:
        echo git pull origin main --allow-unrelated-histories
        echo git push origin main
    )
) else (
    echo ✅ SUCCESS! Your files are now on GitHub!
    echo.
    echo 🔗 Check your repository at:
    echo https://github.com/cozzyboy833/TextMeWhen2
)

echo.
pause