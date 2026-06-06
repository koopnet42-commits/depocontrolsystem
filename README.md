# Depo Otomasyon Sistemi

Web tabanlı depo otomasyon sistemi için temel proje iskeleti.

Bu aşamada yalnızca altyapı hazırlandı:

- Klasör yapısı
- Basit MVC akışı
- PDO tabanlı veritabanı bağlantı katmanı
- Model sınıfları
- Migration çalıştırma yapısı
- Sol menü
- Boş modül kartlarından oluşan dashboard

## Gereksinimler

- PHP 8.2+
- MySQL veya MariaDB

## Kurulum

1. `.env.example` dosyasını `.env` olarak kopyalayın.
2. Veritabanı bilgilerini `.env` içinde güncelleyin.
3. Geliştirme sunucusunu başlatın:

```bash
php -S 127.0.0.1:8000 -t public public/index.php
```

4. Tarayıcıdan açın:

```text
http://127.0.0.1:8000
```

## Veritabanı Migration

Migration dosyalarını çalıştırmak için:

```bash
php scripts/migrate.php
```

Son çalıştırılan migration dosyasını geri almak için:

```bash
php scripts/migrate.php rollback
```

## Klasörler

```text
app/
  Controllers/   Sayfa isteklerini karşılayan controller sınıfları
  Core/          Router, controller tabanı ve veritabanı bağlantısı
  Models/        Veritabanı tablo modelleri ve ilişki tanımları
  Views/         Layout, bileşen ve sayfa şablonları
bootstrap/       Ortak uygulama başlangıç dosyası
config/          Uygulama ve veritabanı ayarları
database/        Migration dosyaları
data/            Modül listesi gibi sabit uygulama verileri
public/          Web kök dizini, CSS ve ön kontrolcü
routes/          Web rota tanımları
scripts/         CLI yardımcı komutları
storage/         Log ve geçici dosyalar için ayrılmış alan
```
