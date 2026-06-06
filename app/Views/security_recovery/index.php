<?php

declare(strict_types=1);

use App\Core\Auth;

$alerts = [
    'saved' => ['alert--success', 'İşlem kaydedildi.'],
    'password_reset' => ['alert--success', 'Geçici şifre oluşturuldu. Bu şifre sadece bir kez gösterilir.'],
    'invalid' => ['alert--danger', 'İşlem için hedef kullanıcı ve servis notu zorunludur.'],
    'admin_required' => ['alert--danger', 'Sistemde en az bir aktif admin kalmalıdır.'],
];
$alert = $alerts[$message] ?? null;
?>
<header class="page-header security-header">
    <div>
        <h1 class="page-title">Güvenlik / Kullanıcı Kurtarma</h1>
        <p class="page-subtitle">Üretici master hesabı yalnızca kullanıcı kurtarma işlemleri yapabilir.</p>
    </div>
</header>

<?php if ($alert !== null): ?>
    <div class="alert <?= htmlspecialchars($alert[0]) ?>"><?= htmlspecialchars($alert[1]) ?></div>
<?php endif; ?>

<?php if ($tempPassword !== null): ?>
    <section class="security-temp-password">
        <span>Tek kullanımlık geçici şifre</span>
        <strong><?= htmlspecialchars($tempPassword['password']) ?></strong>
        <p><?= htmlspecialchars($tempPassword['user']) ?> kullanıcısına iletin. Bu değer tekrar görüntülenemez.</p>
    </section>
<?php endif; ?>

<section class="panel filter-panel">
    <form action="/security-recovery" method="get" class="plate-search">
        <label class="field">
            <span>Kullanıcı ara</span>
            <input type="text" name="q" value="<?= htmlspecialchars($query) ?>" placeholder="Ad veya e-posta">
        </label>
        <button class="button button--primary" type="submit">Ara</button>
    </form>
</section>

<section class="panel">
    <div class="table-wrap">
        <table class="data-table data-table--compact">
            <thead>
                <tr>
                    <th>Kullanıcı</th>
                    <th>Rol</th>
                    <th>Durum</th>
                    <th>Giriş</th>
                    <th>Master İşlemleri</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <?php $isMaster = $user['role'] === 'master'; ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($user['name']) ?></strong>
                            <span class="table-muted"><?= htmlspecialchars($user['email']) ?></span>
                        </td>
                        <td><?= htmlspecialchars(Auth::roleLabel($user['role'])) ?></td>
                        <td>
                            <span class="badge <?= (int) $user['is_active'] === 1 ? 'badge--success' : 'badge--muted' ?>"><?= (int) $user['is_active'] === 1 ? 'Aktif' : 'Pasif' ?></span>
                            <?php if ((int) ($user['is_locked'] ?? 0) === 1 || ! empty($user['locked_until'])): ?>
                                <span class="badge badge--danger">Kilitli</span>
                            <?php endif; ?>
                            <?php if ((int) ($user['must_change_password'] ?? 0) === 1): ?>
                                <span class="badge badge--info">Şifre değişecek</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="table-muted">Son giriş: <?= htmlspecialchars((string) ($user['last_login_at'] ?? '-')) ?></span>
                            <span class="table-muted">Hatalı giriş: <?= (int) ($user['failed_login_count'] ?? 0) ?></span>
                        </td>
                        <td class="security-actions">
                            <?php if ($isMaster): ?>
                                <span class="table-muted">Master kullanıcı değiştirilemez.</span>
                            <?php else: ?>
                                <form action="/security-recovery/reset-password" method="post" data-confirm="Şifre sıfırlanacak ve geçici şifre yalnızca bir kez gösterilecek. Devam edilsin mi?">
                                    <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                    <input type="text" name="service_note" placeholder="Servis notu" required>
                                    <button class="button button--small button--primary" type="submit">Şifre Sıfırla</button>
                                </form>
                                <form action="/security-recovery/unlock" method="post">
                                    <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                    <input type="text" name="service_note" placeholder="Servis notu" required>
                                    <button class="button button--small" type="submit">Kilidi Aç</button>
                                </form>
                                <form action="/security-recovery/toggle-status" method="post" data-confirm="Kullanıcı aktif/pasif durumu değiştirilecek. Devam edilsin mi?">
                                    <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                    <input type="text" name="service_note" placeholder="Servis notu" required>
                                    <button class="button button--small" type="submit"><?= (int) $user['is_active'] === 1 ? 'Pasife Al' : 'Aktifleştir' ?></button>
                                </form>
                                <form action="/security-recovery/assign-role" method="post">
                                    <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                    <select name="role" required>
                                        <?php foreach ($roles as $role => $label): ?>
                                            <option value="<?= htmlspecialchars($role) ?>" <?= $role === $user['role'] ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="service_note" placeholder="Servis notu" required>
                                    <button class="button button--small" type="submit">Rol Ata</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
