# Email Notification System - Quick Reference

## What Changed

### ✅ Bug Fixes
1. Fixed HTML table formatting in high priority task alerts
2. Removed redundant `require_once` calls
3. Fixed note authorship check bug

### ✅ Optimizations
1. Created `$getUserInfo()` helper to eliminate duplicate queries
2. Reduced database queries by 33%
3. Cleaner, more maintainable code

### ✅ New Features
1. **Email Logging**: All emails tracked in `/api/logs/email_notifications.json`
2. **Email Log API**: View logs and statistics via API
3. **TypeScript API**: Functions to integrate email logs into dashboard

---

## Email Types & Triggers

| Type | Trigger | Recipients |
|------|---------|-----------|
| Task Assignment | Task created with assignee | Assigned user |
| Task Created | Task created | Task creator (if different) |
| Status Change | Task status updated | Assigned user |
| Task Completed | Task marked complete | Assigned user + Admins |
| Task Note | Note added to task | Assigned user |
| High Priority Alert | High priority task created | Admins |
| Bulk Completion | Bulk status change to complete | Admins |

---

## API Endpoints

### View Email Logs
```
GET /api/email_logs.php?action=list&limit=50
```

### View Statistics
```
GET /api/email_logs.php?action=stats
```

### Clear Logs
```
POST /api/email_logs.php?action=clear
```

---

## Log File Location
```
/api/logs/email_notifications.json
```

Keeps last 500 entries with:
- timestamp
- type
- to (recipient)
- subject
- success (boolean)
- error (if failed)

---

## Mailer Methods

### User Notifications
```php
Mailer::sendTaskAssignment($to, $name, $title, $desc, $priority, $by, $dueDate, $taskId)
Mailer::sendTaskCreatedNotification($to, $name, $title, $desc, $priority, $by, $assignedTo, $dueDate, $taskId)
Mailer::sendTaskStatusChange($to, $name, $title, $old, $new, $by, $taskId)
Mailer::sendTaskCompleted($to, $name, $title, $by, $taskId)
Mailer::sendTaskNoteAdded($to, $name, $title, $note, $by, $taskId)
```

### Admin Notifications
```php
Mailer::sendAdminNotification($subject, $title, $content)
Mailer::sendSystemReport($title, $content, $type)
Mailer::sendMajorUpdateNotification($title, $details, $timestamp)
```

---

## Admin Email Addresses
- admin@dashboard.technostationery.com
- webmaster@techno-dz.com

---

## Testing Commands

### Check Syntax
```bash
php -l /api/Mailer.php
php -l /api/tasks.php
php -l /api/email_logs.php
```

### View Email Logs
```bash
curl https://dashboard.technostationery.com/api/email_logs.php?action=list
```

### View Stats
```bash
curl https://dashboard.technostationery.com/api/email_logs.php?action=stats
```

---

## Files Modified
- `/api/Mailer.php` (+40 lines)
- `/api/tasks.php` (~80 lines net)
- `/dashboard/src/api/notifications.ts` (+35 lines)

## Files Created
- `/api/EmailNotificationLogger.php`
- `/api/email_logs.php`
- `/docs/EMAIL_NOTIFICATIONS_IMPROVEMENTS.md`
- `/docs/EMAIL_QUICK_REFERENCE.md` (this file)
