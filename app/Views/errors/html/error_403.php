<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Access Denied</title>

    <style>
        body {
            height: 100%;
            background: #fafafa;
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            color: #777;
            font-weight: 300;
        }
        h1 {
            font-weight: lighter;
            letter-spacing: normal;
            font-size: 3rem;
            margin-top: 0;
            margin-bottom: 0;
            color: #222;
        }
        .wrap {
            max-width: 1024px;
            margin: 5rem auto;
            padding: 2rem;
            background: #fff;
            text-align: center;
            border: 1px solid #efefef;
            border-radius: 0.5rem;
            position: relative;
        }
        p {
            margin-top: 1.5rem;
        }
        .actions {
            margin-top: 2rem;
        }
        .actions a {
            color: #dd4814;
            margin: 0 0.5rem;
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>403</h1>

    <p>
        <?php if (ENVIRONMENT !== 'production') : ?>
            <?= nl2br(esc($message ?? 'Access denied')) ?>
        <?php else : ?>
            You do not have permission to access this page.
        <?php endif; ?>
    </p>

    <p class="actions">
        <a href="<?= esc($logoutUrl ?? base_url('auth/logout')) ?>">Logout</a>
        <a href="<?= esc($homeUrl ?? base_url()) ?>">Home</a>
    </p>
</div>
</body>
</html>