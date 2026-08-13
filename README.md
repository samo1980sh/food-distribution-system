# FreshRoute — Food Distribution System

نظام إدارة التوزيع والمبيعات والمخزون والتحصيلات والإقفال اليومي، مبني على Laravel + Filament ومتكامل مع تطبيق Flutter الميداني.

## المكونات الرئيسية

- Laravel 12
- Filament Admin Panel
- MySQL
- Laravel Sanctum / Mobile API v1
- Offline Sync لتطبيق Flutter
- Role-Based Permissions + Data Scopes
- Inventory / FEFO / Moving Weighted Average Cost
- Sales, Payments, Returns, Vehicle Loads, Vehicle Expenses, Daily Closings
- Operational and Management Reports

## التشغيل المحلي

بعد إعداد `.env` المحلي وقاعدة البيانات:

```bash
composer install
php artisan migrate
php artisan db:seed
php artisan optimize:clear
php artisan test
```

لوحة الإدارة:

```text
/admin
```

Mobile API:

```text
/api/v1
```

Health endpoints:

```text
/up
/api/v1/health
```

## الإنتاج

مرجع النشر والتشغيل الكامل:

```text
docs/PRODUCTION_DEPLOYMENT.md
```

يوجد قالب إعدادات إنتاج بدون أسرار في:

```text
.env.production.example
```

لا تنسخ هذا القالب فوق `.env` الموجود في تحديثات الإنتاج، ولا تغيّر `APP_KEY` بعد بدء التشغيل الفعلي.

## الاختبارات والأمان

قبل تثبيت أي Release:

```bash
php artisan test
composer audit
git diff --check
```

يجب أن تكون بيئة الإنتاج دائمًا:

```env
APP_ENV=production
APP_DEBUG=false
```
