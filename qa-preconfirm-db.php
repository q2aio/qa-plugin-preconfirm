<?php
/**
 * qa-preconfirm-db.php  —  shared helpers for Pre-Confirm Registration plugin.
 */

if (!defined('QA_VERSION')) { header('Location: ../../'); exit; }

function qa_preconfirm_ensure_table()
{
    qa_db_query_sub(
        "CREATE TABLE IF NOT EXISTS ^pending_users (
            id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
            email     VARCHAR(200) NOT NULL,
            handle    VARCHAR(40)  NOT NULL,
            passhash  VARCHAR(255) NOT NULL,
            token     VARCHAR(64)  NOT NULL,
            created   DATETIME     NOT NULL,
            expires   DATETIME     NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_token  (token),
            UNIQUE KEY uk_email  (email),
            UNIQUE KEY uk_handle (handle),
            INDEX      idx_exp   (expires)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
    );
}

function qa_preconfirm_upsert($email, $handle, $passhash, $token)
{
    qa_db_query_sub(
        "DELETE FROM ^pending_users WHERE email=$ OR handle=$",
        $email, $handle
    );
    qa_db_query_sub(
        "INSERT INTO ^pending_users (email, handle, passhash, token, created, expires)
         VALUES ($, $, $, $, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))",
        $email, $handle, $passhash, $token
    );
}

function qa_preconfirm_get_by_token($token)
{
    $result = qa_db_query_sub(
        "SELECT * FROM ^pending_users WHERE token=$ AND expires > NOW() LIMIT 1",
        $token
    );
    return qa_db_read_one_assoc($result, true);
}

function qa_preconfirm_delete($id)
{
    qa_db_query_sub("DELETE FROM ^pending_users WHERE id=#", (int)$id);
}

function qa_preconfirm_purge_expired()
{
    qa_db_query_sub("DELETE FROM ^pending_users WHERE expires <= NOW()");
}

function qa_preconfirm_send_email($email, $handle, $token)
{
    $verifyUrl = qa_path_absolute('verify-email') . '?t=' . urlencode($token);
    $siteName  = qa_opt('site_title');

    $body  = 'Hello ' . $handle . ',' . "\n\n";
    $body .= 'Thank you for registering on ' . $siteName . '.' . "\n\n";
    $body .= 'Click the link below to confirm your email address and activate your account:' . "\n\n";
    $body .= $verifyUrl . "\n\n";
    $body .= 'This link expires in 24 hours.' . "\n\n";
    $body .= 'If you did not register on ' . $siteName . ', please ignore this email.' . "\n\n";
    $body .= '-- ' . $siteName . ' Team';

    return qa_send_email([
        'fromemail' => qa_opt('from_email'),
        'fromname'  => $siteName,
        'toemail'   => $email,
        'toname'    => $handle,
        'subject'   => 'Confirm your registration on ' . $siteName,
        'body'      => $body,
        'html'      => false,
    ]);
}
