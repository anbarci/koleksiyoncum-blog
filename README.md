# Koleksiyoncum — SEO Blog & Affiliate Yönetici

## 🚀 Kurulum (YunoHost)

1. YunoHost panelinden **Custom Webapp** uygulamasını kur
2. Bu klasörün içindekileri sunucuya yükle
3. `index.php` içinde şifreyi değiştir:
   ```php
   define('ADMIN_PASS', 'YENİ_ŞİFRENİZ');
   ```
4. SITE_URL'yi kendi domain'inle değiştir (isteğe bağlı):
   ```php
   define('SITE_URL', 'https://koleksiyoncum.com');
   ```

## 📁 Dosyalar

| Dosya | Açıklama |
|-------|----------|
| `index.php` | Tüm uygulama tek dosyada |
| `.htaccess` | SEO URL yönlendirme |
| `posts.json` | Yazı veritabanı (otomatik oluşur) |

## 🔗 Önemli URL'ler

| Sayfa | URL |
|-------|-----|
| Ana sayfa | `https://siten.com/` |
| Yönetici | `https://siten.com/?section=admin` |
| Sitemap | `https://siten.com/sitemap.xml` |
| Robots | `https://siten.com/robots.txt` |
| RSS | `https://siten.com/rss.xml` |

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

## 🔐 Varsayılan Şifre

`koleksiyoncum2026` — **Mutlaka değiştirin!**
