# Trendyol Ürün Fiyat Aralığı & Maliyet Tablosu

Trendyol satıcıları için ürün bazlı maliyet ve kâr tablosu. Excel'den içe
aktarılan her ürün için 4 fiyat aralığının tamamını, her aralığın komisyon
oranını ve maliyete göre **kâr** ile **kâr oranını** tek tabloda gösterir.

**Canlı:** https://ozcansarica.github.io/trendyol/

## Girdiler (ürün başına)
- Maliyet (Alış fiyatı)
- 4 fiyat aralığının sınırları ve her aralığın komisyon oranı (tarife grupları
  Excel'den dinamik algılanır: 3 Günlük / 4 Günlük, ya da bazı haftalarda
  yalnızca 7 Günlük gibi tek bir grup)
- Güncel satış fiyatı ve komisyonu

KDV sabit **%20**, kargo satış fiyatına göre otomatik kademeli hesaplanır:
`<200₺ → 42₺`, `200–350₺ → 78₺`, `>350₺ → 98₺`.

Düşük fiyatlı ürünlerde Trendyol tek adet siparişe izin vermiyor; müşteri en
az zorunlu sipariş adedi kadar almak zorunda (`0–25₺→6`, `25–35₺→4`,
`35–50₺→3`, `50–75₺→2` adet). Kargo bedeli olduğu gibi kalır; maliyet ve satış
tutarı bu adetle çarpılarak o siparişin gerçek kâr/kâr oranı hesaplanır.

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

Tek tek girmek yerine panelin **📤 Excel ile Toplu Yükle** butonuyla "Barkod No"
+ "Alış Tutarı (KDV)" kolonlu bir .xlsx dosyası da yüklenebilir; tüm satırlar
barkoda göre tek seferde kaydedilir.

## Çıktılar (aralık başına)
- Fiyat aralığı ve komisyon oranı
- Kâr (₺) ve Kâr Oranı (%)
- En yüksek kâr oranını veren aralık 🏆 ile vurgulanır

## Özel fiyat sorgulama

"Güncel Fiyat ve Komisyon" kutusundaki alana istediğiniz bir satış fiyatını
girebilirsiniz; hangi fiyat aralığına (komisyon dilimine) düştüğü otomatik
bulunur ve o aralığın komisyonuyla anlık kâr hesaplanır.

## Yıldızlı Ürün Fiyatları

**⭐ Yıldızlı Ürün Fiyatları** panelinden Trendyol'un "Yıldızlı Ürün
Etiketleri" export'u yüklenebilir. Dosyada "Komisyon Oranı" kolonu varsa
doğrudan kullanılır; yoksa her 1/2/3 yıldız fiyat noktası için kâr, o fiyatın
düştüğü mevcut fiyat aralığının komisyonuyla otomatik hesaplanır. Maliyet,
ana ürün listesinden ya da Maliyet Girişi'ne daha önce kaydedilmiş
maliyetlerden bulunur — ürün şu an ana tabloda olmasa bile çalışabilir. Ana
tabloyu, Maliyet Girişi'ni veya diğer hiçbir hesaplamayı etkilemez —
tamamen ayrı, salt-okunur bir görünümdür.

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
- `assets/xlsx-reader.js` — tarayıcıda .xlsx okuyan ayrıştırıcı (ürün verisi)
- `assets/maliyet-xlsx-reader.js` — tarayıcıda .xlsx okuyan ayrıştırıcı (toplu maliyet yükleme)
- `assets/yildizli-urun-xlsx-reader.js` — tarayıcıda .xlsx okuyan ayrıştırıcı (Yıldızlı Ürün Fiyatları)
- `assets/vendor/jszip.min.js` — Excel yükleme için vendorlanmış JSZip
- `tests/` — testler
