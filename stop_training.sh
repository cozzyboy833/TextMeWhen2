#!/bin/bash
# stop_training.sh

echo "🛑 Stopping AI Training Server..."

if [ -f ".server_pid" ]; then
    kill $(cat .server_pid) 2>/dev/null
    rm .server_pid
    echo "✅ Server stopped!"
else
    pkill -f "php -S localhost:8000" 2>/dev/null
    echo "✅ Server stopped!"
fi

echo ""
echo "💾 Your training data is saved!"
echo "🚀 Start again anytime with: ./start_training.sh"
echo ""