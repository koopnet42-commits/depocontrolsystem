<?php

declare(strict_types=1);

return [
    'up' => [
        'ALTER TABLE delivery_notifications MODIFY status ENUM(
            "ürün_bildirimi",
            "kantara_geldi",
            "kantarda",
            "ilk_tartım_alındı",
            "analiz_bekliyor",
            "analizde",
            "analiz_yapıldı",
            "silo_belirlendi",
            "barkod_bekliyor",
            "barkod_basıldı",
            "siloya_yönlendirildi",
            "boşaltımda",
            "ikinci_tartım_bekliyor",
            "ikinci_tartım_alındı",
            "tamamlandı",
            "iptal",
            "ret",
            "pending",
            "at_weighbridge",
            "in_analysis",
            "directed_to_silo",
            "unloaded",
            "completed",
            "cancelled"
        ) NOT NULL DEFAULT "ürün_bildirimi"',
        'UPDATE delivery_notifications SET status = "analiz_yapıldı" WHERE status = "analiz_tamamlandı"',
        'UPDATE delivery_notifications SET status = "ikinci_tartım_bekliyor" WHERE status = "boşaltıldı"',
        'UPDATE delivery_notifications SET status = "kantara_geldi" WHERE status = "giriş_alındı"',
        'ALTER TABLE weighbridge_records MODIFY status ENUM(
            "entry",
            "sampled",
            "silo_assigned",
            "directed",
            "unloading",
            "unloaded",
            "completed",
            "cancelled"
        ) NOT NULL DEFAULT "entry"',
        'ALTER TABLE barcode_tickets MODIFY status ENUM("active", "in_progress", "completed", "used", "cancelled") NOT NULL DEFAULT "active"',
    ],
    'down' => [
        'ALTER TABLE barcode_tickets MODIFY status ENUM("active", "in_progress", "used", "cancelled") NOT NULL DEFAULT "active"',
        'ALTER TABLE weighbridge_records MODIFY status ENUM("entry", "sampled", "directed", "unloading", "completed", "cancelled") NOT NULL DEFAULT "entry"',
    ],
];

