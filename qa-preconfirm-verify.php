<?php
/**
 * qa-preconfirm-verify.php
 *
 * Page module for /verify-email?t=TOKEN
 * Validates the token, creates the real Q2A user, writes the stored
 * password hash, marks email confirmed, and logs the user in.
 */

if (!defined('QA_VERSION')) { header('Location: ../../'); exit; }

class qa_preconfirm_verify
{
    public function match_request($request)
    {
        return ($request === 'verify-email');
    }

    public function process_request($request)
    {
        require_once dirname(__FILE__) . '/qa-preconfirm-db.php';
        require_once QA_INCLUDE_DIR   . 'app/users-edit.php';
        require_once QA_INCLUDE_DIR   . 'db/selects.php';

        qa_preconfirm_ensure_table();
        qa_preconfirm_purge_expired();

        $qa_content          = qa_content_prepare();
        $qa_content['title'] = 'Email Verification';

        // ── Validate token ─────────────────────────────────────────────────
        $token = trim((string) qa_get('t'));

        if ($token === '') {
            $qa_content['custom'] = $this->msg(
                'error', 'Invalid Link',
                'No verification token was found. '
                . 'Please use the exact link from your confirmation email.'
            );
            return $qa_content;
        }

        $pending = qa_preconfirm_get_by_token($token);

        if (!$pending) {
            $qa_content['custom'] = $this->msg(
                'error', 'Link Expired or Already Used',
                'This link has expired or has already been used. '
                . '<a href="' . qa_path_html('register') . '">Register again</a> '
                . 'to receive a new confirmation link.'
            );
            return $qa_content;
        }

        // ── Check for collisions (race condition: someone grabbed the handle) ──
        $collision = $this->check_collision($pending['email'], $pending['handle']);
        if ($collision) {
            qa_preconfirm_delete($pending['id']);
            $qa_content['custom'] = $this->msg('error', 'Registration Conflict', $collision);
            return $qa_content;
        }

        // ── Create the real account ────────────────────────────────────────
        $userid = $this->create_account($pending);

        if (!$userid) {
            $qa_content['custom'] = $this->msg(
                'error', 'Account Creation Failed',
                'Something went wrong creating your account. '
                . 'Please contact the site administrator.'
            );
            return $qa_content;
        }

        // ── Cleanup + login ────────────────────────────────────────────────
        qa_preconfirm_delete($pending['id']);
        qa_set_logged_in_user($userid, $pending['handle']);

        $qa_content['custom'] = $this->msg(
            'success',
            'Welcome, ' . qa_html($pending['handle']) . '!',
            'Your email has been confirmed and your account is now active. '
            . '<a href="' . qa_path_html('') . '">Go to the home page &#8594;</a>'
        );

        return $qa_content;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function check_collision($email, $handle)
    {
        $byEmail = qa_db_select_with_pending(
            qa_db_user_account_selectspec($email, true)
        );
        if (is_array($byEmail)) {
            return 'The email <strong>' . qa_html($email) . '</strong> is already registered. '
                 . '<a href="' . qa_path_html('login') . '">Log in</a> instead.';
        }

        $byHandle = qa_db_select_with_pending(
            qa_db_user_account_selectspec($handle, false)
        );
        if (is_array($byHandle)) {
            return 'The username <strong>' . qa_html($handle) . '</strong> is already taken. '
                 . '<a href="' . qa_path_html('register') . '">Register</a> with a different username.';
        }

        return null;
    }

    private function create_account(array $pending)
    {
        // Pass a dummy password — we overwrite it immediately after.
        // This avoids ever handling the real password in plaintext here.
        $dummy = bin2hex(random_bytes(16));

        try {
            $userid = qa_create_new_user(
                $pending['email'],
                $dummy,
                $pending['handle'],
                QA_USER_LEVEL_BASIC,
                true   // mark email as confirmed
            );
        } catch (Exception $e) {
            error_log('qa-preconfirm verify: ' . $e->getMessage());
            return false;
        }

        if (!$userid) {
            return false;
        }

        // Write the bcrypt hash we saved at registration time.
        // Q2A stores it in qa_users.passcheck.
        qa_db_query_sub(
            "UPDATE ^users SET passcheck=$ WHERE userid=#",
            $pending['passhash'],
            (int)$userid
        );

        return $userid;
    }

    private function msg($type, $heading, $body)
    {
        $styles = [
            'success' => ['#4caf50', '#f0fff0', '#2e7d32'],
            'error'   => ['#f44336', '#fff5f5', '#b71c1c'],
        ];
        [$border, $bg, $hColor] = $styles[$type] ?? $styles['error'];

        return '<div style="border:2px solid ' . $border . ';border-radius:6px;'
             . 'padding:24px;background:' . $bg . ';max-width:540px;">'
             . '<h3 style="color:' . $hColor . ';margin-top:0;">' . $heading . '</h3>'
             . '<p>' . $body . '</p>'
             . '</div>';
    }
}
