# Trendyol Maliyet & Kâr Hesaplama

Trendyol satıcıları için maliyet ve kâr hesaplama aracı. Satış/alış fiyatı,
komisyon, KDV ve kargo bilgisinden **kâr**, **kâr oranı**, **ROI**, **stopaj**,
**hizmet bedeli** ve tüm **KDV dağılımını** hesaplar.

**Canlı:** https://ozcansarica.github.io/trendyol/

## Girdiler
- Ürün Satış Fiyatı (₺, KDV dahil)
- Ürün Alış Fiyatı (₺, KDV dahil)
- Komisyon %
- KDV %
- Kargo Ücreti (₺, KDV dahil)
- Kargo Tipi: Satıcıya Ait / İhracat / Aynı Gün

## Çıktılar
- Komisyon, Hizmet Bedeli, Stopaj Bedeli
- Kâr, Kâr Oranı, Yatırım Geri Dönüş Oranı (ROI), Ödenecek KDV
- KDV dağılımı: Satıştan / Alıştan / Kargodan / Komisyondan / Hizmet Bedelinden
  Oluşan KDV ve Ödenecek KDV
- Satış dağılımı grafiği

## Geliştirme

```bash
npm test          # hesaplama testleri (node --test)
```

Statik site; derleme gerektirmez. Yerelde açmak için basit bir sunucu kullanın
(ES modülü içe aktarımı `file://` üzerinde çalışmaz):

```bash
python3 -m http.server 8000   # sonra http://localhost:8000
```

## Çalışma kuralı

Her iş ayrı bir dalda yapılır, `main`'e PR ile merge edilir. Bkz. `CONTRIBUTING.md`.

## İçerik
- `index.html` — maliyet & kâr hesaplama arayüzü
- `assets/calc.js` — hesaplama çekirdeği
- `trendyol_komisyon_hesaplayici.html` — ürün bazlı komisyon/tarife karşılaştırma
- `tests/` — testler
