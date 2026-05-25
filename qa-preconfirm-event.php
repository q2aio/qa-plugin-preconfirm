<?php
/**
 * qa-preconfirm-event.php
 *
 * Event module that intercepts the 'u_register' event, which Q2A fires
 * from INSIDE qa_create_new_user() — before the register page has a chance
 * to log the user in or redirect anywhere.
 *
 * What happens on every new registration:
 *   1. Q2A creates the user normally (userid generated).
 *   2. This event fires.
 *   3. We read the already-hashed password straight from qa_users.
 *   4. We save the pending row and delete the Q2A user.
 *   5. We email the confirmation link.
 *   6. We redirect to /confirm-pending and call exit.
 *      → Q2A's register page never reaches qa_set_logged_in_user().
 *
 * Admin-created accounts (via Admin panel) are deliberately skipped.
 */

if (!defined('QA_VERSION')) { header('Location: ../../'); exit; }

class qa_preconfirm_event
{
    public function process_event($event, $userid, $handle, $cookieid, $params)
    {
        if ($event !== 'u_register') {
            return;
        }

        // ── Skip accounts created by an admin ──────────────────────────────
        // qa_get_logged_in_level() returns null/0 for guests, so an existing
        // admin session means this is a manual admin-created account.
        if (qa_get_logged_in_level() >= QA_USER_LEVEL_ADMIN) {
            return;
        }

        // ── Load helpers ───────────────────────────────────────────────────
        require_once dirname(__FILE__) . '/qa-preconfirm-db.php';
        require_once QA_INCLUDE_DIR   . 'app/users-edit.php';

        $email = isset($params['email']) ? trim($params['email']) : '';

        if (empty($email) || !$userid) {
            // Cannot proceed without email or userid — let Q2A handle it normally.
            return;
        }

        // ── Retrieve the bcrypt hash Q2A just stored ───────────────────────
        $result  = qa_db_query_sub(
            "SELECT passcheck FROM ^users WHERE userid=# LIMIT 1",
            (int)$userid
        );
        $userRow  = qa_db_read_one_assoc($result, true);
        $passhash = ($userRow && !empty($userRow['passcheck']))
                    ? $userRow['passcheck']
                    : '';

        // ── Persist pending registration ───────────────────────────────────
        qa_preconfirm_ensure_table();
        qa_preconfirm_purge_expired();

        $token = bin2hex(random_bytes(32)); // 64-char cryptographically secure token
        qa_preconfirm_upsert($email, $handle, $passhash, $token);

        // ── Delete the just-created Q2A user ──────────────────────────────
        // qa_delete_user() cleans up all related tables (userprofile, etc.)
        qa_delete_user($userid);

        // ── Send confirmation email ────────────────────────────────────────
        qa_preconfirm_send_email($email, $handle, $token);

        // ── Redirect and stop — Q2A never logs this user in ───────────────
        // qa_redirect() calls header() + exit, so the register page's
        // qa_set_logged_in_user() call is never reached.
        qa_redirect('confirm-pending');
    }
}
