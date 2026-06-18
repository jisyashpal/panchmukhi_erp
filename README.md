# Panchmukhi ERP

Laravel 12 ERP for construction/site inventory management (Head Office + site roles).

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
| admin@panchmukhi.test | Super Admin | password |
| site@panchmukhi.test | Site In-Charge | password |
| engineer@panchmukhi.test | Site Engineer | password |
| store@panchmukhi.test | Site Store In-Charge | password |

## Workflow

1. **Engineer** → Product Request → **Site In-Charge** approves → optional auto Purchase Request to HO
2. **Store** → Purchase Request → **Site In-Charge** approves → **HO** approves → Purchase Order (with price + GST)
3. **Store / In-Charge** receives PO at site → stock in
4. **Site In-Charge** creates plant users → **HO** approves before login

## Portals

- Super Admin: `/admin`
- Site users: `/site`
- Profile: `/site/profile` (site), `/admin/profile` (HO)
- Notifications: bell in topbar + `/site/notifications` or `/admin/notifications`

## Tests

```bash
php artisan test
```

## License

MIT
