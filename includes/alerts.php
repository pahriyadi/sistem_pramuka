<?php
/**
 * Flash Message Renderer
 * Racana Universitas Samawa (UNSA)
 */
$flash = get_flash();
if ($flash):
    $type = $flash['type']; // success, danger, warning, info
    $icon = 'fa-circle-info';
    if ($type === 'success') $icon = 'fa-circle-check';
    elseif ($type === 'danger') $icon = 'fa-circle-exclamation';
    elseif ($type === 'warning') $icon = 'fa-triangle-exclamation';
?>
    <div class="alert alert-<?= e($type) ?> alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">
        <i class="fa-solid <?= $icon ?> fs-5 me-2 flex-shrink-0"></i>
        <div class="flex-grow-1"><?= $flash['message'] ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
