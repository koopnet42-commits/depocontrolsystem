<?php

declare(strict_types=1);

$app = require BASE_PATH . '/config/app.php';
$modules = $modules ?? require BASE_PATH . '/data/modules.php';
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$operationStages = [
    '/weighbridge-entry' => ['Kantar', 'Kantara gelen ve tartıma hazırlanan araçlar'],
    '/sample-analysis' => ['Analiz', 'Numune ve kalite kontrol aşaması'],
    '/barcode-tickets' => ['Barkod', 'Silo yönlendirme fişi aşaması'],
    '/unloading-operations' => ['Silo Boşaltım', 'Yönlendirilen aracın boşaltım aşaması'],
    '/second-weighing' => ['2. Tartım', 'Boşaltım sonrası kapanış tartımı'],
];
$activeOperationStage = $operationStages[$currentPath] ?? null;
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(($title ?? 'Dashboard') . ' | ' . $app['name']) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php if (($authLayout ?? false) === true): ?>
    <?php require $viewPath; ?>
<?php else: ?>
    <div class="app-shell">
        <?php require BASE_PATH . '/app/Views/partials/sidebar.php'; ?>

        <div class="workspace">
            <?php require BASE_PATH . '/app/Views/components/topbar.php'; ?>
            <main class="main">
                <?php if ($activeOperationStage !== null): ?>
                    <div class="operation-stage-strip" data-stage="<?= htmlspecialchars($currentPath) ?>">
                        <?php foreach ($operationStages as $stagePath => [$stageTitle, $stageDescription]): ?>
                            <a class="operation-stage-strip__item <?= $stagePath === $currentPath ? 'is-active' : '' ?>" href="<?= htmlspecialchars($stagePath) ?>">
                                <strong><?= htmlspecialchars($stageTitle) ?></strong>
                                <span><?= htmlspecialchars($stagePath === $currentPath ? $stageDescription : 'Git') ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php require $viewPath; ?>
            </main>
            <?php require BASE_PATH . '/app/Views/components/vehicle_process_modal.php'; ?>
        </div>
    </div>
<?php endif; ?>
<script>
    document.addEventListener('submit', (event) => {
        const message = event.target.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
            return;
        }

        const realModeMessage = event.target.getAttribute('data-confirm-real');
        const modeInput = event.target.querySelector('[name="operation_mode"]');
        if (realModeMessage && modeInput && modeInput.value === 'real' && !window.confirm(realModeMessage)) {
            event.preventDefault();
            return;
        }

        const refreshAfter = Number(event.target.getAttribute('data-refresh-after-submit') || 0);
        if (refreshAfter > 0) {
            window.setTimeout(() => window.location.reload(), refreshAfter);
        }
    });

    (() => {
        const storageGet = (key) => {
            try {
                return window.localStorage?.getItem(key) ?? null;
            } catch {
                return null;
            }
        };
        const storageSet = (key, value) => {
            try {
                window.localStorage?.setItem(key, value);
            } catch {
                return;
            }
        };

        document.querySelectorAll('[data-menu-group]').forEach((group) => {
            const key = `depo.menu.${group.dataset.menuGroup}`;
            const button = group.querySelector('.menu__group-title');
            const active = group.classList.contains('menu__group--active');
            const stored = storageGet(key);
            const isOpen = stored === null ? active : stored === 'open';

            group.classList.toggle('menu__group--open', isOpen);
            button?.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

            button?.addEventListener('click', () => {
                const nextOpen = !group.classList.contains('menu__group--open');
                group.classList.toggle('menu__group--open', nextOpen);
                button.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
                storageSet(key, nextOpen ? 'open' : 'closed');
            });
        });

        const dateEl = document.getElementById('sidebar-date');
        const timeEl = document.getElementById('sidebar-time');
        const dayEl = document.getElementById('sidebar-day');
        const updateClock = () => {
            const now = new Date();
            dateEl && (dateEl.textContent = `Tarih: ${now.toLocaleDateString('tr-TR', { timeZone: 'Europe/Istanbul' })}`);
            timeEl && (timeEl.textContent = `Saat: ${now.toLocaleTimeString('tr-TR', { timeZone: 'Europe/Istanbul', hour12: false })}`);
            dayEl && (dayEl.textContent = `Gün: ${now.toLocaleDateString('tr-TR', { timeZone: 'Europe/Istanbul', weekday: 'long' })}`);
        };
        updateClock();
        window.setInterval(updateClock, 1000);

        const validViews = ['vertical', 'horizontal'];
        const siloStorageKey = 'depo.dashboard.siloView';

        const renderSiloView = (view) => {
            const nextView = validViews.includes(view) ? view : 'vertical';
            const siloDashboard = document.querySelector('[data-silo-dashboard]');
            const grid = siloDashboard?.querySelector('[data-silo-grid]');
            const cards = Array.from(siloDashboard?.querySelectorAll('[data-silo-card]') ?? []);
            const buttons = Array.from(siloDashboard?.querySelectorAll('[data-silo-view-button]') ?? []);

            grid?.classList.remove('dashboard-silo-grid--vertical', 'dashboard-silo-grid--horizontal');
            grid?.classList.add(`dashboard-silo-grid--${nextView}`);

            cards.forEach((card) => {
                card.classList.remove('silo-visual-card--vertical', 'silo-visual-card--horizontal');
                card.classList.add(`silo-visual-card--${nextView}`);

                const fill = card.querySelector('.silo-visual__fill');
                const percent = fill?.dataset.fillPercent || '0';
                if (fill) {
                    fill.style.width = nextView === 'horizontal' ? `${percent}%` : '100%';
                    fill.style.height = nextView === 'vertical' ? `${percent}%` : '100%';
                }
            });

            buttons.forEach((button) => {
                const active = button.dataset.siloViewButton === nextView;
                button.classList.toggle('view-toggle__button--active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        };

        window.DepoDashboardSilos = {
            setView(view) {
                renderSiloView(view);
                storageSet(siloStorageKey, view);
            },
        };

        const siloDashboard = document.querySelector('[data-silo-dashboard]');
        if (siloDashboard) {
            const initialView = storageGet(siloStorageKey) || siloDashboard.dataset.initialView || 'vertical';
            renderSiloView(initialView);
        }

        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-silo-view-button]');
            if (!button) {
                return;
            }

            event.preventDefault();
            window.DepoDashboardSilos.setView(button.dataset.siloViewButton || 'vertical');
        });

        const normalizePlateInput = (value) => (value || '').toLocaleUpperCase('tr-TR').replace(/\s+/g, '').trim();
        const fieldValue = (form, name) => form.elements[name]?.value?.trim() || '';
        const setField = (form, name, value, onlyEmpty = false) => {
            const field = form.elements[name];
            if (!field || (onlyEmpty && field.value.trim() !== '')) return;
            field.value = value || '';
        };
        const conflict = (a, b) => String(a || '').trim() !== '' && String(b || '').trim() !== '' && String(a || '').trim().toLocaleLowerCase('tr-TR') !== String(b || '').trim().toLocaleLowerCase('tr-TR');
        const historyLine = (item, type) => {
            if (!item || Object.keys(item).length === 0) return '';
            return type === 'driver'
                ? `<small>Son geliş: ${item.used_at || '-'} / Son plaka: ${item.plate_number || '-'} / Son firma: ${item.company_name || '-'} / Toplam: ${item.total_count || 1}</small>`
                : `<small>Son geliş: ${item.used_at || '-'} / Son şoför: ${item.driver_name || '-'} / Son ürün: ${item.product_name || '-'} / Toplam: ${item.total_count || 1}</small>`;
        };
        const applyChoice = (form, group, action, data) => {
            form.dataset[`${group}Resolved`] = '1';
            form.elements[`${group}_match_action`].value = action;
            if (action === 'old') {
                if (group === 'vehicle') {
                    setField(form, 'vehicle_brand', data?.brand);
                    setField(form, 'vehicle_model', data?.model);
                } else {
                    setField(form, 'driver_name', data?.full_name);
                    setField(form, 'driver_phone', data?.phone);
                    setField(form, 'driver_identity_number', data?.identity_number);
                }
            }
            form.querySelector(`[data-${group}-warning]`)?.remove();
        };
        const renderMatchCard = (form, payload) => {
            const card = form.querySelector('[data-driver-vehicle-card]');
            if (!card) return;
            const vehicle = payload.vehicle;
            const driver = payload.driver;
            const blocks = [];
            form.dataset.vehicleResolved = '1';
            form.dataset.driverResolved = '1';
            if (vehicle) {
                setField(form, 'vehicle_brand', vehicle.brand, true);
                setField(form, 'vehicle_model', vehicle.model, true);
                const changed = conflict(fieldValue(form, 'vehicle_brand'), vehicle.brand) || conflict(fieldValue(form, 'vehicle_model'), vehicle.model);
                if (changed) {
                    form.dataset.vehicleResolved = '0';
                    form.elements.vehicle_match_action.value = '';
                }
                blocks.push(`<div class="match-card__block ${changed ? 'match-card__block--warn' : ''}" ${changed ? 'data-vehicle-warning' : ''}>
                    <strong>Plaka kaydı bulundu: ${vehicle.plate_number || vehicle.normalized_plate}</strong>
                    <span>Araç: ${vehicle.brand || '-'} ${vehicle.model || ''}</span>
                    ${historyLine(vehicle.history, 'vehicle')}
                    ${changed ? '<p>Bu plaka için girilen araç bilgisi kayıtlı bilgiden farklı. Nasıl devam edilsin?</p>' : ''}
                    ${changed ? '<div class="match-card__actions"><button type="button" data-match-choice="vehicle:old">Eski Bilgiyi Kullan</button><button type="button" data-match-choice="vehicle:update">Yeni Bilgiyle Güncelle</button><button type="button" data-match-choice="vehicle:once">Sadece Bu İşlem İçin Kullan</button></div>' : ''}
                </div>`);
            }
            if (driver) {
                setField(form, 'driver_name', driver.full_name, true);
                setField(form, 'driver_phone', driver.phone, true);
                setField(form, 'driver_identity_number', driver.identity_number, true);
                const changed = conflict(fieldValue(form, 'driver_name'), driver.full_name) || conflict(fieldValue(form, 'driver_phone'), driver.phone) || conflict(fieldValue(form, 'driver_identity_number'), driver.identity_number);
                if (changed) {
                    form.dataset.driverResolved = '0';
                    form.elements.driver_match_action.value = '';
                }
                blocks.push(`<div class="match-card__block ${changed ? 'match-card__block--warn' : ''}" ${changed ? 'data-driver-warning' : ''}>
                    <strong>Şoför kaydı bulundu: ${driver.full_name || '-'}</strong>
                    <span>Telefon: ${driver.phone || '-'} / TC: ${driver.identity_number || '-'}</span>
                    <small>Daha önce kullandığı plakalar: ${(driver.plates || []).join(', ') || '-'}</small>
                    ${historyLine(driver.history, 'driver')}
                    ${changed ? '<p>Bu şoför için girilen bilgi kayıtlı bilgiden farklı. Nasıl devam edilsin?</p>' : ''}
                    ${changed ? '<div class="match-card__actions"><button type="button" data-match-choice="driver:old">Eski Bilgiyi Kullan</button><button type="button" data-match-choice="driver:update">Yeni Bilgiyle Güncelle</button><button type="button" data-match-choice="driver:once">Sadece Bu İşlem İçin Kullan</button></div>' : ''}
                </div>`);
            }
            card.hidden = blocks.length === 0;
            card.innerHTML = blocks.join('');
            card.querySelectorAll('[data-match-choice]').forEach((button) => button.addEventListener('click', () => {
                const [group, action] = button.dataset.matchChoice.split(':');
                applyChoice(form, group, action, group === 'vehicle' ? vehicle : driver);
                if (!card.querySelector('[data-vehicle-warning], [data-driver-warning]')) card.hidden = true;
            }));
        };
        const bindDriverVehicleForm = (form) => {
            let timer = null;
            const run = () => {
                const plate = normalizePlateInput(fieldValue(form, 'plate_number'));
                if (form.elements.plate_number && plate.length >= 5) form.elements.plate_number.value = plate;
                const query = new URLSearchParams({
                    plate,
                    driver_name: fieldValue(form, 'driver_name'),
                    driver_phone: fieldValue(form, 'driver_phone'),
                    driver_identity_number: fieldValue(form, 'driver_identity_number'),
                });
                if (plate.length < 5 && query.get('driver_phone').length < 7 && query.get('driver_identity_number').length < 11 && query.get('driver_name').length < 5) return;
                fetch(`/driver-vehicle/lookup?${query.toString()}`, {headers: {'Accept': 'application/json'}})
                    .then((response) => response.json())
                    .then((payload) => renderMatchCard(form, payload))
                    .catch(() => {});
            };
            ['plate_number', 'vehicle_brand', 'vehicle_model', 'driver_name', 'driver_phone', 'driver_identity_number'].forEach((name) => {
                form.elements[name]?.addEventListener('input', () => {
                    if (name === 'driver_identity_number') form.elements[name].value = form.elements[name].value.replace(/\D+/g, '').slice(0, 11);
                    window.clearTimeout(timer);
                    timer = window.setTimeout(run, 450);
                });
            });
            form.addEventListener('submit', (event) => {
                if (form.dataset.vehicleResolved === '0' || form.dataset.driverResolved === '0') {
                    event.preventDefault();
                    form.querySelector('[data-driver-vehicle-card]')?.scrollIntoView({behavior: 'smooth', block: 'center'});
                }
            });
        };
        document.querySelectorAll('[data-driver-vehicle-form]').forEach(bindDriverVehicleForm);

        const vehicleModal = document.getElementById('vehicle-process-modal');
        const vehicleBackdrop = document.querySelector('[data-vehicle-process-backdrop]');
        const vehicleContent = document.querySelector('[data-vehicle-process-content]');
        const labels = {
            'pending': 'Beklemede',
            'ürün_bildirimi': 'Ürün bildirimi',
            'kantara_geldi': 'Kantara geldi',
            'giriş_bariyeri_açıldı': 'Giriş bariyeri açıldı',
            'kantarda': 'Kantarda',
            'ilk_tartım_alındı': 'İlk tartım alındı',
            'analiz_bekliyor': 'Analiz bekliyor',
            'analizde': 'Analizde',
            'analiz_yapıldı': 'Analiz yapıldı',
            'silo_belirlendi': 'Silo belirlendi',
            'barkod_bekliyor': 'Barkod bekliyor',
            'barkod_basıldı': 'Barkod basıldı',
            'siloya_yönlendirildi': 'Siloya yönlendirildi',
            'boşaltımda': 'Boşaltımda',
            'ikinci_tartım_bekliyor': 'İkinci tartım bekliyor',
            'tamamlandı': 'Tamamlandı',
            'iptal': 'İptal',
            'ret': 'Ret',
            'alıma_girmedi': 'Alıma girmedi',
        };
        const flowSteps = [
            ['Bildirim', ['pending', 'ürün_bildirimi']],
            ['Kantar Giriş', ['kantara_geldi', 'giriş_bariyeri_açıldı', 'kantarda']],
            ['1. Tartım', ['ilk_tartım_alındı', 'analiz_bekliyor']],
            ['Analiz', ['analizde', 'analiz_yapıldı', 'ret', 'alıma_girmedi']],
            ['Silo Seçimi', ['silo_belirlendi', 'barkod_bekliyor']],
            ['Barkod', ['barkod_basıldı', 'siloya_yönlendirildi']],
            ['Silo Boşaltım', ['boşaltımda']],
            ['2. Tartım', ['ikinci_tartım_bekliyor', 'ikinci_tartım_alındı']],
            ['Tamamlandı', ['tamamlandı']],
        ];
        const flowNavigationPermissions = <?= json_encode([
            'weighbridge' => \App\Core\Auth::canAccessPath('/weighbridge-entry'),
            'analysis' => \App\Core\Auth::canAccessPath('/sample-analysis'),
            'barcode' => \App\Core\Auth::canAccessPath('/barcode-tickets'),
            'unloading' => \App\Core\Auth::canAccessPath('/unloading-operations'),
            'second_weighing' => \App\Core\Auth::canAccessPath('/second-weighing'),
        ], JSON_UNESCAPED_UNICODE) ?>;
        const kgText = (kg) => kg === null || kg === undefined || kg === '' ? '-' : `${Number(kg).toLocaleString('tr-TR', {maximumFractionDigits: 0})} kg`;
        const ton = (kg) => kg === null || kg === undefined || kg === '' ? '-' : `${(Number(kg) / 1000).toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2})} ton`;
        const kgWithTon = (kg) => kg === null || kg === undefined || kg === '' ? '-' : `${kgText(kg)} (${ton(kg)})`;
        const esc = (value) => String(value ?? '-').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        const todayText = () => new Date().toISOString().slice(0, 10);
        const delayedSeconds = (record) => {
            if (!['pending', 'ürün_bildirimi'].includes(record?.status || '') || !record?.expected_arrival_date) return 0;
            let expected = new Date(record.expected_arrival_date);
            if (/^\d{4}-\d{2}-\d{2}($|\s+00:00:00$)/.test(record.expected_arrival_date)) {
                expected = new Date(`${record.expected_arrival_date.slice(0, 10)}T23:59:59`);
            }
            const seconds = Math.floor((Date.now() - expected.getTime()) / 1000);
            return Number.isFinite(seconds) ? Math.max(0, seconds) : 0;
        };
        const delayedText = (record) => {
            const seconds = delayedSeconds(record);
            if (seconds <= 0) return '';
            const hours = Math.floor(seconds / 3600);
            if (hours < 1) return 'Belirlenen tarih geçti';
            if (hours < 24) return `${hours} saat gecikti`;
            return `${Math.floor(hours / 24)} gün gecikti`;
        };
        const isDelayedRecord = (record) => delayedSeconds(record) > 0;
        const rejectionReasonLabels = {
            high_moisture: 'Rutubet yüksek',
            high_foreign_matter: 'Yabancı madde oranı yüksek',
            high_sunn_pest_rate: 'Süne oranı yüksek',
            low_hectoliter: 'Hektolitre düşük',
            not_suitable: 'Ürün alım kriterlerine uygun değil',
            other: 'Diğer',
        };
        const conditionalReasonLabels = {
            moisture_limit: 'Rutubet sınırda',
            foreign_material_limit: 'Yabancı madde sınırda',
            hectoliter_limit: 'Hektolitre düşük / sınırda',
            protein_limit: 'Protein değeri sınırda',
            quality_discount: 'Kalite kesintisi ile kabul',
            manager_approval: 'Yetkili onayı ile kabul',
            other: 'Diğer',
        };
        const rejectionReason = (value) => rejectionReasonLabels[value] || value || '-';
        const conditionalReason = (value) => conditionalReasonLabels[value] || value || 'Şartlı kabul sebebi kaydedilmemiş';
        const flowNavigationFor = (record, index) => {
            const entryId = Number(record.entry_id || record.id || 0);
            const recordId = Number(record.weighbridge_record_id || 0);
            const plate = encodeURIComponent(record.plate_number || '');
            const entryQuery = entryId ? `entry_id=${entryId}&entryId=${entryId}` : '';
            const plateQuery = plate ? `plate=${plate}` : '';
            const parts = (...items) => items.filter(Boolean).join('&');
            const withQuery = (path, query) => query ? `${path}?${query}` : path;
            const actions = {
                1: flowNavigationPermissions.weighbridge ? ['Kantar Ekranına Git', withQuery('/weighbridge-entry', parts(plateQuery, entryQuery))] : null,
                3: flowNavigationPermissions.analysis ? ['Analiz Ekranına Git', recordId ? `/sample-analysis/edit?record_id=${recordId}&${entryQuery}` : withQuery('/sample-analysis', parts(plateQuery, entryQuery))] : null,
                5: flowNavigationPermissions.barcode ? ['Barkod Ekranına Git', recordId ? `/barcode-tickets?record_id=${recordId}&${entryQuery}` : withQuery('/barcode-tickets', parts(plateQuery, entryQuery))] : null,
                6: flowNavigationPermissions.unloading ? ['Yönlendirme Bilgisi', withQuery('/unloading-operations', parts(plateQuery, entryQuery, recordId ? `record_id=${recordId}` : ''))] : null,
                7: flowNavigationPermissions.second_weighing ? ['2. Tartıma Git', withQuery('/second-weighing', parts(plateQuery, entryQuery, recordId ? `record_id=${recordId}` : ''))] : null,
            };

            return actions[index] || null;
        };
        const analysisStateClass = (record) => {
            if (record.status === 'ret' || record.status === 'alıma_girmedi' || record.analysis_result === 'rejected' || record.result_status === 'ret') {
                return 'analysis-state-row--rejected';
            }
            if (isDelayedRecord(record)) return 'operation-row-state--delayed';
            if (record.analysis_result === 'accepted') return 'analysis-state-row--accepted';
            if (record.analysis_result === 'conditional') return 'analysis-state-row--conditional';
            return 'analysis-state-row--pending';
        };

        const renderVehicle = (record, activeStep = null) => {
            const isRejected = record.status === 'ret' || record.status === 'alıma_girmedi';
            const delayed = isDelayedRecord(record);
            const cancelled = ['iptal', 'cancelled'].includes(record?.status || '');
            const cancelledWarning = cancelled ? `
                <div class="cancel-detail-banner field--wide">
                    <strong>Bu ön bildirim iptal edildi.</strong>
                    <span>İptal nedeni: ${esc(record.cancel_reason || 'İptal nedeni kaydedilmemiş')}</span>
                    <span>İptal açıklaması / notu: ${esc(record.cancel_note || 'İptal açıklaması kaydedilmemiş')}</span>
                    <span>İptal eden kullanıcı: ${esc(record.cancelled_by_name || 'Kullanıcı bilgisi yok')}</span>
                    <span>İptal tarihi ve saati: ${esc(record.cancelled_at || 'İptal tarihi kaydedilmemiş')}</span>
                </div>
            ` : '';
            const notifyInfo = (record.company_notified_at || record.company_notified_note || record.company_notified_by_name) ? `
                <div class="notify-detail-banner field--wide">
                    <strong>Firma bilgilendirme bilgisi</strong>
                    <span>Firma haberdar edildi mi: ${record.company_notified_at ? 'Evet' : 'Hayır'}</span>
                    <span>Haberdar edilme notu: ${esc(record.company_notified_note || '-')}</span>
                    <span>Haberdar eden kullanıcı: ${esc(record.company_notified_by_name || 'Kullanıcı bilgisi yok')}</span>
                    <span>Haberdar edilme tarihi: ${esc(record.company_notified_at || '-')}</span>
                </div>
            ` : '';
            const delayedWarning = delayed ? `
                <div class="delay-detail-banner field--wide">
                    <strong>Bu araç planlanan geliş tarihini geçti.</strong>
                    <span>Tahmini geliş tarihi: ${esc(record.expected_arrival_date)}</span>
                    <span>Gecikme: ${esc(delayedText(record))}</span>
                    <span>Firma haberdar edildi mi: ${record.company_notified_at ? 'Evet' : 'Hayır'}</span>
                    <span>Son not: ${esc(record.company_notified_note || record.notification_note || '-')}</span>
                </div>
            ` : '';
            const currentIndex = isRejected ? 3 : Math.max(0, flowSteps.findIndex(([, statuses]) => statuses.includes(record.status)));
            const selectedStep = activeStep ?? currentIndex;
            const detailBlocks = [
                ['Bildirim', `Bildirim no: ${esc(record.notification_number)}<br>Giriş: ${esc(record.created_at)}<br>Bildirim miktarı: ${esc(ton(record.expected_quantity_kg))}`],
                ['Kantar Giriş', `Kantar fişi: ${esc(record.weighbridge_ticket)}<br>Aktif durum: ${esc(labels[record.status] || record.status)}`],
                ['1. Tartım', `İlk tartım: ${esc(kgWithTon(record.first_weight_kg))}<br>Zaman: ${esc(record.first_weighed_at)}`],
                ['Analiz', `Sonuç: ${esc(record.analysis_result)}<br>Rutubet: ${esc(record.moisture)} / Hektolitre: ${esc(record.hectoliter)}<br>Yabancı madde: ${esc(record.foreign_material)} / Protein: ${esc(record.protein)}${record.analysis_result === 'conditional' ? `<br>Şartlı kabul sebebi: ${esc(conditionalReason(record.conditional_reason))}<br>Şartlı kabul açıklaması: ${esc(record.conditional_note || '-')}` : ''}${isRejected ? `<br>Ret sebebi: ${esc(rejectionReason(record.rejection_reason))}<br>Açıklama: ${esc(record.rejection_note)}${record.analysis_id ? `<br><a class="button button--small" href="/sample-analysis/rejection-print?analysis_id=${Number(record.analysis_id)}">Ret Fişi Yazdır</a>` : ''}` : ''}`],
                ['Silo Seçimi', `Hedef silo: ${esc((record.silo_code || '-') + ' - ' + (record.silo_name || '-'))}`],
                ['Barkod', `Ticket: ${esc(record.barcode)}<br>Durum: ${esc(record.barcode_status)}${record.weighbridge_record_id ? `<br><a class="button button--small" href="/barcode-tickets/print?record_id=${Number(record.weighbridge_record_id)}">Tekrar Yazdır</a>` : ''}`],
                ['Silo Boşaltım', `Başlangıç: ${esc(record.unloading_started_at)}<br>Bitiş: ${esc(record.unloading_completed_at)}<br>Durum: ${esc(record.unloading_status)}`],
                ['2. Tartım', `İkinci tartım: ${esc(kgWithTon(record.second_weight_kg))}<br>Net: ${esc(kgWithTon(record.net_weight_kg))}`],
                ['Tamamlandı', `Son durum: ${esc(labels[record.status] || record.status)}<br>Son işlem: ${esc(record.updated_at)}`],
            ];
            const flow = flowSteps.map(([label], index) => {
                const state = record.status === 'iptal'
                    ? 'error'
                    : (isRejected ? (index < currentIndex ? 'done' : (index === currentIndex ? 'error' : 'waiting')) : (index < currentIndex ? 'done' : (index === currentIndex ? 'current' : 'waiting')));
                const nav = flowNavigationFor(record, index);
                const navButton = nav ? `<a class="flow-step-action" href="${esc(nav[1])}" data-flow-nav>${esc(nav[0])}</a>` : '';
                return `<div class="flow-step-wrap"><button class="flow-step flow-step--${state} ${index === selectedStep ? 'flow-step--selected' : ''}" type="button" data-flow-step="${index}"><span>${index === currentIndex ? '🚚' : '•'}</span><strong>${esc(label)}</strong></button>${navButton}</div>`;
            }).join('');
            const history = (record.history || []).map((item) => `<li><strong>${esc(item.action_name)}</strong><span>${esc(item.old_status)} → ${esc(item.new_status)}</span><small>${esc(item.created_at)}</small></li>`).join('');
            vehicleContent.innerHTML = `
                <div class="vehicle-detail-shell ${analysisStateClass(record)}">
                ${cancelledWarning}
                ${notifyInfo}
                ${delayedWarning}
                <div class="detail-grid">
                    <div><span>Plaka</span><strong>${esc(record.plate_number)}</strong></div>
                    <div><span>Ürün</span><strong>${esc(record.product_name)}</strong></div>
                    <div><span>Firma / Şahıs</span><strong>${esc(record.sender_display)}</strong></div>
                    <div><span>Şoför</span><strong>${esc(record.driver_name)}</strong></div>
                    <div><span>Mevcut durum</span><strong>${esc(labels[record.status] || record.status)}</strong></div>
                    <div><span>Giriş tarihi</span><strong>${esc(record.expected_arrival_date || record.created_at)}</strong></div>
                    <div><span>Bildirilen miktar</span><strong>${esc(ton(record.expected_quantity_kg))}</strong></div>
                    <div><span>İlk tartım</span><strong>${esc(kgWithTon(record.first_weight_kg))}</strong></div>
                    <div><span>İkinci tartım</span><strong>${esc(kgWithTon(record.second_weight_kg))}</strong></div>
                    <div><span>Net miktar</span><strong>${esc(kgWithTon(record.net_weight_kg))}</strong></div>
                    <div><span>Atanan silo</span><strong>${esc((record.silo_code || '-') + ' - ' + (record.silo_name || '-'))}</strong></div>
                    <div><span>Ticket</span><strong>${esc(record.barcode)}</strong></div>
                </div>
                <div class="flow-diagram vehicle-flow">${flow}</div>
                <div class="vehicle-step-detail"><h3>${esc(detailBlocks[selectedStep][0])}</h3><p>${detailBlocks[selectedStep][1]}</p></div>
                <div class="process-history"><span>Süreç geçmişi</span><ul>${history || '<li>Geçmiş kaydı yok.</li>'}</ul></div>
                </div>
            `;
            vehicleContent.querySelectorAll('[data-flow-step]').forEach((button) => {
                button.addEventListener('click', () => renderVehicle(record, Number(button.dataset.flowStep)));
            });
        };

        window.openVehicleProcessDetail = async (entryId, step = null) => {
            vehicleBackdrop.hidden = false;
            vehicleModal.showModal();
            vehicleContent.innerHTML = '<p class="empty-state">Araç bilgileri yükleniyor...</p>';
            const response = await fetch(`/vehicle-process/detail?entry_id=${encodeURIComponent(entryId)}`, {headers: {'Accept': 'application/json'}});
            const payload = await response.json();
            if (!payload.ok) {
                vehicleContent.innerHTML = '<p class="empty-state">Araç kaydı bulunamadı.</p>';
                return;
            }
            renderVehicle(payload.record, step);
        };

        const outboundLabels = {
            OUTBOUND_PRE_NOTIFIED: 'Çıkış ön bildirimi',
            OUTBOUND_ARRIVED: '1. tartım bekliyor',
            OUTBOUND_FIRST_WEIGHED: 'Yükleme alanına yönlendirme bekliyor',
            OUTBOUND_LOADING_ASSIGNED_TO_SILO: 'Yükleme alanına yönlendirildi',
            OUTBOUND_SECOND_WEIGHING_WAITING: '2. tartım bekliyor',
            OUTBOUND_COMPLETED: 'Tamamlandı',
            OUTBOUND_REJECTED: 'İptal / ret',
        };
        const outboundFlowSteps = ['Ön Bildirim', '1. Tartım', 'Yükleme', '2. Tartım', 'Tamamlandı'];
        const outboundStepIndex = (status) => ({
            OUTBOUND_PRE_NOTIFIED: 0,
            OUTBOUND_ARRIVED: 1,
            OUTBOUND_FIRST_WEIGHED: 2,
            OUTBOUND_LOADING_ASSIGNED_TO_SILO: 2,
            OUTBOUND_SECOND_WEIGHING_WAITING: 3,
            OUTBOUND_COMPLETED: 4,
            OUTBOUND_REJECTED: 1,
        }[status] ?? 0);

        const renderOutbound = (record, activeStep = null) => {
            const currentIndex = outboundStepIndex(record.status);
            const selectedStep = activeStep ?? currentIndex;
            const history = (record.history || []).map((item) => `<li><strong>${esc(item.action_name)}</strong><span>${esc(item.old_status || '-')} → ${esc(item.new_status)}</span><small>${esc(item.created_at)}</small></li>`).join('');
            const flow = outboundFlowSteps.map((label, index) => {
                const state = index < currentIndex ? 'done' : (index === currentIndex ? 'current' : 'waiting');
                return `<button class="flow-step flow-step--${state} ${index === selectedStep ? 'flow-step--selected' : ''}" type="button" data-outbound-flow-step="${index}"><span>${index === currentIndex ? '↑' : '•'}</span><strong>${esc(label)}</strong></button>`;
            }).join('');
            const actionLink = record.status === 'OUTBOUND_SECOND_WEIGHING_WAITING'
                ? `<a class="button button--small button--outbound" href="/second-weighing?outbound_id=${Number(record.outbound_id)}">2. Tartıma Git</a>`
                : `<a class="button button--small button--outbound" href="/product-operations/entry?mode=outbound&outbound_id=${Number(record.outbound_id)}">Süreci Aç</a>`;
            vehicleContent.innerHTML = `
                <div class="vehicle-detail-shell operation-row-state--outbound">
                    <div class="detail-grid">
                        <div><span>Plaka</span><strong>${esc(record.plate_number)}</strong></div>
                        <div><span>Ürün</span><strong>${esc(record.product_name)}</strong></div>
                        <div><span>Alıcı</span><strong>${esc(record.sender_display)}</strong></div>
                        <div><span>Durum</span><strong>${esc(outboundLabels[record.status] || record.status)}</strong></div>
                        <div><span>Kaynak silo</span><strong>${esc((record.silo_code || '-') + ' - ' + (record.silo_name || '-'))}</strong></div>
                        <div><span>Planlanan</span><strong>${esc(kgWithTon(record.planned_quantity_kg))}</strong></div>
                        <div><span>1. tartım</span><strong>${esc(kgWithTon(record.first_weight_kg))}</strong></div>
                        <div><span>2. tartım</span><strong>${esc(kgWithTon(record.second_weight_kg))}</strong></div>
                        <div><span>Net çıkış</span><strong>${esc(kgWithTon(record.net_quantity_kg))}</strong></div>
                    </div>
                    <div class="flow-diagram vehicle-flow vehicle-flow--outbound">${flow}</div>
                    <div class="vehicle-step-detail"><h3>${esc(outboundFlowSteps[selectedStep])}</h3><p>${esc(outboundLabels[record.status] || record.status)}</p>${actionLink}</div>
                    <div class="process-history"><span>Süreç geçmişi</span><ul>${history || '<li>Geçmiş kaydı yok.</li>'}</ul></div>
                </div>
            `;
            vehicleContent.querySelectorAll('[data-outbound-flow-step]').forEach((button) => {
                button.addEventListener('click', () => renderOutbound(record, Number(button.dataset.outboundFlowStep)));
            });
        };

        window.openOutboundProcessDetail = async (outboundId) => {
            vehicleBackdrop.hidden = false;
            vehicleModal.showModal();
            vehicleContent.innerHTML = '<p class="empty-state">Çıkış kaydı yükleniyor...</p>';
            const response = await fetch(`/outbound-process/detail?outbound_id=${encodeURIComponent(outboundId)}`, {headers: {'Accept': 'application/json'}});
            const payload = await response.json();
            if (!payload.ok) {
                vehicleContent.innerHTML = '<p class="empty-state">Çıkış kaydı bulunamadı.</p>';
                return;
            }
            renderOutbound(payload.record);
        };

        window.openOutboundProcessList = async (group) => {
            vehicleBackdrop.hidden = false;
            vehicleModal.showModal();
            vehicleContent.innerHTML = '<p class="empty-state">Çıkış listesi yükleniyor...</p>';
            const response = await fetch(`/outbound-process/list?group=${encodeURIComponent(group)}`, {headers: {'Accept': 'application/json'}});
            const payload = await response.json();
            const labels = payload.status_labels || outboundLabels;
            const rows = (payload.records || []).map((record) => `
                <div class="vehicle-list-row operation-row-state--outbound" role="button" tabindex="0" data-outbound-id="${Number(record.outbound_id)}">
                    <div class="vehicle-list-row__main">
                        <strong>${esc(record.plate_number)}</strong>
                        <span>${esc(record.product_name)}</span>
                        <span>${esc(record.sender_display)}</span>
                        <small>${esc(labels[record.status] || record.status)} / ${esc(record.updated_at)}</small>
                    </div>
                </div>
            `).join('');
            vehicleContent.innerHTML = `<div class="vehicle-list">${rows || '<p class="empty-state">Bu durumda çıkış aracı yok.</p>'}</div>`;
        };

        window.openVehicleProcessList = async (group) => {
            vehicleBackdrop.hidden = false;
            vehicleModal.showModal();
            vehicleContent.innerHTML = '<p class="empty-state">Araç listesi yükleniyor...</p>';
            const response = await fetch(`/vehicle-process/list?group=${encodeURIComponent(group)}`, {headers: {'Accept': 'application/json'}});
            const payload = await response.json();
            const rows = (payload.records || []).map((record) => {
                const canStartFlow = group === 'waiting' && ['pending', 'ürün_bildirimi'].includes(record.status || '');
                const startFlow = canStartFlow ? `
                    <form class="vehicle-list-row__actions" action="/incoming-products/start-pre-notified" method="post">
                        <input type="hidden" name="return_to" value="dashboard">
                        <input type="hidden" name="notification_id" value="${Number(record.entry_id)}">
                        <input type="hidden" name="arrival_date" value="${todayText()}">
                        <input type="hidden" name="entry_notes" value="Dashboard bekleyen araçlar listesinden akış başlatıldı.">
                        <button class="button button--small button--primary" type="submit">Akış Başlat</button>
                    </form>
                ` : '';

                return `
                    <div class="vehicle-list-row analysis-state-row ${analysisStateClass(record)}" role="button" tabindex="0" data-vehicle-entry-id="${Number(record.entry_id)}">
                        <div class="vehicle-list-row__main">
                            <strong>${esc(record.plate_number)}</strong>
                            <span>${esc(record.product_name)}</span>
                            <span>${esc(record.sender_display)}</span>
                            <small>${esc(labels[record.status] || record.status)} / ${esc(record.updated_at)}</small>
                        </div>
                        ${startFlow}
                    </div>
                `;
            }).join('');
            vehicleContent.innerHTML = `<div class="vehicle-list">${rows || '<p class="empty-state">Bu durumda araç yok.</p>'}</div>`;
        };

        document.addEventListener('click', (event) => {
            const flowNav = event.target.closest('[data-flow-nav]');
            if (flowNav) {
                event.stopPropagation();
                return;
            }

            const outboundGroupTrigger = event.target.closest('[data-outbound-group]');
            if (outboundGroupTrigger) {
                event.preventDefault();
                window.openOutboundProcessList(outboundGroupTrigger.dataset.outboundGroup);
                return;
            }

            const groupTrigger = event.target.closest('[data-vehicle-group]');
            if (groupTrigger) {
                event.preventDefault();
                window.openVehicleProcessList(groupTrigger.dataset.vehicleGroup);
                return;
            }

            const interactive = event.target.closest('a, button, input, select, textarea');
            if (interactive && !interactive.hasAttribute('data-vehicle-entry-id') && !interactive.hasAttribute('data-outbound-id')) {
                return;
            }

            const outboundDetailTrigger = event.target.closest('[data-outbound-id]');
            if (outboundDetailTrigger) {
                event.preventDefault();
                window.openOutboundProcessDetail(outboundDetailTrigger.dataset.outboundId);
                return;
            }

            const detailTrigger = event.target.closest('[data-vehicle-entry-id]');
            if (detailTrigger) {
                event.preventDefault();
                window.openVehicleProcessDetail(detailTrigger.dataset.vehicleEntryId, detailTrigger.dataset.vehicleStep ? Number(detailTrigger.dataset.vehicleStep) : null);
                return;
            }
        });

        document.querySelectorAll('[data-vehicle-process-close]').forEach((button) => button.addEventListener('click', () => {
            vehicleModal.close();
            vehicleBackdrop.hidden = true;
        }));
        vehicleBackdrop?.addEventListener('click', () => {
            vehicleModal.close();
            vehicleBackdrop.hidden = true;
        });

        const firstInvalidField = document.querySelector('.field--error input:not([disabled]), .field--error select:not([disabled]), .field--error textarea:not([disabled])');
        if (firstInvalidField) {
            setTimeout(() => firstInvalidField.focus({preventScroll: false}), 120);
        }
    })();
</script>
</body>
</html>
