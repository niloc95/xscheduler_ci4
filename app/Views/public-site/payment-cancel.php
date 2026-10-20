<?= view('public-site/_payment-result', [
    'pageTitle'  => 'Payment Cancelled',
    'icon'       => '↩️',
    'heading'    => 'Payment Cancelled',
    'paragraphs' => [
        'Your payment was not completed. Your booking slot has been reserved but is pending payment.',
        'You can retry the payment or contact us to arrange an alternative.',
    ],
    'footnote'   => null,
    'linkText'   => 'Return to booking',
]) ?>
