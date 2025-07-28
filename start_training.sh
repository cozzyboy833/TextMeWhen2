#!/bin/bash
# start_training.sh - Start the AI training interface with REAL data

echo "🚀 Starting AI Training Interface (Real Data)..."
echo "================================================="
echo ""

# Check if everything is set up
if [ ! -f "textmewhen.db" ]; then
    echo "❌ Database not found. Run ./setup_mac.sh first!"
    exit 1
fi

# Check if we have reminders in the database
REMINDER_COUNT=$(sqlite3 textmewhen.db "SELECT COUNT(*) FROM reminders;" 2>/dev/null || echo "0")

if [ "$REMINDER_COUNT" = "0" ]; then
    echo "⚠️  No reminders found in database!"
    echo ""
    echo "🎯 Quick fix:"
    echo "1. Open: http://localhost:8000/index.html"
    echo "2. Create a few test reminders like:"
    echo "   - 'message me when the yankees game ends'"
    echo "   - 'text me when Apple stock hits \$200'"
    echo "   - 'remind me in 5 minutes'"
    echo "3. Then come back and run ./start_training.sh"
    echo ""
    read -p "Continue anyway? (y/n): " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Kill any existing PHP server
pkill -f "php -S localhost:8000" 2>/dev/null

# Start PHP server in background
echo "🌐 Starting web server on http://localhost:8000"
php -S localhost:8000 > server.log 2>&1 &
SERVER_PID=$!

# Wait a moment for server to start
sleep 2

# Save the real data trainer HTML to a file
cat > real_data_trainer.html << 'EOF'
<!-- Insert the artifact content here -->
EOF

# Try to open browser
if command -v open &> /dev/null; then
    echo "🖥️  Opening REAL DATA training interface in browser..."
    open http://localhost:8000/real_data_trainer.html
elif command -v xdg-open &> /dev/null; then
    xdg-open http://localhost:8000/real_data_trainer.html
else
    echo "📖 Manual step: Open http://localhost:8000/real_data_trainer.html in your browser"
fi

echo ""
echo "✅ AI Training Interface is running with YOUR REAL DATA!"
echo "======================================================="
echo ""
echo "📊 Database Stats:"
echo "   Total Reminders: $REMINDER_COUNT"
echo ""
echo "📋 Instructions for your friend:"
echo "1. The browser should open automatically"
echo "2. Click 'Load Real Data' to see YOUR actual reminders"
echo "3. Look for yellow cards (AI mistakes)"
echo "4. Fix them by changing the dropdowns"
echo "5. Click 'Save All Fixes & Generate Better AI'"
echo ""
echo "🎯 Key fixes to look for:"
echo "   - Yankees 'starts' vs 'ends' confusion"
echo "   - Stock mentions classified as 'generic'"
echo "   - Weather/temperature not recognized"
echo ""
echo "🛑 To stop: Press Ctrl+C or run ./stop_training.sh"
echo "📊 Server log: tail -f server.log"
echo ""

# Save the PID so we can stop it later
echo $SERVER_PID > .server_pid

# Keep script running
echo "Press Ctrl+C to stop the server..."
wait $SERVER_PID