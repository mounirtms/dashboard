# System Configuration Documentation

## Dashboard
- **URL**: https://dashboard.technostationery.com
- **Location**: `/home/dashboard/public_html`
- **Credentials**: admin / admin123

### API Endpoints
- `/api/monitor.php` - Server monitoring 
- `/api/dashboard.php` - Dashboard data
- `/api/auth.php` - Authentication

### Key Fix Applied
Added inline `fetch()` monkey-patch in `index.html` to handle PHP shebang in API responses:
```javascript
// Lines 544-559 in index.html
<script>
(function() {
  const orig = window.fetch;
  window.fetch = function(...args) {
    return orig.apply(this, args).then(r => {
      const oldJson = r.json;
      r.json = function() {
        return r.text().then(t => {
          const jsonStr = t.replace(/^[^{]*/, '').trim();
          return JSON.parse(jsonStr);
        });
      };
      return r;
    });
  };
})();
</script>
```

## Akeneo PIM
- **URL**: https://pim.technostationery.com
- **Location**: `/home/pim/public_html` (serving via cPanel)
- **Note**: Login has encoder/hasher mismatch issue (bcrypt vs sha512 in Symfony 6+)

### Known Issues
1. **PIM Login**: Password encoding mismatch between bcrypt (legacy) vs sha512 (Symfony security config)
   - The pim:user:create command creates password in bcrypt format
   - But security.yml is configured for sha512 encoder
   - Need to match both configurations for login to work

### Database Configuration
- MySQL Socket: `/opt/mariadb10.6/mariadb.sock`
- MySQL Port: 3307
- Database: `akeneo_pim`

### Security Config
- Config: `config/packages/security.yml`
- Uses sha512 encoder for `Akeneo\UserManagement\Component\Model\User`

## Common Commands

### Clear Cache
```bash
# Dashboard
php /home/dashboard/public_html/bin/console cache:clear --env=prod

# Akeneo PIM
php /home/pim/akeneopublic_html/bin/console cache:clear --env=prod
```

### Create User
```bash
# Akeneo PIM
php /home/pim/akeneopublic_html/bin/console pim:user:create <username> <password> <email> <first> <last> en_US --admin -n
```

### Check Routes
```bash
php bin/console debug:router
php bin/console debug:config framework security
```

### Check Services
```bash
php bin/console debug:container --env=prod | grep password
```

## File Locations
- Dashboard JS: `/home/dashboard/public_html/assets/dashboard.js`
- Dashboard Login: `/home/dashboard/public_html/login.html`
- PIM Security: `/home/pim/akeneopublic_html/config/packages/security.yml`
- PIM .env: `/home/pim/akeneopublic_html/.env`