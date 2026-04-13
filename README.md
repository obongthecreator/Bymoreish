# Bymoreish Inventory Management

A WordPress plugin for restaurant inventory management.

## Installation

1. Download `bymoreish-inventory.zip` from this repository
2. Go to **WordPress Dashboard → Plugins → Add New → Upload Plugin**
3. Upload the zip file, click **Install Now**, then **Activate**
4. After activation, visit **Settings → Permalinks** and click **Save Changes** (this flushes rewrite rules)

## Page URLs

All pages are served under the `/bymoreish/` path on your WordPress site.
Replace `https://yoursite.com` with your actual WordPress site URL.

### Public Pages (no login required)

| Page              | URL                                    | Description                  |
|-------------------|----------------------------------------|------------------------------|
| Branch Selection  | `https://yoursite.com/bymoreish/`      | Select active branch         |
| Login             | `https://yoursite.com/bymoreish/login` | Staff login                  |

### Protected Pages (login required)

| Page              | URL                                          | Description                        |
|-------------------|----------------------------------------------|------------------------------------|
| Home / Dashboard  | `https://yoursite.com/bymoreish/home`        | Dashboard with daily KPIs          |
| Orders            | `https://yoursite.com/bymoreish/orders`      | Create and manage orders           |
| Stock             | `https://yoursite.com/bymoreish/stock`       | Daily stock intake                 |
| Financial         | `https://yoursite.com/bymoreish/financial`   | Financial summary and records      |
| Expenses          | `https://yoursite.com/bymoreish/expenses`    | Record and manage expenses         |
| Analytics         | `https://yoursite.com/bymoreish/analytics`   | Charts and business analytics      |
| Products          | `https://yoursite.com/bymoreish/products`    | Manage menu items and products     |
| Profile           | `https://yoursite.com/bymoreish/profile`     | User profile and password change   |

### Admin Pages (admin or superadmin role required)

| Page              | URL                                      | Description                        |
|-------------------|------------------------------------------|------------------------------------|
| Admin Panel       | `https://yoursite.com/bymoreish/admin`   | User management, branches, settings|

### History Pages (login required)

| Page               | URL                                                | Description               |
|--------------------|----------------------------------------------------|---------------------------|
| Order History      | `https://yoursite.com/bymoreish/history/orders`    | Past orders log           |
| Stock History      | `https://yoursite.com/bymoreish/history/stock`     | Past stock intake log     |
| Expense History    | `https://yoursite.com/bymoreish/history/expenses`  | Past expenses log         |
| Financial History  | `https://yoursite.com/bymoreish/history/financial` | Past financial records    |

### Logout

To log out, visit:
```
https://yoursite.com/bymoreish/login?action=logout&_bym_nonce=YOUR_SESSION_NONCE
```
The logout link is available in the sidebar navigation.

## Troubleshooting

- **404 on page URLs?** Go to **Settings → Permalinks** in WordPress and click **Save Changes** to flush rewrite rules.
- **Pages require pretty permalinks** — the plugin uses WordPress rewrite rules, so make sure your permalink structure is **not** set to "Plain".