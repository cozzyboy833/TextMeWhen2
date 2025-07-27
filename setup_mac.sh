#!/bin/bash
# setup_mac.sh - Super simple setup for Mac users

echo "🤖 TextMeWhen AI Training Setup for Mac"
echo "======================================="
echo ""
echo "Setting up AI training environment..."
echo ""

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "❌ PHP not found. Installing PHP..."
    
    # Check if Homebrew is installed
    if ! command -v brew &> /dev/null; then
        echo "📦 Installing Homebrew first..."
        /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
    fi
    
    echo "📦 Installing PHP..."
    brew install php
    echo "✅ PHP installed!"
else
    echo "✅ PHP found: $(php -v | head -n1)"
fi

# Create database if it doesn't exist
if [ ! -f "textmewhen.db" ]; then
    echo "📊 Setting up database..."
    php setup_database.php
    echo "✅ Database created!"
else
    echo "✅ Database found"
fi

# Generate initial enhanced parser if it doesn't exist
if [ ! -f "enhanced_ai_parser.php" ]; then
    echo "🧠 Creating initial AI parser..."
    php quick_generate_parser.php
    echo "✅ AI parser ready!"
fi

echo ""
echo "🎉 Setup Complete!"
echo "=================="
echo ""
echo "📋 What's Next:"
echo "1. Run: ./start_training.sh"
echo "2. Open the web browser when it opens automatically"
echo "3. Start training the AI!"
echo ""
echo "💡 Need help? Run: ./help.sh"
echo ""