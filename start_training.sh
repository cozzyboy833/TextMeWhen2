#!/bin/bash
# start_training.sh - Start the AI training interface

echo "🚀 Starting AI Training Interface..."
echo "===================================="
echo ""

# Check if everything is set up
if [ ! -f "textmewhen.db" ]; then
    echo "❌ Database not found. Run ./setup_mac.sh first!"
    exit 1
fi

# Kill any existing PHP server
pkill -f "php -S localhost:8000" 2>/dev/null

# Start PHP server in background
echo "🌐 Starting web server on http://localhost:8000"
php -S localhost:8000 > server.log 2>&1 &
SERVER_PID=$!

# Wait a moment for server to start
sleep 2

# Try to open browser
if command -v open &> /dev/null; then
    echo "🖥️  Opening training interface in browser..."
    open http://localhost:8000/simple_data_editor.html
elif command -v xdg-open &> /dev/null; then
    xdg-open http://localhost:8000/simple_data_editor.html
else
    echo "📖 Manual step: Open http://localhost:8000/simple_data_editor.html in your browser"
fi

echo ""
echo "✅ AI Training Interface is running!"
echo "=================================="
echo ""
echo "📋 Instructions for your friend:"
echo "1. The browser should open automatically"
echo "2. Click 'Load Reminder Data' to see all the AI's guesses"
echo "3. Look for wrong classifications (red/yellow cards)"
echo "4. Fix them by changing the dropdowns and text fields"
echo "5. Click 'Apply Correction' for each fix"
echo "6. When done, click 'Save All Changes & Retrain AI'"
echo ""
echo "🛑 To stop: Press Ctrl+C or run ./stop_training.sh"
echo "📊 Server log: tail -f server.log"
echo ""

# Save the PID so we can stop it later
echo $SERVER_PID > .server_pid

# Keep script running
echo "Press Ctrl+C to stop the server..."
wait $SERVER_PID