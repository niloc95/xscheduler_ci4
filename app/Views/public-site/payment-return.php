<?= view('public-site/_payment-result', [
    'pageTitle'  => 'Payment Received',
    'icon'       => '✅',
    'heading'    => 'Payment Received',
    'paragraphs' => [
        'Thank you! Your deposit has been received.',
        'Your booking is confirmed — please check your email for the confirmation details.',
    ],
    'footnote'   => 'Gateway: ' . ucfirst($gateway ?? ''),
    'linkText'   => 'Book another appointment',
]) ?>
