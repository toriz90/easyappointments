<?php
/**
 * Local variables.
 *
 * @var string $subject
 * @var string $message
 * @var array $settings
 * @var string|null $company_logo_cid
 */
?>
<html lang="en">
<head>
    <title><?= $subject ?><?= !empty($settings['company_name']) ? ' | ' . e($settings['company_name']) : '' ?></title>
</head>
<body style="font: 13px arial, helvetica, tahoma;">

<div class="email-container" style="width: 650px; border: 1px solid #eee; margin: 30px auto;">
    <div id="header"
         style="background-color: <?= $settings['company_color'] ?? EMAIL_FALLBACK_COMPANY_COLOR ?>; padding: 15px; text-align: center;">
        <?php if (!empty($company_logo_cid)): ?>
            <table role="presentation" align="center" cellpadding="0" cellspacing="0" style="margin: 0 auto;">
                <tr>
                    <td align="center" valign="middle" style="background-color: #1F2A37; border-radius: 16px; padding: 12px;">
                        <img src="cid:<?= e($company_logo_cid) ?>" alt="<?= e($settings['company_name']) ?>" height="64" style="display:block; height:64px; width:auto; max-width:160px; border-radius: 8px;">
                    </td>
                </tr>
            </table>
        <?php else: ?>
            <strong id="logo" style="color: white; font-size: 20px; margin-top: 10px; display: inline-block">
                <?= e($settings['company_name']) ?>
            </strong>
        <?php endif; ?>
    </div>

    <div id="content" style="padding: 10px 15px; min-height: 400px">
        <h2>
            <?= $subject ?>
        </h2>
        <p>
            <?= $message ?>
        </p>
    </div>

    <div id="footer" style="padding: 10px; text-align: center; margin-top: 10px;
                border-top: 1px solid #EEE; background: #FAFAFA;">
        <a href="<?= $settings['company_link'] ?>" style="text-decoration: none;">
            <?= e($settings['company_name']) ?>
        </a>
    </div>
</div>

</body>
</html>
