# Role-Based Permissions System

## Overview

The Techno Server Dashboard implements a comprehensive role-based access control (RBAC) system with three-layer protection:

1. **Backend API Guards** — PHP permission checks in every API endpoint
2. **Frontend Route Protection** — React route guards redirect unauthorized users
3. **Conditional UI Rendering** — Buttons and menu items hidden based on permissions

```
┌─────────────────────────────────────────────────────────┐
│                    USER REQUEST                         │
├─────────────────────────────────────────────────────────┤
│  Layer 1: Frontend Route Guard (ProtectedRoute)         │
│  → Redirects to / if role doesn't match requiredRole    │
├─────────────────────────────────────────────────────────┤
│  Layer 2: Conditional UI (PermissionGate / hasPermission)│
│  → Hides buttons, links, menu items user can't use      │
├─────────────────────────────────────────────────────────┤
│  Layer 3: Backend API Guards (PermissionChecker)         │
│  → Returns 403 if permission not granted                │
└─────────────────────────────────────────────────────────┘
```

## Roles

| Role | Description | Intended Use |
|------|-------------|-------------|
| **admin** | Full access to all pages and permissions | System administrators managing infrastructure, users, and settings |
| **editor** | Can create/update own tasks and notes, no admin pages | Content creators and task contributors |
| **moderator** | Can update any task, delete notes, access some admin pages | Senior team members who manage tasks and content |
| **viewer** | Can view and comment on tasks, minimal permissions | Stakeholders who need visibility but limited editing |

## Permission Flags

### Page Access Permissions

| Permission | Admin | Editor | Moderator | Viewer | Description | Enforced In |
|---|---|---|---|---|---|---|
| `can_access_users_page` | 1 | 0 | 0 | 0 | Access to User Management page | App.tsx route guard |
| `can_access_settings_page` | 1 | 0 | 0 | 0 | Access to Dashboard Settings | App.tsx route guard |
| `can_access_emergency_actions` | 1 | 0 | 0 | 0 | Access to Emergency Actions | App.tsx route guard |
| `can_access_cache_control` | 1 | 0 | 0 | 0 | Access to Cache Control page | App.tsx route guard |
| `can_access_process_explorer` | 1 | 0 | 1 | 0 | Access to Process Explorer | App.tsx route guard |
| `can_access_permissions_page` | 1 | 0 | 0 | 0 | Access to Permissions Matrix | App.tsx route guard |

### Task Permissions

| Permission | Admin | Editor | Moderator | Viewer | Description | Enforced In |
|---|---|---|---|---|---|---|
| `can_create_tasks` | 1 | 1 | 1 | 1 | Create new tasks | `api/tasks.php` (create action) |
| `can_update_own_tasks` | 1 | 1 | 1 | 1 | Update tasks the user created | `api/tasks.php` (update action) |
| `can_update_any_task` | 1 | 0 | 1 | 0 | Update any task regardless of owner | `api/tasks.php` (update action) |
| `can_delete_tasks` | 1 | 0 | 0 | 0 | Delete tasks | `api/tasks.php` (delete action) |

### Note Permissions

| Permission | Admin | Editor | Moderator | Viewer | Description | Enforced In |
|---|---|---|---|---|---|---|
| `can_edit_own_notes` | 1 | 1 | 1 | 1 | Edit notes the user authored | `api/tasks.php` (edit_note action) |
| `can_edit_any_note` | 1 | 0 | 0 | 0 | Edit any note regardless of author | `api/tasks.php` (edit_note action) |
| `can_delete_own_notes` | 1 | 1 | 1 | 1 | Delete notes the user authored | `api/tasks.php` (delete_note action) |
| `can_delete_any_note` | 1 | 0 | 1 | 0 | Delete any note regardless of author | `api/tasks.php` (delete_note action) |
| `can_pin_notes` | 1 | 1 | 1 | 1 | Pin/unpin notes | `api/tasks.php` (pin_note action) |

### Administration Permissions

| Permission | Admin | Editor | Moderator | Viewer | Description | Enforced In |
|---|---|---|---|---|---|---|
| `can_manage_users` | 1 | 0 | 0 | 0 | Create, edit, delete users | `api/users.php` (all actions) |

## Ownership Model

### Tasks
- **Owner** = `created_by` field (username of the user who created the task)
- To **update** a task: must be owner AND have `can_update_own_tasks`, OR have `can_update_any_task`
- To **delete** a task: must have `can_delete_tasks`

### Notes
- **Author** = `author` field (username of the user who wrote the note)
- To **edit** a note: must be author AND have `can_edit_own_notes`, OR have `can_edit_any_note`
- To **delete** a note: must be author AND have `can_delete_own_notes`, OR have `can_delete_any_note`

## Architecture

### Key Files

```
api/
├── PermissionChecker.php      # Core PHP permission class (static caching)
├── permissions.php            # Admin-only API endpoint for permission matrix
├── tasks.php                  # Task/note API with permission guards
├── users.php                  # User management API (admin-only)
└── webpushr.php               # Push notification API (some admin actions)

dashboard/src/
├── config/routes.ts           # Shared ADMIN_PATHS constant (single source of truth)
├── api/permissions.ts         # Frontend API client for permissions
├── hooks/usePermissions.ts    # React hook with request deduplication
├── components/
│   ├── ProtectedRoute.tsx     # Route guard with requiredRole prop
│   └── common/PermissionGate.tsx # Wrapper for conditional rendering
└── pages/
    ├── PermissionsPage.tsx    # Admin-only matrix UI with toggle switches
    ├── TasksPage.tsx          # Conditional button rendering
    └── TaskDetailPage.tsx     # Conditional note/task action rendering
```

### Data Flow

```
Login → Auth API returns user.role
     → useAuth() stores user context
     → usePermissions() fetches role permissions (cached 30s, deduplicated)
     → ProtectedRoute checks user.role against requiredRole
     → Components call hasPermission() to show/hide UI elements
     → API calls hit PermissionChecker backend guards
```

## API Endpoints

### `GET /api/permissions.php?action=get_all`
Returns all role permissions for the matrix display. **Admin only.**

**Response:**
```json
{
  "admin": { "role": "admin", "can_create_tasks": 1, ... },
  "editor": { "role": "editor", "can_create_tasks": 1, ... },
  ...
}
```

### `GET /api/permissions.php?action=get_role&role=editor`
Returns permissions for a specific role.

**Response:**
```json
{ "role": "editor", "can_create_tasks": 1, "can_delete_tasks": 0, ... }
```

### `POST /api/permissions.php?action=update`
Update a single permission. **Admin only.** Logs changes to audit_log.

**Body:**
```json
{ "role": "editor", "permission": "can_delete_tasks", "value": true }
```

### `GET /api/permissions.php?action=roles`
Returns available role names.

**Response:**
```json
["admin", "editor", "moderator", "viewer"]
```

## How to Add a New Permission

1. **Add column to `role_permissions` table:**
   ```sql
   ALTER TABLE role_permissions ADD COLUMN can_xyz TINYINT(1) DEFAULT 0 AFTER can_manage_users;
   ```

2. **Add to whitelist in `api/PermissionChecker.php`:**
   Add the column name to `VALID_PERMISSIONS` constant.

3. **Add to TypeScript interface in `dashboard/src/api/permissions.ts`:**
   Add the field to `RolePermissions` interface.

4. **Add backend guard in the relevant API endpoint:**
   ```php
   if (!PermissionChecker::hasPermission('can_xyz')) {
       http_response_code(403);
       echo json_encode(['error' => 'Permission denied']);
       break;
   }
   ```

5. **Add to default role seeds** (if needed):
   ```sql
   UPDATE role_permissions SET can_xyz = 1 WHERE role IN ('admin', 'moderator');
   ```

6. **Add to PermissionsPage.tsx** matrix UI (in the appropriate group section).

## How to Add a New Role

1. **Add to users table ENUM:**
   ```sql
   ALTER TABLE users MODIFY COLUMN role ENUM('admin','editor','moderator','viewer','custom') NOT NULL DEFAULT 'viewer';
   ```

2. **Add to role_permissions table ENUM:**
   ```sql
   ALTER TABLE role_permissions MODIFY COLUMN role ENUM('admin','editor','moderator','viewer','custom') NOT NULL UNIQUE;
   ```

3. **Add to `PermissionChecker::VALID_PERMISSIONS`** — no changes needed (role-agnostic).

4. **Add to `PermissionChecker::getAvailableRoles()`** return array.

5. **Add to `users.php`** role validation array.

6. **Add to frontend types** in `useAuth.tsx` and `users.ts`:
   ```typescript
   export type UserRole = 'admin' | 'editor' | 'moderator' | 'viewer' | 'custom';
   ```

7. **Seed default permissions:**
   ```sql
   INSERT INTO role_permissions (role, can_create_tasks, ...) VALUES ('custom', 1, ...);
   ```

## Security Notes

- **SQL Injection Prevention**: Permission column names are validated against a whitelist constant, never interpolated directly into SQL queries.
- **Static Caching**: `PermissionChecker` caches role permissions per-request to avoid redundant DB queries.
- **Request Deduplication**: `usePermissions` hook deduplicates concurrent API calls and caches results for 30 seconds.
- **Audit Trail**: All permission changes are logged to `audit_log` with user ID, IP, user agent, and old/new values.
