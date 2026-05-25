# Pre-Confirm Registration — Question2Answer Plugin

A [Question2Answer](https://www.question2answer.org/) plugin that blocks spam account creation by ensuring **no user ID is ever generated until the registrant confirms their email address**.

Unlike Q2A's built-in email confirmation (which creates the account first and restricts it later), this plugin intercepts registration at the core level — the user record is created, immediately deleted, and only recreated permanently once the confirmation link is clicked.

---

## The Problem

Q2A's default registration flow:

```
User submits form → user ID created → confirmation email sent (optional)
```

Unconfirmed accounts still exist in the database, allowing spam bots to flood your user list even if they never confirm.

## The Solution

This plugin's flow:

```
User submits form → user ID created → immediately deleted → confirmation email sent
                                                                        ↓
                                                        User clicks link in email
                                                                        ↓
                                                        Real user ID created permanently
```

Spam bots that never confirm their email leave zero trace in your database.

---

## How It Works

The plugin registers a Q2A **event module** that hooks into the `u_register` event, which fires inside Q2A's own `qa_create_new_user()` function. This makes it version-safe and independent of which registration page or theme you use.

**Step-by-step flow:**

1. Visitor fills in and submits the standard Q2A registration form.
2. Q2A creates the user and fires the `u_register` event.
3. The plugin reads the bcrypt hash Q2A just stored, then **deletes the user**.
4. Registration details (email, username, password hash, token) are saved to a `qa_pending_users` table.
5. A confirmation email is sent with a secure 64-character token link.
6. The visitor is redirected to `/confirm-pending` (a "check your inbox" page).
7. When the visitor clicks the link → `/verify-email?t=TOKEN`:
   - Token is validated (must not be expired).
   - A real Q2A user is created with the stored credentials.
   - The email is marked confirmed, and the user is logged in automatically.
   - The pending row is deleted.

Pending rows expire after **24 hours** and are purged automatically.

---

## Features

- No user ID is generated for unconfirmed registrants
- Works with Q2A's default registration page and any custom theme
- Admin-created accounts (via Admin panel) are not affected
- Duplicate registration attempts reset the expiry timer
- Tokens are cryptographically secure (`random_bytes(32)`)
- Passwords are never stored in plaintext — only the bcrypt hash
- Expired pending rows are purged automatically on each request
- No core Q2A files are modified

---

## Requirements

- Question2Answer **1.8** or later
- PHP **7.2** or later
- MySQL / MariaDB (InnoDB support)
- Outgoing email configured in Q2A (`Admin → General → From email`)

---

## Installation

**1. Upload the plugin**

Copy the `qa-plugin-preconfirm` folder into your Q2A plugins directory:

```
your-site/
└── qa-plugin/
    └── qa-plugin-preconfirm/       ← upload here
        ├── qa-plugin.php
        ├── qa-preconfirm-db.php
        ├── qa-preconfirm-event.php
        ├── qa-preconfirm-pending.php
        └── qa-preconfirm-verify.php
```

**2. Activate in Q2A Admin**

Go to **Admin → Plugins** and enable *Pre-Confirm Registration*.

**3. Configure email**

Go to **Admin → General** and make sure the **From email** address is set. Without this, confirmation emails will not send.

**4. Recommended: disable Q2A's built-in email confirmation**

Go to **Admin → Users** and turn off *Confirm email addresses*. This plugin fully replaces that feature — having both on is redundant.

The `qa_pending_users` database table is created automatically on first use. No manual SQL import is needed.

---

## Plugin File Structure

| File | Purpose |
|---|---|
| `qa-plugin.php` | Registers the three modules with Q2A |
| `qa-preconfirm-db.php` | Shared database helpers and email sender |
| `qa-preconfirm-event.php` | Event module — core logic, intercepts `u_register` |
| `qa-preconfirm-pending.php` | Page module for `/confirm-pending` (check your inbox) |
| `qa-preconfirm-verify.php` | Page module for `/verify-email?t=TOKEN` (activates account) |

---

## Configuration

All configuration is done by editing the relevant file directly. No admin UI settings panel is provided.

| Setting | File | What to change |
|---|---|---|
| Token expiry (default: 24 h) | `qa-preconfirm-db.php` → `qa_preconfirm_upsert()` | Change `INTERVAL 24 HOUR` |
| Email subject / body | `qa-preconfirm-db.php` → `qa_preconfirm_send_email()` | Edit the `$subject` and `$body` strings |
| "Check your inbox" message | `qa-preconfirm-pending.php` → `process_request()` | Edit the `$qa_content['custom']` HTML |
| Success / error messages | `qa-preconfirm-verify.php` → `msg()` calls | Edit the strings passed to `$this->msg()` |

---

## Troubleshooting

**Confirmation emails are not arriving**

- Verify the *From email* is set in **Admin → General**.
- Check your server's mail logs and the recipient's spam folder.
- Consider a transactional email service (SendGrid, Mailgun, Amazon SES) paired with a Q2A SMTP plugin for reliable delivery.

**User is redirected to the home page instead of `/confirm-pending`**

Q2A's URL rewriting must be enabled. Check that `mod_rewrite` is active and your `.htaccess` is in place.

**"Username already taken" error after clicking the verification link**

The pending row expired AND someone else registered with the same username in the meantime. The visitor needs to register again with a different username.

**Admin-created accounts are also going through verification**

The plugin skips interception when an admin-level session is active. Make sure you are logged in as an admin when creating accounts via the Admin panel.

**The `qa_pending_users` table was not created automatically**

The Q2A database user may lack `CREATE TABLE` permission. You can create it manually using the `CREATE TABLE` statement in `qa-preconfirm-db.php` → `qa_preconfirm_ensure_table()`.

---

## Security Notes

- Tokens are generated with PHP's `random_bytes(32)` (CSPRNG), producing 64 hex characters.
- Passwords are bcrypt-hashed by Q2A before this plugin ever reads them. The plaintext password is never stored or transmitted by this plugin.
- Pending rows are hard-deleted (not soft-deleted) when a token is used or expires.
- Tokens are stored with a `UNIQUE` constraint — replay attacks will fail.

---

## Compatibility

- Tested on Question2Answer 1.8.x
- Does not modify any core Q2A files
- Compatible with all Q2A themes
- Does not interfere with social login plugins (Facebook, Google, etc.) as those bypass the standard registration event

---

## License

Released under the [GNU General Public License v2.0](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html), consistent with Question2Answer's own license.

---

## Contributing

Pull requests and issues are welcome. If you find a bug or have a feature request, please open an issue with your Q2A version and PHP version included.
