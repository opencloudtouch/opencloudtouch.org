#!/bin/bash
# Event Archive Rotation Script
# Archives events older than 90 days into dated zip files
# To be run via cron: 0 2 * * * /path/to/rotate-events.sh

EVENTS_DIR="/srv/www/w018fdfd/api/supporters/events"
ARCHIVE_DIR="/srv/www/w018fdfd/api/supporters/archives"
CUTOFF_DATE=$(date -d "90 days ago" +%Y-%m-%d)

# Create archive dir if not exists
mkdir -p "$ARCHIVE_DIR"

# Find events older than 90 days
OLD_EVENTS=$(find "$EVENTS_DIR" -name "*.json" -type f | grep -E "[0-9]{4}-[0-9]{2}-[0-9]{2}" | while read file; do
    # Extract date from filename (format: YYYY-MM-DD_HH-mm-ss_...)
    FILE_DATE=$(basename "$file" | cut -d'_' -f1)
    
    # Compare dates
    if [[ "$FILE_DATE" < "$CUTOFF_DATE" ]]; then
        echo "$file"
    fi
done)

# Count events
EVENT_COUNT=$(echo "$OLD_EVENTS" | grep -c "\.json" || echo 0)

if [ "$EVENT_COUNT" -eq 0 ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') [INFO] No events older than 90 days found."
    exit 0
fi

echo "$(date '+%Y-%m-%d %H:%M:%S') [INFO] Found $EVENT_COUNT events older than 90 days"

# Create archive filename
ARCHIVE_NAME="events-archive-$(date +%Y-%m-%d).zip"
ARCHIVE_PATH="$ARCHIVE_DIR/$ARCHIVE_NAME"

# Create zip archive
cd "$EVENTS_DIR" || exit 1
echo "$OLD_EVENTS" | while read file; do
    zip -q "$ARCHIVE_PATH" "$(basename "$file")"
done

# Verify archive
if [ -f "$ARCHIVE_PATH" ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') [SUCCESS] Created archive: $ARCHIVE_NAME ($EVENT_COUNT events)"
    
    # Delete archived files
    echo "$OLD_EVENTS" | while read file; do
        rm -f "$file"
    done
    
    echo "$(date '+%Y-%m-%d %H:%M:%S') [INFO] Deleted $EVENT_COUNT old event files"
else
    echo "$(date '+%Y-%m-%d %H:%M:%S') [ERROR] Failed to create archive"
    exit 1
fi

# Cleanup: Remove archives older than 1 year
find "$ARCHIVE_DIR" -name "events-archive-*.zip" -type f -mtime +365 -delete
echo "$(date '+%Y-%m-%d %H:%M:%S') [INFO] Cleaned up archives older than 1 year"
