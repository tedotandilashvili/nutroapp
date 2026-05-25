# NutroApp — Setup Guide

## Requirements
- PHP 5.6+
- MySQL 5.6+
- Apache/Nginx with mod_rewrite
- cURL enabled in PHP

## Installation

### 1. Copy files
Place the `nutroapp/` folder in your web root:
```
/var/www/html/nutroapp/   (Apache)
/usr/share/nginx/html/nutroapp/  (Nginx)
```

### 2. Create the database
```sql
mysql -u root -p < nutroapp.sql
```
Or import `nutroapp.sql` via phpMyAdmin.

### 3. Configure database + API key
Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'nutroapp');
define('DB_USER', 'your_mysql_user');
define('DB_PASS', 'your_mysql_password');
define('ANTHROPIC_API_KEY', 'sk-ant-...');  // Your Anthropic API key
```

### 4. Set permissions
```bash
chmod 755 nutroapp/
chmod -R 644 nutroapp/*.php
```

### 5. Visit the app
```
http://localhost/nutroapp/
```

---

## File Structure
```
nutroapp/
├── index.php          # Landing page
├── login.php          # Login
├── register.php       # Registration
├── dashboard.php      # User dashboard
├── generate.php       # Generate new plan (UI)
├── plan.php           # View a saved plan
├── history.php        # All plans list
├── profile.php        # User profile form
├── logout.php         # Logout
├── nutroapp.sql       # Database schema
│
├── config/
│   └── database.php   # DB + API key config
│
├── includes/
│   ├── auth.php       # Session, auth helpers
│   ├── claude.php     # Anthropic API caller + DB save
│   └── layout.php     # HTML header/footer
│
├── assets/
│   └── css/
│       └── main.css   # All styles
│
└── api/
    ├── generate.php   # AJAX endpoint for generate.php
    ├── delete_plan.php # Delete a plan
    └── v1.php         # REST API for future mobile apps
```

---

## REST API (for Android/iOS)

Base URL: `http://yoursite.com/nutroapp/api/v1.php`

| Method | Action | Description |
|--------|--------|-------------|
| POST | `?action=register` | Create account |
| POST | `?action=login` | Login |
| GET  | `?action=profile` | Get user profile |
| POST | `?action=profile` | Save user profile |
| POST | `?action=generate` | Generate diet plan |
| GET  | `?action=plans` | List all plans |
| GET  | `?action=plan&id=N` | Get single plan |
| POST | `?action=delete_plan` | Delete a plan |

### Example: Generate plan (mobile)
```json
POST /nutroapp/api/v1.php?action=generate
{ "days": 5 }
```

### Mobile upgrade path
When moving to Android/iOS:
1. Replace session auth with JWT tokens
2. Add `Authorization: Bearer <token>` header support to `v1.php`
3. All business logic is already in `includes/claude.php` — reuse as-is

---

## Notes
- Passwords hashed with bcrypt (password_hash)
- All user input sanitized via PDO prepared statements
- Georgian language UI throughout
- AI generates Georgian-language meal names and uses local Georgian foods
