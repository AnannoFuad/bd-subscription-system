# Installation Guide

## Requirements
- WordPress 6.0 or higher
- WooCommerce installed and activated
- PHP 7.4 or higher

## Installation Steps
1. Download or clone this repository.
2. Upload the plugin folder to `/wp-content/plugins/`.
3. Go to **WordPress Admin > Plugins**.
4. Activate **BD Simple Subscription**.
5. Make sure WooCommerce is active.
6. Open the plugin settings page and configure:
   - subscription duration
   - protected content behavior
   - user access settings
7. Create or connect WooCommerce products for subscription purchase.
8. Test a full purchase flow using a test user account.

## Recommended Setup
- Create a subscription status page
- Create a my account / subscription page
- Configure content protection rules before publishing protected posts

## Notes
- Keep both `README.md` for GitHub and `readme.txt` for WordPress plugin packaging.
- Do not upload private credentials, live payment keys, or client-specific data to this repository.
