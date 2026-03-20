=== BD Subscription System for WooCommerce ===
Contributors: muahtasimfuad
Tags: woocommerce, subscription, membership, paywall, content protection
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce fixed-duration subscriptions and content locking with expiry handling and subscriber management.

== Description ==

BD Subscription System for WooCommerce is a lightweight subscription-access plugin for WordPress sites that want to sell subscription products through WooCommerce and protect premium content without a heavy membership setup.

It is built for use cases like:
- news and journal websites
- premium blogs
- research and report portals
- subscriber-only content websites

This plugin lets you:
- create WooCommerce products as subscription plans
- assign a plan key and role to the purchased subscription
- grant access automatically after eligible order completion
- create or attach customer accounts for guest buyers
- protect content using post/page lock settings
- automatically expire access after a fixed duration
- manage subscribers from WordPress admin
- show subscription status on the frontend with shortcodes

== Features ==

- WooCommerce subscription product fields
- Fixed-duration access in days
- Automatic subscription creation after valid order
- Guest checkout account creation or user attachment by billing email
- Custom database table for subscriptions
- Role-based and plan-based access checks
- Post and page lock settings
- Automatic expiry handling with fallback validation
- Subscriber admin list with search and filters
- Manual activate / expire controls from admin
- Frontend subscription dashboard shortcodes:
  - `[bdss_my_subscription]`
  - `[bdss_subscription_status]`

== Free Version Scope ==

This free version includes:
- subscription product setup
- fixed-duration subscriptions
- automatic expiry
- basic post/page locking
- subscriber admin list
- guest account creation
- role assignment
- basic frontend subscription status/dashboard shortcodes

== Limitations ==

Please review these limitations before using the plugin on a live website:

- This plugin does not provide recurring billing.
- It works with fixed-duration access only.
- Refund, cancellation, and reversal handling is basic and may still require admin review depending on store workflow.
- It is not a full replacement for advanced membership suites with drip rules, advanced reporting, or complex access logic.
- Advanced rule-based protection, custom locker systems, and premium-only controls are not included in this free version.
- Always test on a staging site before using on production.
- The plugin should not be used as a full-site content locker by default.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin in WordPress
3. Make sure WooCommerce is installed and active
4. Go to a WooCommerce product and enable the subscription fields
5. Set duration, role slug, and plan key for the product
6. Configure plugin settings under **BD Subscriptions > Settings**
7. Use post/page locking as needed

== Usage ==

= Create a subscription product =

Edit a WooCommerce product and configure:
- Enable subscription
- Duration in days
- Granted role
- Plan key

= Protect a post or page =

Open the post or page editor and use the **BD Subscription Lock** meta box to:
- enable lock
- select required plan key
- select required role
- set teaser mode
- set teaser words
- set subscribe URL
- set custom locker message

= Show frontend subscription info =

Use these shortcodes on a page:

`[bdss_my_subscription]`

`[bdss_subscription_status]`

== Frequently Asked Questions ==

= Does this support recurring billing? =

No. This version provides fixed-duration access after eligible WooCommerce orders.

= Does it work with guest checkout? =

Yes. The plugin can create a customer account or attach the order to an existing user by billing email.

= Can I protect individual posts and pages? =

Yes. Each post and page can be locked using the BD Subscription Lock meta box.

= Does the plugin remove access after expiry? =

Yes. The plugin expires access automatically and also includes fallback validation if scheduled expiry is missed.

= Can admins manually expire or reactivate subscriptions? =

Yes. Admins can manage subscription status from the subscriber list page.

= Does this plugin handle all refunds and cancellations automatically? =

Not fully in every store workflow. Admins should still review refund/cancellation edge cases manually.

== Changelog ==

= 1.0.0 =
* Refactored into includes-based structure
* Added frontend subscription dashboard shortcodes
* Improved content protection flow
* Improved admin subscriber management
* Added guest account creation and attachment support
* Added fallback expiry validation
* Added manual activate and expire admin actions
* Updated readme for stable public release

== Upgrade Notice ==

= 1.0.0 =
This release aligns the readme with the current plugin version and documents the current free-version scope and limitations.