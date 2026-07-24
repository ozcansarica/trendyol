# CLAUDE.md

Bu depoda çalışan asistan (ve katkıda bulunanlar) için kalıcı kurallar.

## Çalışma kuralı: her iş için dal, bitince main'e merge

**İstisnasız uygulanır.** Her yeni iş için `main`'den ayrı bir dal açılır, iş
tamamlanınca Pull Request ile `main`'e merge edilir, ardından dal kapatılır.
Ayrıntılar için `CONTRIBUTING.md`.

- Doğrudan `main`'e commit atma; her değişiklik bir dal + PR üzerinden gider.
- Dal adları: `feat/…`, `fix/…`, `chore/…`, `docs/…`.
- PR açılınca CI (`node --test`) çalışır; yeşil olmadan merge edilmez.
- `main`'e merge → GitHub Pages otomatik deploy (`.github/workflows/deploy.yml`).

## Proje yapısı

Tek sayfalık site — `index.html` tek çalışma sayfasıdır, başka sayfa/link yok.

| Yol | Açıklama |
|-----|----------|
| `index.html` | Ürün Fiyat Aralığı & Maliyet Tablosu (tek sayfa) |
| `assets/calc.js` | Hesaplama çekirdeği (tarayıcı + testler ortak kullanır) |
| `assets/urunler-data.js` | Varsayılan ürün verisi (Excel'den içe aktarıldı; kullanıcı yeni dosya yüklemezse bu kullanılır) |
| `assets/xlsx-reader.js` | Tarayıcıda .xlsx dosyası okuyup `URUNLER` formatına çeviren ayrıştırıcı (JSZip + native DOMParser/TextDecoder) |
| `assets/maliyet-xlsx-reader.js` | Maliyet Girişi için "Barkod No" + "Alış Tutarı (KDV)" kolonlu .xlsx dosyalarını barkod → maliyet eşlemesine çeviren ayrıştırıcı (xlsx-reader.js ile aynı yöntem, bilinçli olarak bağımsız modül — bkz. dosya başındaki not) |
| `assets/vendor/jszip.min.js` | Vendorlanmış JSZip (ZIP açma) — SheetJS gibi hazır kütüphaneler bazı Türkçe karakterleri (Ç, Ğ) yanlış çözdüğü için bilinçli olarak kullanılmıyor |
| `tests/calc.test.js` | Referans senaryo ve kenar durum testleri |
| `.github/workflows/ci.yml` | PR/branch testleri |
| `.github/workflows/deploy.yml` | `main` → GitHub Pages deploy (JS import'larına cache-busting sürüm etiketi de bu adımda uygulanır) |

## Hesaplama mantığı (özet)

Girdiler KDV **dahil** verilir. KDV sabit **%20**: `r = 20 / 120`.
Kargo, satış fiyatına göre otomatik kademeli: `<200₺→42₺`, `200–350₺→78₺`, `>350₺→98₺`.

**Zorunlu sipariş adedi:** Düşük fiyatlı ürünlerde Trendyol tek adet siparişe
izin vermiyor; müşteri en az belirli bir adet almak zorunda
(`zorunluSiparisAdedi()`, `index.html`): `0–25₺→6`, `25–35₺→4`, `35–50₺→3`,
`50–75₺→2`, `>75₺→1` (kural yok). Kargo bedeli olduğu gibi (sipariş başına,
adede bölünmeden) uygulanır; yalnızca `computeMaliyet()`'e verilen **satış**
ve **maliyet (alış)** tutarları bu adetle çarpılır — böylece Kâr/Kâr Oranı,
zorunlu adet kadar ürünün birlikte satıldığı siparişin gerçek toplamını
yansıtır (`satis: sellPrice * adet, alis: u.maliyet * adet`).

- Komisyon = Satış × Komisyon%
- Hizmet Bedeli = tüm satışlarda sabit **8,39₺** (`HIZMET_BEDELI_SABIT`)
- ...Oluşan KDV = ilgili tutar × `r`
- Ödenecek KDV = Satıştan KDV − (Alış + Kargo + Komisyon + Hizmet Bedeli KDV'leri); negatifse 0 (devreden)
- Stopaj = (Satış / (1 + kdv/100)) × %1
- Kâr = Satış − Alış − Kargo − Komisyon − Hizmet Bedeli − Stopaj − Ödenecek KDV
- Kâr Oranı = Kâr / Alış × 100

Her ürün için 4 fiyat aralığının komisyonuyla ayrı ayrı hesaplanır; en yüksek
kâr oranını veren aralık arayüzde 🏆 ile vurgulanır.

**Özel fiyat sorgulama:** "Güncel Fiyat ve Komisyon" hücresindeki serbest giriş
alanına (`index.html`, `.ozel-fiyat-input`) kullanıcı kendi satış fiyatını
girebilir; `tierForPrice()` bu fiyatın hangi fiyat aralığına (komisyon
dilimine) düştüğünü bulur (aralıklar f1_alt'tan f4_ust'a azalan ve bitişik
olduğundan, fiyatın karşıladığı en üst aralık kullanılır) ve `ozelFiyatHesapla()`
o aralığın komisyonuyla (zorunlu sipariş adedi dahil) anlık kâr hesaplar.

Hesaplama kuralını değiştirirken önce `tests/calc.test.js`, sonra `assets/calc.js`.

## Yeni ürün eklemek/güncellemek

**Haftalık güncelleme (kullanıcı için):** Sayfadaki **Excel Yükle** butonuyla yeni
`.xlsx` dosyası seçilir; veriler tarayıcıda ayrıştırılıp `localStorage`'da saklanır,
sayfa yenilense de kalıcı kalır. En son yüklenen dosya geçerli veri kaynağıdır;
varsayılana dönme seçeneği yoktur — yeniden varsayılan veriyi kullanmak için
kullanıcı bu tarayıcının localStorage'ını temizlemelidir. Beklenen format:
Trendyol "KomisyonTarifeleriÜrünleri" export'u (kolon adları `assets/xlsx-reader.js`
içindeki `REQUIRED_COLUMNS`'da listelidir; sıra önemli değildir, isimle eşleştirilir).

**Depodaki varsayılan veriyi güncellemek (kalıcı, kod değişikliği):**
`assets/urunler-data.js` içindeki `URUNLER` dizisini düzenleyin (alanlar:
`maliyet`, `f1_alt/f2_ust/f2_alt/f3_ust/f3_alt/f4_ust`, `tarifeler`,
`komisyon_fiyat`, `guncel_komisyon`, `guncel_tsf`).

**Tarife grupları (3 Gün / 4 Gün / 7 Gün…):** Excel'deki "Tarih aralığı (N Gün)"
kolonları sabit değil, dinamik olarak algılanır (`assets/xlsx-reader.js`,
`TARIH_ARALIGI_REGEX`). Bazı haftalarda 3 Gün + 4 Gün, bazılarında yalnızca
7 Gün gibi tek bir grup gelebilir; hepsi otomatik desteklenir. Her ürünün
tarife verisi `u.tarifeler["<gün>"] = { tarih, k: [k1,k2,k3,k4] }` şeklinde
saklanır; arayüzdeki tarife sekmeleri (`index.html`, `updateTarifeAvailability()`)
bu anahtarlardan dinamik olarak üretilir.

## Maliyet Girişi (kalıcı maliyet kaydı)

Ürün maliyetleri fiyat/komisyondan bağımsız olarak **bir kere** girilip barkoda
göre kalıcı saklanabilir: sayfadaki **💰 Maliyet Girişi** paneli, her ürün için
düzenlenebilir bir maliyet alanı gösterir. Girilen değer `localStorage`'da
(`trendyol_maliyet_kayitlari_v1`, barkod → maliyet eşlemesi) saklanır ve hem
anlık olarak hem de **her yeni Excel yüklemesinde** (`applyMaliyetKayitlari()`)
barkoda göre otomatik uygulanır — bu sayede haftalık Excel'in kendi Maliyet
kolonu farklı/eksik olsa bile (veya hiç yoksa) kullanıcının bir kere girdiği maliyet geçerli
kalır. Eşleştirme anahtarı: barkod (yoksa model kodu, o da yoksa ürün adı).

**Excel ile toplu maliyet yükleme:** Maliyet Girişi panelindeki **📤 Excel ile
Toplu Yükle** butonuyla "Barkod No" + "Alış Tutarı (KDV)" kolonlu bir .xlsx
dosyası (ör. Trendyol "Aktif-Pasif Ürün" export'u) yüklenebilir
(`assets/maliyet-xlsx-reader.js`, `parseMaliyetXlsx()`). Dosyadaki tüm
barkod → maliyet eşlemesi tek seferde `trendyol_maliyet_kayitlari_v1`'e
kaydedilir (`saveMaliyetKayitlariBulk()`) ve barkodu eşleşen ürünlerin
maliyeti anında güncellenir; eşleşmeyen barkodlar da kayıt defterinde
saklı kalır ve o barkoda sahip bir ürün ileride yüklenirse otomatik uygulanır.
