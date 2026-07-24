# Trendyol Ürün Fiyat Aralığı & Maliyet Tablosu

Trendyol satıcıları için ürün bazlı maliyet ve kâr tablosu. Excel'den içe
aktarılan her ürün için 4 fiyat aralığının tamamını, her aralığın komisyon
oranını ve maliyete göre **kâr** ile **kâr oranını** tek tabloda gösterir.

**Canlı:** https://ozcansarica.github.io/trendyol/

## Girdiler (ürün başına)
- Maliyet (Alış fiyatı)
- 4 fiyat aralığının sınırları ve her aralığın komisyon oranı (3 Günlük / 4 Günlük tarife)
- Güncel satış fiyatı ve komisyonu

KDV sabit **%20**, kargo satış fiyatına göre otomatik kademeli hesaplanır:
`<200₺ → 42₺`, `200–350₺ → 78₺`, `>350₺ → 98₺`.

## Ürünleri güncelleme (haftalık)

Ürünler her hafta değişebildiği için sayfadaki **Excel Yükle** butonuyla yeni
`.xlsx` dosyası seçilebilir. Veriler tarayıcıda anında ayrıştırılıp tabloya
yansır ve bu tarayıcıda kalıcı olarak saklanır (sayfa yenilense de kaybolmaz).
En son yüklenen dosya geçerli veri kaynağıdır.

## Maliyet Girişi (bir kere gir, otomatik eşleşsin)

Maliyetler genelde haftalık değişmediği için **💰 Maliyet Girişi** panelinden
her ürünün maliyeti bir kere girilebilir. Girilen maliyet barkoda göre
kaydedilir ve her yeni Excel yüklemesinde otomatik olarak eşleşip uygulanır.
Haftalık Excel'de artık ayrı bir Maliyet kolonu bulunması gerekmez.

## Çıktılar (aralık başına)
- Fiyat aralığı ve komisyon oranı
- Kâr (₺) ve Kâr Oranı (%)
- En yüksek kâr oranını veren aralık 🏆 ile vurgulanır

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
- `index.html` — ürün fiyat aralığı & maliyet tablosu (tek sayfa)
- `assets/calc.js` — hesaplama çekirdeği
- `assets/urunler-data.js` — varsayılan ürün verisi (Excel'den içe aktarıldı)
- `assets/xlsx-reader.js` — tarayıcıda .xlsx okuyan ayrıştırıcı
- `assets/vendor/jszip.min.js` — Excel yükleme için vendorlanmış JSZip
- `tests/` — testler
