# Panchmukhi ERP

Laravel 12 ERP for construction/plant inventory management (Head Office + plant roles).

## Requirements

- PHP 8.2+
- Composer

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

The UI uses **Bootstrap 5** and **Bootstrap Icons** loaded from CDN.

## Demo logins

| Email | Role | Password |
|-------|------|----------|
| admin@gmail.com | Super Admin | 12345678 |
| ho@gmail.com | HO Data Operator | 12345678 |
| plant@gmail.com | Plant In-Charge | 12345678 |
| purchaser@gmail.com | Purchaser | 12345678 |
| plant2@gmail.com | Branch Plant In-Charge | 12345678 |

Additional seeded users for approval testing: `pending@gmail.com`, `rejected@gmail.com` (same password).

## Workflow

1. **Plant In-Charge** → Product Request → HO / purchaser notified
2. **Purchaser** → Purchase Request → **Plant In-Charge** approves → **HO** approves → Purchase Order (with price + GST)
3. **Plant In-Charge / Purchaser** receives PO at plant → stock in
4. **Super Admin** manages plants and users; plant users require approval before login

## Portals

- Super Admin: `/admin`
- Head Office: `/ho`
- Plant users: `/plant`
- Profile: `/plant/profile` (plant), `/ho/profile` (HO), `/admin/profile` (admin)
- Notifications: bell in topbar + `/plant/notifications`, `/ho/notifications`, or `/admin/notifications`

## Tests

```bash
php artisan test
```

## License

MIT
