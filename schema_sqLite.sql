-- SQLite Schema for TextMeWhen System

-- Reminders table
CREATE TABLE IF NOT EXISTS reminders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL,
    phone TEXT,
    original_text TEXT NOT NULL,
    parsed_data TEXT, -- SQLite doesn't have JSON type, we'll store as TEXT
    status TEXT DEFAULT 'active' CHECK(status IN ('active', 'completed', 'failed', 'cancelled')),
    created_at TEXT DEFAULT (datetime('now')),
    completed_at TEXT,
    last_checked TEXT,
    check_frequency INTEGER DEFAULT 300, -- seconds between checks
    max_checks INTEGER DEFAULT 2880, -- max checks before giving up
    current_checks INTEGER DEFAULT 0
);

-- Notification log table
CREATE TABLE IF NOT EXISTS notification_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    reminder_id INTEGER,
    type TEXT NOT NULL CHECK(type IN ('email', 'sms')),
    recipient TEXT NOT NULL,
    subject TEXT,
    message TEXT,
    sent_at TEXT DEFAULT (datetime('now')),
    status TEXT DEFAULT 'sent' CHECK(status IN ('sent', 'failed')),
    FOREIGN KEY (reminder_id) REFERENCES reminders(id)
);

-- Search results cache
CREATE TABLE IF NOT EXISTS search_cache (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    search_query TEXT NOT NULL UNIQUE,
    results TEXT,
    cached_at TEXT DEFAULT (datetime('now')),
    expires_at TEXT
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_reminders_status ON reminders(status);
CREATE INDEX IF NOT EXISTS idx_reminders_last_checked ON reminders(last_checked);
CREATE INDEX IF NOT EXISTS idx_search_cache_expires ON search_cache(expires_at);