<?php
/**
 * Responsive HTML shell for appointment notification emails.
 *
 * Owner: notifications contract (see .github/copilot/skills/notifications/SKILL.md §11.10).
 * Rendered by App\Services\EmailBodyRenderer, which injects either a redesigned HTML
 * template fragment (customer emails) or a converted plain-text body (internal / custom
 * templates) as $contentHtml. SMS and WhatsApp never use this shell.
 *
 * Inline + <style> CSS is intentional: email clients require it and strip linked
 * stylesheets. This is the accepted exception to the no-inline-style view rule — the
 * password-reset email view (app/Views/auth/emails/password-reset.php) follows the same
 * precedent.
 *
 * @var string $contentHtml Body HTML to place inside the content area
 * @var string $subject     Email subject (used for <title>)
 * @var string $preheader   Optional hidden preview text shown by inbox clients
 */
$logoUrl   = setting_url('general.company_logo', 'assets/settings/default-logo.svg');
$preheader = $preheader ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light only">
    <title><?= esc($subject ?? 'Appointment Update') ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #2d3748;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            -webkit-text-size-adjust: 100%;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.08);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            padding: 28px 30px;
            text-align: center;
        }
        .header .logo {
            display: block;
            margin: 0 auto;
            max-height: 48px;
        }
        .content {
            padding: 32px 30px;
            font-size: 16px;
            color: #4a5568;
        }
        .content p { margin: 0 0 16px; }
        .greeting { font-size: 18px; color: #2d3748; margin-bottom: 16px; }
        /* Appointment details card */
        .details-card {
            width: 100%;
            border-collapse: collapse;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            margin: 8px 0 20px;
        }
        .details-card td { padding: 16px 18px; vertical-align: top; }
        .detail-row { padding: 4px 0; font-size: 15px; }
        .detail-label { color: #718096; }
        .detail-value { color: #2d3748; font-weight: 600; }
        .card-title {
            font-size: 13px;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #667eea;
            font-weight: 700;
            margin: 0 0 10px;
        }
        /* Buttons */
        .cta { text-align: center; margin: 8px 0 4px; }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff !important;
            padding: 12px 22px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            margin: 6px 4px;
        }
        .btn-secondary {
            background: #ffffff;
            color: #553c9a !important;
            border: 1px solid #cbd5e0;
        }
        .ref {
            display: inline-block;
            background: #edf2f7;
            border-radius: 6px;
            padding: 6px 12px;
            font-weight: 700;
            letter-spacing: .03em;
            color: #2d3748;
        }
        .muted { color: #718096; font-size: 13px; }
        .copy-link { word-break: break-all; color: #667eea; font-size: 13px; }
        .divider { border: none; border-top: 1px solid #e2e8f0; margin: 24px 0; }
        .footer {
            background: #f7fafc;
            padding: 20px 30px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        .footer p { margin: 4px 0; font-size: 13px; color: #718096; }
        .footer a { color: #667eea; text-decoration: none; }
        @media (max-width: 600px) {
            .email-container { margin: 0; border-radius: 0; }
            .content { padding: 24px 18px; }
            .btn { display: block; margin: 8px 0; }
        }
    </style>
</head>
<body>
    <?php if ($preheader !== ''): ?>
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;"><?= esc($preheader) ?></div>
    <?php endif; ?>
    <div class="email-container">
        <div class="header">
            <?php if ($logoUrl): ?>
                <img class="logo" src="<?= esc($logoUrl) ?>" alt="Company logo" />
            <?php else: ?>
                <span style="font-size:28px;font-weight:300;">WebScheduler</span>
            <?php endif; ?>
        </div>
        <div class="content">
            <?= $contentHtml ?>
        </div>
        <div class="footer">
            <p>This is an automated message — please do not reply to this email.</p>
            <p>&copy; <?= date('Y') ?> WebScheduler. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
