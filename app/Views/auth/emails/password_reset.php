<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - XScheduler</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 300;
        }
        .header .icon {
            font-size: 48px;
            margin-bottom: 10px;
            display: block;
        }
        .content {
            padding: 40px 30px;
        }
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
            color: #2d3748;
        }
        .message {
            font-size: 16px;
            margin-bottom: 30px;
            color: #4a5568;
            line-height: 1.8;
        }
        .reset-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 16px;
            margin: 20px 0;
            transition: transform 0.2s;
        }
        .reset-button:hover {
            transform: translateY(-2px);
        }
        .link-info {
            background: #f7fafc;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }
        .link-info p {
            margin: 0;
            font-size: 14px;
            color: #2d3748;
        }
        .footer {
            background: #f7fafc;
            padding: 20px 30px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        .footer p {
            margin: 5px 0;
            font-size: 14px;
            color: #718096;
        }
        .security-info {
            background: #fef5e7;
            border: 1px solid #f6ad55;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        .security-info p {
            margin: 0;
            font-size: 14px;
            color: #744210;
        }
        @media (max-width: 600px) {
            .email-container {
                margin: 10px;
            }
            .content {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <span class="icon">🔐</span>
            <h1>XScheduler</h1>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="greeting">
                Hello <?= esc($name) ?>,
            </div>

            <div class="message">
                We received a request to reset your password for your XScheduler account. 
                If you made this request, click the button below to set a new password:
            </div>

            <div style="text-align: center;">
                <a href="<?= esc($resetLink) ?>" class="reset-button">
                    Reset My Password
                </a>
            </div>

            <div class="link-info">
                <p><strong>Link expires in 1 hour</strong></p>
                <p>If the button doesn't work, copy and paste this link into your browser:</p>
                <p style="word-break: break-all; color: #667eea; margin-top: 10px;">
                    <?= esc($resetLink) ?>
                </p>
            </div>

            <div class="security-info">
                <p><strong>Security Note:</strong> If you didn't request a password reset, please ignore this email. Your password will remain unchanged.</p>
            </div>

            <div class="message">
                If you have any questions or concerns, please contact our support team.
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>XScheduler Team</strong></p>
            <p>This is an automated message, please do not reply to this email.</p>
            <p>&copy; <?= date('Y') ?> XScheduler. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
