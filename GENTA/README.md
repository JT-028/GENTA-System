# GENTA Web Portal

CakePHP 4.6 teacher application: accounts, students, item bank, quiz versions, MELCs, and fetch of IoT-generated analysis / tailored modules.

This folder is the **web** side of the [GENTA System](../README.md) monorepo.

## Stack

- PHP ~8.3, CakePHP 4.6  
- `cakephp/authentication`, `cakephp/authorization`, `cakephp/migrations`  
- MySQL/MariaDB (`utf8mb4`)  
- SMTP (Gmail app password or institutional SMTP)

## Setup

```bash
composer install
cp .env.example .env
cp config/app_local.example.php config/app_local.php
mysql -u USER -p genta < config/schema/init_scalingo.sql
bin/cake server -p 8765
```

Point `NGROK_BASE_URL` / `NGROK_API_KEY` at the Flask hub when you want the dashboard to pull reports.

## Layout

| Path | Role |
| --- | --- |
| `src/Controller/UsersController.php` | Login, register, verify, reset, lockout |
| `src/Controller/Component/SecurityComponent.php` | Rate limit, session idle/absolute timeout |
| `src/Controller/Component/CaptchaComponent.php` | Math CAPTCHA after failed logins |
| `src/Controller/Teacher/` | Dashboard, students, questions, MELCs |
| `templates/` | PHP views (guest + teacher layouts) |
| `webroot/assets/` | CSS/JS, Shepherd.js tour, mascot |
| `tests/` | PHPUnit fixtures + TestCase |

Do not commit `config/app_local.php`, `vendor/`, `logs/`, or profile uploads.
