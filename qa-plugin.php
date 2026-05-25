<?php
/*
    Plugin Name:    Pre-Confirm Registration
    Plugin URI:
    Plugin Description: Prevents spam accounts by deleting any newly registered user
                        immediately, storing their details in a pending table, and only
                        creating the real account after they click the confirmation link
                        in their email. Uses an event module so it works regardless of
                        which register page is active.
    Plugin Version: 2.0.0
    Plugin Date:    2026-05-26
    Plugin Author:  Custom
    Plugin License: GPLv2
    Plugin Minimum Question2Answer Version: 1.8
*/

if (!defined('QA_VERSION')) { header('Location: ../../'); exit; }

// Event module: hooks into u_register, fires inside qa_create_new_user()
qa_register_plugin_module(
    'event',
    'qa-preconfirm-event.php',
    'qa_preconfirm_event',
    'Pre-Confirm: Registration Event'
);

// Page module: /confirm-pending  — "check your inbox" landing page
qa_register_plugin_module(
    'page',
    'qa-preconfirm-pending.php',
    'qa_preconfirm_pending',
    'Pre-Confirm: Check Email Page'
);

// Page module: /verify-email?t=TOKEN  — activates account on click
qa_register_plugin_module(
    'page',
    'qa-preconfirm-verify.php',
    'qa_preconfirm_verify',
    'Pre-Confirm: Verify Email Page'
);
