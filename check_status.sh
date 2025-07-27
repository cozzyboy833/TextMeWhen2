#!/bin/bash
# check_status.sh - Check AI training system status

echo "📊 AI Training System Status"
echo "============================"
echo ""

# Check PHP
if command -v php &> /dev/null; then
    echo "✅ PHP installed: $(php -v | head -n1 | cut -d' ' -f2)"
else
    echo "❌ PHP not found - install with: brew install php"
fi

echo ""

# Check if server is running
if pgrep -f "php -S localhost:8000" > /dev/null; then
    echo "✅ Training server is RUNNING"
    echo "🌐 Interface: http://localhost:8000/user_friendly_trainer.html"
else
    echo "❌ Training server is STOPPED"
    echo "🚀 Start with: ./start_training.sh"
fi

echo ""

# Check database
if [ -f "textmewhen.db" ]; then
    echo "✅ Database found"
    if command -v sqlite3 &> /dev/null; then
        REMINDER_COUNT=$(sqlite3 textmewhen.db "SELECT COUNT(*) FROM reminders;" 2>/dev/null || echo "0")
        echo "📊 Reminders in database: $REMINDER_COUNT"
        
        if [ "$REMINDER_COUNT" = "0" ]; then
            echo "⚠️  No reminders found - create some first!"
        fi
    else
        echo "📊 Reminders in database: (can't count - sqlite3 not installed)"
    fi
else
    echo "❌ Database not found"
    echo "🔧 Run: ./setup_mac.sh"
fi

echo ""

# Check AI parser
if [ -f "enhanced_ai_parser.php" ]; then
    echo "✅ Enhanced AI parser exists"
    echo "🧠 AI model ready for testing"
else
    echo "⚠️  No enhanced AI parser yet"
    echo "💡 Generate one by training the AI or run: php quick_generate_parser.php"
fi

echo ""

# Check git status
if [ -d ".git" ]; then
    echo "✅ Git repository connected"
    CHANGES=$(git status --porcelain 2>/dev/null | wc -l | tr -d ' ')
    if [ "$CHANGES" -gt 0 ]; then
        echo "📝 You have $CHANGES unsaved changes"
        echo "📤 Upload with: ./upload_changes.sh"
    else
        echo "💾 All changes saved"
    fi
else
    echo "❌ Not connected to Git"
    echo "💡 Download from GitHub for sharing improvements"
fi

echo ""

# Overall health check
echo "🏥 Overall Health:"
if [ -f "textmewhen.db" ] && command -v php &> /dev/null; then
    echo "✅ System is healthy and ready for training!"
    echo ""
    echo "🎯 Quick Actions:"
    echo "  ./start_training.sh  - Start training interface"
    echo "  ./help.sh           - Get detailed help"
else
    echo "⚠️  System needs setup"
    echo ""
    echo "🔧 Fix with:"
    echo "  ./setup_mac.sh      - Run initial setup"
    echo "  ./help.sh           - Get help"
fi

echo ""