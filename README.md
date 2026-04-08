# Koleksiyoncum — SEO Blog & Affiliate Yönetici

## 🚀 Kurulum (YunoHost — Resmi Paket)

YunoHost 12+ destekli sunucuda:

```bash
yunohost app install https://github.com/anbarci/koleksiyoncum_ynh
```

Kurulum sırasında domain, URL yolu ve yönetici şifresi sorulur.  
Şifre otomatik olarak bcrypt ile hashlenir ve `/var/www/koleksiyoncum/config.php` dosyasına yazılır.

### 📦 Paket yapısı

| Dosya / Dizin | Açıklama |
|---|---|
| `manifest.toml` | YunoHost paket tanımı |
| `scripts/install` | Kurulum scripti |
| `scripts/remove` | Kaldırma scripti |
| `scripts/upgrade` | Güncelleme scripti |
| `scripts/backup` | Yedekleme scripti |
| `scripts/restore` | Geri yükleme scripti |
| `conf/nginx.conf` | Nginx yapılandırma şablonu |
| `conf/config.php` | PHP yapılandırma şablonu |
| `conf/extra_php-fpm.conf` | PHP-FPM ek ayarları |

### 📁 Sunucu Dizin Yapısı (Kurulum Sonrası)

| Yol | Açıklama |
|---|---|
| `/var/www/koleksiyoncum/` | Uygulama dosyaları (`index.php`, `config.php`) |
| `/home/yunohost.app/koleksiyoncum/posts.json` | Yazı veritabanı |
| `/etc/nginx/conf.d/<domain>.d/koleksiyoncum.conf` | Nginx config |
| `/etc/php/8.3/fpm/pool.d/koleksiyoncum.conf` | PHP-FPM pool |

---

## 🖥️ Manuel Kurulum (YunoHost olmadan)

1. Dosyaları web sunucusu dizinine kopyala
2. `index.php` — **YunoHost yoksa `config.php` olmadan da çalışır**, varsayılan şifre kullanılır
3. Varsayılan şifreyi değiştirmek için `config.php` oluştur:
   ```php
   <?php
   define('ADMIN_PASS_HASH', password_hash('YENİ_ŞİFRENİZ', PASSWORD_BCRYPT));
   define('DATA_DIR', __DIR__);
   ```

---

## 🔗 Önemli URL'ler

| Sayfa | URL |
|-------|-----|
| Ana sayfa | `https://siten.com/blog/` |
| Yönetici | `https://siten.com/blog/?section=admin` |
| Sitemap | `https://siten.com/blog/sitemap.xml` |
| Robots | `https://siten.com/blog/robots.txt` |
| RSS | `https://siten.com/blog/rss.xml` |

## 📍 Google İndeksleme

1. Siteyi ayağa kaldır
2. [Google Search Console](https://search.google.com/search-console) aç
3. Domain ekle → `sitemap.xml` URL'sini gir
4. Google 1-7 gün içinde tüm yazıları indeksler

## ✏️ SEO İpuçları

- Her yazıya **SEO Başlığı** (60 karakter) ve **SEO Açıklaması** (160 karakter) gir
- URL slug'ı Türkçe anahtar kelimelerle oluştur: `en-iyi-jean-2026`
- Yazılar en az **300 kelime** olsun (Google kaliteli içerik sever)
- Affiliate linklerini içeriğin içine doğal yerleştir

## 🔐 Varsayılan Şifre (Yalnızca Manuel Kurulum)

`koleksiyoncum2026` — **YunoHost kurulumunda kurulum sırasında belirlenir.**
