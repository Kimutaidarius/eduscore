#!/bin/bash

# ============================================
# Cron Wrapper Script for PayHero Transaction Checker
# ============================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_FILE="$SCRIPT_DIR/cron_log.txt"

echo "$(date): Running transaction status check..." >> $LOG_FILE

# Run the PHP script
php "$SCRIPT_DIR/check_pending_transactions.php" >> $LOG_FILE 2>&1

# Check exit code
if [ $? -eq 0 ]; then
    echo "$(date): Cron completed successfully" >> $LOG_FILE
else
    echo "$(date): Cron failed with error" >> $LOG_FILE
fi

echo "----------------------------------------" >> $LOG_FILE