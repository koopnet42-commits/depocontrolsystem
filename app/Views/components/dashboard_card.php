<?php

declare(strict_types=1);

/**
 * Expected variables: $label, $value, $meta, $tone.
 */
?>
<?php
$groupAttribute = '';
if (isset($outboundGroup)) {
    $groupAttribute = 'data-outbound-group="' . htmlspecialchars((string) $outboundGroup) . '"';
} elseif (isset($group)) {
    $groupAttribute = 'data-vehicle-group="' . htmlspecialchars((string) $group) . '"';
}
$cardClass = 'summary-card summary-card--' . htmlspecialchars($tone ?? 'default') . (isset($outboundGroup) ? ' summary-card--outbound' : '');
?>
<button class="<?= $cardClass ?>" type="button" <?= $groupAttribute ?>>
    <span><?= htmlspecialchars($label) ?></span>
    <strong><?= htmlspecialchars((string) $value) ?></strong>
    <?php if (($meta ?? '') !== ''): ?>
        <small><?= htmlspecialchars((string) $meta) ?></small>
    <?php endif; ?>
</button>
