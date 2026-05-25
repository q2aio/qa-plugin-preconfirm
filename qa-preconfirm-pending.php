<?php
/**
 * qa-preconfirm-pending.php
 *
 * Page module for /confirm-pending
 * Users land here after submitting the register form.
 * This URL is brand-new (not a Q2A built-in), so the page module
 * intercepts it without any conflict.
 */

if (!defined('QA_VERSION')) { header('Location: ../../'); exit; }

class qa_preconfirm_pending
{
    public function match_request($request)
    {
        return ($request === 'confirm-pending');
    }

    public function process_request($request)
    {
        $qa_content          = qa_content_prepare();
        $qa_content['title'] = 'Check Your Email';

        $qa_content['custom'] =
            '<div style="border:2px solid #4caf50;border-radius:6px;'
          . 'padding:24px;background:#f0fff0;max-width:540px;">'
          . '<h3 style="color:#2e7d32;margin-top:0;">&#10003; One more step!</h3>'
          . '<p>We\'ve sent a confirmation link to your email address.</p>'
          . '<p>Click the link in that email to activate your account. '
          . 'The link is valid for <strong>24 hours</strong>.</p>'
          . '<p style="font-size:.9em;color:#555;">'
          . 'Didn\'t receive it? Check your spam folder. '
          . 'You can also <a href="' . qa_path_html('register') . '">register again</a> '
          . 'with the same address to get a new link.'
          . '</p>'
          . '</div>';

        return $qa_content;
    }
}
