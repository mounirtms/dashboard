# Email Notification System - Task Management

## Overview
The email notification system has been extended to handle task management events and administrative reports. All notifications use the existing `Mailer.php` infrastructure with PHP's `mail()` function.

## Admin Email Addresses
- `admin@dashboard.technostationery.com`
- `webmaster@techno-dz.com`

Both addresses receive administrative notifications for major events.

## Notification Types

### 1. Task Creation Notifications
**Triggered when:** A new task is created via the `create` action

**Recipients:**
- **Assigned User**: Receives `sendTaskAssignment()` notification
- **Task Creator**: Receives `sendTaskCreatedNotification()` if different from assignee
- **Admins**: Receive `sendAdminNotification()` for high-priority tasks only

**Email Methods Used:**
- `Mailer::sendTaskAssignment($to, $assigneeName, $taskTitle, $taskDescription, $priority, $assignedBy, $dueDate, $taskId)`
- `Mailer::sendTaskCreatedNotification($to, $assigneeName, $taskTitle, $taskDescription, $priority, $createdBy, $assignedTo, $dueDate, $taskId)`
- `Mailer::sendAdminNotification($subject, $title, $content)`

### 2. Task Status Change Notifications
**Triggered when:** A task's status is updated via the `update` action

**Recipients:**
- **Assigned User**: Receives `sendTaskStatusChange()` for any status change
- **Assigned User**: Additionally receives `sendTaskCompleted()` when status changes to 'completed'
- **Admins**: Receive `sendAdminNotification()` when task is completed

**Email Methods Used:**
- `Mailer::sendTaskStatusChange($to, $assigneeName, $taskTitle, $oldStatus, $newStatus, $changedBy, $taskId)`
- `Mailer::sendTaskCompleted($to, $assigneeName, $taskTitle, $completedBy, $taskId)`
- `Mailer::sendAdminNotification($subject, $title, $content)`

### 3. Task Note Notifications
**Triggered when:** A note is added to a task via the `add_note` action

**Recipients:**
- **Assigned User**: Receives `sendTaskNoteAdded()` if different from note author

**Email Methods Used:**
- `Mailer::sendTaskNoteAdded($to, $assigneeName, $taskTitle, $noteContent, $addedBy, $taskId)`

### 4. Bulk Task Update Notifications
**Triggered when:** Multiple tasks are updated via the `bulk_update` action

**Recipients:**
- **Admins**: Receive `sendAdminNotification()` when tasks are bulk-completed

**Email Methods Used:**
- `Mailer::sendAdminNotification($subject, $title, $content)`

### 5. System Reports
**Available for:** Manual triggering from system monitors or cron jobs

**Recipients:**
- **Admins**: Both admin addresses receive the report

**Email Methods Used:**
- `Mailer::sendSystemReport($reportTitle, $reportContent, $reportType)`

### 6. Major Update Notifications
**Available for:** System updates, deployment notifications, etc.

**Recipients:**
- **Admins**: Both admin addresses receive the notification

**Email Methods Used:**
- `Mailer::sendMajorUpdateNotification($updateTitle, $updateDetails, $timestamp)`

## New Mailer Methods Added

### Admin Notifications
```php
Mailer::sendAdminNotification($subject, $title, $content)
```
Sends email to both admin addresses. Returns array of results keyed by email.

```php
Mailer::sendSystemReport($reportTitle, $reportContent, $reportType = 'System Report')
```
Sends formatted system report to admins with timestamp and hostname.

```php
Mailer::sendMajorUpdateNotification($updateTitle, $updateDetails, $timestamp = null)
```
Sends major update alert to admins with formatted details.

### Task Notifications
```php
Mailer::sendTaskCreatedNotification($to, $assigneeName, $taskTitle, $taskDescription, $priority, $createdBy, $assignedTo, $dueDate = null, $taskId = null)
```
Notifies user about new task creation (separate from assignment).

```php
Mailer::sendTaskNoteAdded($to, $assigneeName, $taskTitle, $noteContent, $addedBy, $taskId = null)
```
Notifies assignee when a note is added to their task.

```php
Mailer::sendTaskCompleted($to, $assigneeName, $taskTitle, $completedBy, $taskId = null)
```
Congratulatory notification when task is marked as completed.

## Error Handling
All email sending is wrapped in try-catch blocks to prevent email failures from breaking the API. Errors are logged to PHP's error log with the format:
```
[tasks.php] Failed to send <type> email: <error message>
```

## Integration Points
All email notifications are integrated into `/home/dashboard/public_html/api/tasks.php`:
- Task creation (`create` action)
- Task updates (`update` action) 
- Note addition (`add_note` action)
- Bulk updates (`bulk_update` action)

## Testing
To test email notifications:
1. Create a task with an assigned user who has a valid email
2. Update a task's status to 'completed'
3. Add a note to a task assigned to another user
4. Bulk update multiple tasks to 'completed' status
5. Check PHP error logs for any email sending failures

## Future Enhancements
- Email notification preferences per user
- Notification throttling to prevent email spam
- Email notification logging in database
- HTML email templates with better styling
- Attachment support for reports
