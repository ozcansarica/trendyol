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

İki sayfalık statik site. `index.html` ana çalışma sayfasıdır; `indirim/` altındaki
barem indirimi sayfası ondan bağımsız açılır ve **aynı localStorage'ı paylaşır**
(ürün verisi, maliyet kayıtları, kargo/hizmet ayarı). Ortak mantık `assets/`
altındaki modüllerde tek kaynaktan tutulur — iki sayfaya kopyalanmaz.

| Yol | Açıklama |
|-----|----------|
| `index.html` | Ürün Fiyat Aralığı & Maliyet Tablosu (ana sayfa) |
| `indirim/index.html` | ⬇️ Barem İndirimi sayfası (ana sayfadan link ile açılır) |
| `assets/calc.js` | Hesaplama çekirdeği (tarayıcı + testler ortak kullanır): `computeMaliyet()`, `zorunluSiparisAdedi()`, `baremIndirimAnalizi()`, `kargoKademesi()`, `tarifeTierleri()`, `tarifeTierForPrice()` |
| `assets/ortak.js` | İki sayfanın ortak kullandığı localStorage anahtarları, veri okuma yardımcıları (`readUrunler`, `readKargoHizmet`, `applyMaliyetKayitlari`) ve `tl`/`pc` biçimlendiricileri |
| `assets/xlsx-writer.js` | Satır dizisinden .xlsx Blob üreten yazıcı (`buildXlsxBlob`) — iki sayfa da dışa aktarımda kullanır |
| `assets/urunler-data.js` | Varsayılan ürün verisi (Excel'den içe aktarıldı; kullanıcı yeni dosya yüklemezse bu kullanılır) |
| `assets/xlsx-reader.js` | Tarayıcıda .xlsx dosyası okuyup `URUNLER` formatına çeviren ayrıştırıcı (JSZip + native DOMParser/TextDecoder) |
| `assets/maliyet-xlsx-reader.js` | Maliyet Girişi için "Barkod No" + "Alış Tutarı (KDV)" kolonlu .xlsx dosyalarını barkod → maliyet eşlemesine çeviren ayrıştırıcı (xlsx-reader.js ile aynı yöntem, bilinçli olarak bağımsız modül — bkz. dosya başındaki not) |
| `assets/yildizli-urun-xlsx-reader.js` | Trendyol "YıldızlıÜrünEtiketleri" export'unu (komisyon oranı içermez, sadece 1/2/3 yıldız fiyat noktaları) barkod → yıldız fiyatları eşlemesine çeviren ayrıştırıcı (aynı yöntem, bağımsız modül) |
| `assets/vendor/jszip.min.js` | Vendorlanmış JSZip (ZIP açma) — SheetJS gibi hazır kütüphaneler bazı Türkçe karakterleri (Ç, Ğ) yanlış çözdüğü için bilinçli olarak kullanılmıyor |
| `tests/calc.test.js` | Referans senaryo ve kenar durum testleri |
| `.github/workflows/ci.yml` | PR/branch testleri |
| `.github/workflows/deploy.yml` | `main` → GitHub Pages deploy (JS import'larına cache-busting sürüm etiketi de bu adımda, **tüm `*.html` dosyalarına** uygulanır) |

## Hesaplama mantığı (özet)

Girdiler KDV **dahil** verilir. KDV sabit **%20**: `r = 20 / 120`.
Kargo, **sipariş toplamına** göre otomatik kademeli: `<200₺→42₺`, `200–350₺→78₺`,
`>350₺→98₺`. Kargo bedeli gönderi başına bir kez alındığından kademe de birim
fiyata değil siparişin tamamına (birim fiyat × zorunlu sipariş adedi) bakar
(`kargoIcin()`, `index.html`). Kademeler `📦 Kargo/Hizmet Tanımı` panelinden
değiştirilebilir; varsayılan eşiklerde zorunlu adetli en yüksek sipariş toplamı
150₺ olduğundan bu ayrım ancak eşik 150₺'nin altına çekilirse fark yaratır.

**Zorunlu sipariş adedi:** Düşük fiyatlı ürünlerde Trendyol tek adet siparişe
izin vermiyor; müşteri en az belirli bir adet almak zorunda
(`zorunluSiparisAdedi()`, `assets/calc.js`): `0–25₺→6`, `25–35₺→4`, `35–50₺→3`,
`50–75₺→2`, `>75₺→1` (kural yok). Kargo bedeli olduğu gibi (sipariş başına,
adede bölünmeden) uygulanır; yalnızca `computeMaliyet()`'e verilen **satış**
ve **maliyet (alış)** tutarları bu adetle çarpılır — böylece Kâr/Kâr Oranı,
zorunlu adet kadar ürünün birlikte satıldığı siparişin gerçek toplamını
yansıtır (`satis: sellPrice * adet, alis: u.maliyet * adet`).

- Komisyon = Satış × Komisyon%
- Hizmet Bedeli = tüm satışlarda sabit **13,19₺** (`HIZMET_BEDELI_SABIT`, `assets/calc.js`;
  `📦 Kargo/Hizmet Tanımı` panelinden değiştirilebilir, `hizmetAyar` olarak saklanır)
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

## Barem İndirimi (`indirim/index.html`)

Kargo kademesi ve komisyon fiyat aralıkları **eşik bazlı** arttığından, bir
eşiğin hemen üstünde kalan satış fiyatını eşiğin altına çekmek kimi üründe
fiyattan kaybedileni kargo/komisyondan fazlasıyla geri kazandırır: 240₺'lik bir
sipariş 199₺'ye indirilince kargo 78₺ → 42₺ düşer, komisyon da satışla orantılı
azalır. Panel bu senaryoları tarayıp mevcut durumla karşılaştırır.

Çekirdek `baremIndirimAnalizi()` (`assets/calc.js`) saf bir fonksiyondur; kargo,
komisyon ve adet bilgisini enjekte edilen fonksiyonlardan alır. Aday eşikler
iki kapsamda tanımlanır (`baremEsikleri()`, `index.html`):

| Kapsam | Eşik kaynağı | Hedef fiyat |
|--------|--------------|-------------|
| `'toplam'` | Kargo kademesi eşikleri (`kargoAyar.esik1/esik2`) — sipariş toplamına bakar | Eşik − `BAREM_MARJI` (1₺): `200₺ → 199₺` |
| `'birim'` | Ürünün komisyon fiyat aralıklarının üst sınırları (`f2_ust/f3_ust/f4_ust`) — birim fiyata bakar | Aralığın üst sınırının kendisi ("X ₺ ve altı" tanımlı olduğu için) |

`'toplam'` kapsamda hedef sipariş toplamı adede bölünerek birim fiyata çevrilir;
birim fiyat düşünce zorunlu sipariş adedi artabildiğinden sabit noktaya
yakınsanır ve kuruş küsuratı **aşağı** kırpılır (aksi halde toplam eşiği aşardı).
Yakınsamayan eşik elenir.

Her aday, mevcut durumla aynı formülle (zorunlu adet dahil sipariş toplamı
üzerinden) hesaplanıp **kâr farkına** göre sıralanır; mevcut durum ürünün kendi
`guncel_komisyon`'uyla, adaylar ise indirimli fiyatın düştüğü aralığın
komisyonuyla (`tierForPrice()`) hesaplanır. `maxIndirimYuzdesi` (panelde
**En fazla indirim**, varsayılan %20, `trendyol_barem_ayar_v1`'de saklanır) bundan
fazla indirim gerektiren eşikleri eler — amaç yakın bir bareme yuvarlamak, fiyatı
dibe çekmek değil. Sayfa her ürünün her adayını ayrı satır olarak gösterir,
seçilenler Excel'e aktarılabilir.

Sayfa localStorage'ı yalnızca **okur** (kendi `trendyol_barem_ayar_v1` anahtarı
hariç): ürün verisini, maliyet kayıtlarını ve kargo/hizmet ayarını ana sayfanın
kaydettiği şekilde alır, hiçbirini değiştirmez. Tarife seçimi sayfanın kendi
sekmelerinden yapılır ve komisyon aralıkları buna göre çözülür. Ana sayfadaki
hesaplamaları, Maliyet Girişi'ni veya diğer panelleri etkilemez.

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

## Yıldızlı Ürün Fiyatları (ayrı panel, mevcut sistemi etkilemez)

Trendyol'un "Yıldızlı Ürün Etiketleri" export'u **💰 Maliyet Girişi**'nin
yanındaki **⭐ Yıldızlı Ürün Fiyatları** panelinden yüklenir
(`assets/yildizli-urun-xlsx-reader.js`, `parseYildizliUrunXlsx()`). Her
yıldız seviyesi için "Üst Fiyat" (o seviyeyi en az indirimle karşılayan fiyat)
kullanılır. İki şablon desteklenir:
- **Yeni şablon** (`Komisyon Oranı` kolonu var): komisyon doğrudan bu
  kolondan alınır — ürünün ana ürün listesinde olması gerekmez.
- **Eski şablon** (`Komisyon Oranı` kolonu yok): komisyon, fiyatın ürünün
  zaten yüklü 4 fiyat aralığından hangisine düştüğü `tierForPrice()` ile
  bulunup oradan alınır (özel fiyat sorgulama ile aynı mantık) — bunun için
  ürünün barkodu ana ürün listesinde (`activeUrunler`) olmalıdır.

Maliyet, önce ürün ana listede varsa oradan, yoksa kalıcı Maliyet Girişi
kayıt defterinden (`trendyol_maliyet_kayitlari_v1`, barkoda göre —
`yildizMaliyetBul()`) aranır; böylece ürün şu an ana tabloda olmasa bile,
daha önce girilmiş/toplu yüklenmiş bir maliyeti varsa yine kâr hesaplanabilir.
Maliyet ya da komisyon hiçbir yerde bulunamazsa (barkod hem ana listede hem
maliyet kayıtlarında yoksa, ve dosyada da komisyon oranı yoksa) o satır için
yalnızca fiyat noktası gösterilir, kâr hesaplanmaz.

Veri `trendyol_yildizli_urun_v1`'de barkoda göre saklanır ve panelde
**canlı olarak** `activeUrunler` ile eşleştirilir — ürün nesnelerine
yazılmadığından ana tabloyu, Maliyet Girişi'ni veya diğer hesaplamaları
hiçbir şekilde etkilemez.
