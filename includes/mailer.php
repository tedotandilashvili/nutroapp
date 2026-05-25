<?php
/**
 * NutroApp - Simple SMTP Mailer
 * PHP 5.6 compatible — no external libraries needed
 * Uses PHP's mail() with SMTP via socket
 */

function sendEmail($to, $to_name, $subject, $html_body, $text_body = '') {
    // Try PHPMailer-style socket SMTP first, fallback to mail()
    $result = smtpSend($to, $to_name, $subject, $html_body, $text_body);
    if (!$result) {
        // Fallback to PHP mail()
        $headers  = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
        $headers .= 'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM . '>' . "\r\n";
        $headers .= 'Reply-To: ' . SMTP_FROM . "\r\n";
        return mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $html_body, $headers);
    }
    return $result;
}

function smtpSend($to, $to_name, $subject, $html_body, $text_body = '') {
    $host    = SMTP_HOST;
    $port    = SMTP_PORT;
    $user    = SMTP_USER;
    $pass    = SMTP_PASS;
    $secure  = SMTP_SECURE;
    $from    = SMTP_FROM;
    $from_nm = SMTP_FROM_NAME;

    // Build MIME message
    $boundary = md5(time());
    $text_body = $text_body ?: strip_tags($html_body);

    $message  = "--{$boundary}\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $message .= chunk_split(base64_encode($text_body)) . "\r\n";
    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $message .= chunk_split(base64_encode($html_body)) . "\r\n";
    $message .= "--{$boundary}--\r\n";

    $headers  = "From: =?UTF-8?B?".base64_encode($from_nm)."?= <{$from}>\r\n";
    $headers .= "To: =?UTF-8?B?".base64_encode($to_name)."?= <{$to}>\r\n";
    $headers .= "Subject: =?UTF-8?B?".base64_encode($subject)."?=\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "Date: ".date('r')."\r\n";
    $headers .= "Message-ID: <".time()."@nutroapp.ge>\r\n";

    // Connect via socket
    try {
        $ctx = stream_context_create();
        $prefix = ($secure === 'ssl') ? 'ssl://' : '';
        $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);
        if (!$socket) return false;

        // Read greeting
        smtpRead($socket);

        // EHLO
        fputs($socket, "EHLO nutroapp.ge\r\n");
        smtpRead($socket);

        // STARTTLS
        if ($secure === 'tls') {
            fputs($socket, "STARTTLS\r\n");
            smtpRead($socket);
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            fputs($socket, "EHLO nutroapp.ge\r\n");
            smtpRead($socket);
        }

        // Auth
        fputs($socket, "AUTH LOGIN\r\n");
        smtpRead($socket);
        fputs($socket, base64_encode($user)."\r\n");
        smtpRead($socket);
        fputs($socket, base64_encode($pass)."\r\n");
        $auth = smtpRead($socket);
        if (strpos($auth, '235') === false) { fclose($socket); return false; }

        // Send
        fputs($socket, "MAIL FROM:<{$from}>\r\n");
        smtpRead($socket);
        fputs($socket, "RCPT TO:<{$to}>\r\n");
        smtpRead($socket);
        fputs($socket, "DATA\r\n");
        smtpRead($socket);
        fputs($socket, $headers."\r\n".$message."\r\n.\r\n");
        $sent = smtpRead($socket);
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        return strpos($sent, '250') !== false;
    } catch (Exception $e) {
        return false;
    }
}

function smtpRead($socket) {
    $data = '';
    while ($line = fgets($socket, 512)) {
        $data .= $line;
        if (substr($line, 3, 1) === ' ') break;
    }
    return $data;
}

// ── Email templates ─────────────────────────────────────────────────────────

function emailTemplate($title, $content, $btn_text = '', $btn_url = '') {
    $btn = '';
    if ($btn_text && $btn_url) {
        $btn = '<div style="text-align:center;margin:28px 0;">
            <a href="'.$btn_url.'" style="background:#1D9E75;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:8px;font-size:15px;font-weight:500;display:inline-block;">'.$btn_text.'</a>
        </div>';
    }
    return '<!DOCTYPE html><html lang="ka"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#F8F8F6;font-family:\'Helvetica Neue\',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#F8F8F6;padding:32px 0;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;">
  <!-- Header -->
  <tr><td style="background:#1A1A18;border-radius:12px 12px 0 0;padding:20px 32px;">
    <div style="font-family:Georgia,serif;font-size:22px;color:#ffffff;">Nutro<span style="color:#1D9E75;font-style:italic;">App</span></div>
  </td></tr>
  <!-- Body -->
  <tr><td style="background:#ffffff;padding:32px;border-left:1px solid #E8E6DF;border-right:1px solid #E8E6DF;">
    <h1 style="font-size:20px;font-weight:600;color:#1A1A18;margin:0 0 16px;">'.$title.'</h1>
    '.$content.$btn.'
  </td></tr>
  <!-- Footer -->
  <tr><td style="background:#F1EFE8;border-radius:0 0 12px 12px;border:1px solid #E8E6DF;border-top:none;padding:16px 32px;text-align:center;">
    <p style="font-size:12px;color:#888780;margin:0;">nutroapp.ge &middot; პერსონალური კვების გეგმა</p>
    <p style="font-size:11px;color:#B4B2A9;margin:6px 0 0;">გამოწერის გაუქმება: <a href="https://nutroapp.ge/pricing.php" style="color:#1D9E75;">nutroapp.ge/pricing.php</a></p>
  </td></tr>
</table>
</td></tr>
</table>
</body></html>';
}

// ── Notification senders ────────────────────────────────────────────────────

function sendWelcomeEmail($user) {
    $content = '
        <p style="color:#444441;font-size:15px;line-height:1.7;">გამარჯობა, <strong>'.htmlspecialchars($user['name']).'</strong>!</p>
        <p style="color:#444441;font-size:15px;line-height:1.7;">კეთილი იყოს თქვენი მობრძანება NutroApp-ზე — პერსონალურად გენერირებული კვების გეგმა ქართული პროდუქტებით.</p>
        <div style="background:#E1F5EE;border-radius:8px;padding:16px;margin:20px 0;">
          <p style="margin:0;font-size:14px;color:#0F6E56;"><strong>დასაწყებად:</strong></p>
          <ol style="margin:8px 0 0;padding-left:18px;color:#0F6E56;font-size:14px;line-height:1.8;">
            <li>შეავსეთ პროფილი</li>
            <li>აირჩიეთ გამოწერის გეგმა</li>
            <li>დააგენერირეთ პირველი კვების გეგმა</li>
          </ol>
        </div>';
    $html = emailTemplate('მოგესალმებით NutroApp-ზე! 🥗', $content, 'გეგმის შექმნა', 'https://nutroapp.ge/generate.php');
    return sendEmail($user['email'], $user['name'], 'კეთილი იყოს თქვენი მობრძანება NutroApp-ზე!', $html);
}

function sendSubscriptionConfirmEmail($user, $plan_name, $expires_at, $amount) {
    $content = '
        <p style="color:#444441;font-size:15px;line-height:1.7;">გამარჯობა, <strong>'.htmlspecialchars($user['name']).'</strong>!</p>
        <p style="color:#444441;font-size:15px;line-height:1.7;">თქვენი გამოწერა წარმატებით გააქტიურდა.</p>
        <div style="background:#F8F7F2;border-radius:8px;padding:16px;margin:20px 0;">
          <table width="100%" cellpadding="6" cellspacing="0">
            <tr><td style="color:#888780;font-size:13px;">გეგმა</td><td style="font-weight:500;text-align:right;">'.htmlspecialchars($plan_name).'</td></tr>
            <tr><td style="color:#888780;font-size:13px;">გადახდა</td><td style="font-weight:500;text-align:right;color:#1D9E75;">'.number_format($amount,2).' ₾</td></tr>
            <tr><td style="color:#888780;font-size:13px;">მოქმედია</td><td style="font-weight:500;text-align:right;">'.date('d/m/Y', $expires_at).'-მდე</td></tr>
          </table>
        </div>';
    $html = emailTemplate('გამოწერა გააქტიურდა ✅', $content, 'კვების გეგმა', 'https://nutroapp.ge/generate.php');
    return sendEmail($user['email'], $user['name'], '✅ NutroApp გამოწერა გააქტიურდა', $html);
}

function sendExpiryReminderEmail($user, $plan_name, $expires_at, $days_left) {
    $urgency = $days_left <= 3 ? '#A32D2D' : '#854F0B';
    $urgency_bg = $days_left <= 3 ? '#FCEBEB' : '#FAEEDA';
    $content = '
        <p style="color:#444441;font-size:15px;line-height:1.7;">გამარჯობა, <strong>'.htmlspecialchars($user['name']).'</strong>!</p>
        <div style="background:'.$urgency_bg.';border-radius:8px;padding:16px;margin:16px 0;border-left:4px solid '.$urgency.';">
          <p style="margin:0;font-size:15px;color:'.$urgency.';">
            ⏰ თქვენი <strong>'.htmlspecialchars($plan_name).'</strong> გამოწერა <strong>'.$days_left.' დღეში</strong> სრულდება.
          </p>
          <p style="margin:8px 0 0;font-size:13px;color:'.$urgency.';">ვადა: '.date('d/m/Y', $expires_at).'</p>
        </div>
        <p style="color:#444441;font-size:14px;line-height:1.7;">გამოწერის განახლებისთვის, არ გამოგრჩეთ გადახდა.</p>';
    $html = emailTemplate('გამოწერის გაუქმებამდე დარჩენილია '.$days_left.' დღე ⏰', $content, 'გამოწერის განახლება', 'https://nutroapp.ge/pricing.php');
    return sendEmail($user['email'], $user['name'], '⏰ NutroApp გამოწერა '.$days_left.' დღეში სრულდება', $html);
}

function sendPlanGeneratedEmail($user, $plan_title, $plan_id, $calories) {
    $content = '
        <p style="color:#444441;font-size:15px;line-height:1.7;">გამარჯობა, <strong>'.htmlspecialchars($user['name']).'</strong>!</p>
        <p style="color:#444441;font-size:15px;line-height:1.7;">თქვენი კვების გეგმა მზადაა!</p>
        <div style="background:#E1F5EE;border-radius:8px;padding:16px;margin:16px 0;">
          <p style="margin:0;font-size:15px;color:#0F6E56;font-weight:500;">🥗 '.htmlspecialchars($plan_title).'</p>
          <p style="margin:8px 0 0;font-size:13px;color:#0F6E56;">'.$calories.' კკალ / დღეში</p>
        </div>';
    $html = emailTemplate('კვების გეგმა მზადაა! 🥗', $content, 'გეგმის ნახვა', 'https://nutroapp.ge/plan.php?id='.$plan_id);
    return sendEmail($user['email'], $user['name'], '🥗 NutroApp — კვების გეგმა მზადაა', $html);
}

function sendReferralRewardEmail($user, $referred_name) {
    $content = '
        <p style="color:#444441;font-size:15px;line-height:1.7;">გამარჯობა, <strong>'.htmlspecialchars($user['name']).'</strong>!</p>
        <p style="color:#444441;font-size:15px;line-height:1.7;">
          <strong>'.htmlspecialchars($referred_name).'</strong> დარეგისტრირდა თქვენი რეფერალური ლინკით და გამოწერა შეიძინა!
        </p>
        <div style="background:#E1F5EE;border-radius:8px;padding:16px;margin:16px 0;text-align:center;">
          <div style="font-size:36px;">🎁</div>
          <p style="margin:8px 0 0;font-size:15px;color:#0F6E56;font-weight:500;">1 თვე უფასო გამოწერა!</p>
          <p style="margin:4px 0 0;font-size:13px;color:#888780;">ადმინი დაადასტურებს ჯილდოს 24 საათში</p>
        </div>';
    $html = emailTemplate('🎁 რეფერალის ჯილდო!', $content, 'მთავარი გვერდი', 'https://nutroapp.ge/dashboard.php');
    return sendEmail($user['email'], $user['name'], '🎁 NutroApp — მიიღეთ 1 თვე უფასო!', $html);
}
