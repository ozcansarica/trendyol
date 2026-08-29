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

Statik site, üç bölüm. `index.html` ana çalışma sayfasıdır; `indirim/` altındaki
barem indirimi sayfası ondan link ile açılır ve **aynı localStorage'ı paylaşır**
(ürün verisi, maliyet kayıtları, kargo/hizmet ayarı). `kargo-barem/` ise
**tamamen bağımsız** bir araçtır: kendi Excel şablonu, kendi yükleme sayfası ve
kendi localStorage anahtarı vardır; ana sayfadan link verilmez ve verisi ana
sayfayla paylaşılmaz. Ortak *mantık* `assets/` altındaki modüllerde tek
kaynaktan tutulur — sayfalara kopyalanmaz.

| Yol | Açıklama |
|-----|----------|
| `index.html` | Ürün Fiyat Aralığı & Maliyet Tablosu (ana sayfa) |
| `indirim/index.html` | ⬇️ Barem İndirimi sayfası (ana sayfadan link ile açılır) |
| `kargo-barem/index.html` | 📦 Kargo Barem tablosu (bağımsız araç — link verilmez, doğrudan URL ile açılır) |
| `kargo-barem/yukle.html` | 📤 Kargo Barem'in kendi ürün yükleme sayfası |
| `assets/aktif-pasif-xlsx-reader.js` | Trendyol "Aktif-Pasif Ürün" export'unu (Barkod No · Ürün Adı · Alış/Satış Tutarı (KDV) · Komisyon Oranı · Ürün Resim) ürün listesine çeviren ayrıştırıcı — Kargo Barem'in tek veri kaynağı (aynı yöntem, bağımsız modül) |
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

Bir fiyatı barem eşiğinin altına çekince kârın ne olduğunu gösterir. Sayfa
ürünleri **zorunlu sipariş adedinin devreye girdiği 75₺ sınırına göre ikiye
ayırır**; iki grubun barem türü farklıdır:

| Grup | Kullanılan baremler |
|------|---------------------|
| **75₺ ve altı** (zorunlu adet > 1) | Zorunlu sipariş adedi eşikleri **+** kargo kademesi eşikleri |
| **75₺ üzeri** (tek adet satılabilir) | Yalnızca kargo kademesi eşikleri |

Grup sınırı sabit yazılmaz, `ZORUNLU_ADET_BAREMLERI`'nin en üst eşiğinden okunur.

**İki eşik türü, farklı tutara bakar** (`kapsam` alanı, `baremIndirimAnalizi()`):

| Kapsam | Eşik kaynağı | Baktığı tutar | Hedef fiyat |
|--------|--------------|---------------|-------------|
| `'birim'` | Zorunlu sipariş adedi (`ZORUNLU_ADET_BAREMLERI`: 25/35/50/75₺) | Birim fiyat | Eşiğin kendisi — kural "X ₺ ve altı" (50₺ → 3 adet) |
| `'toplam'` | Kargo kademesi (`kargoAyar.esik1/esik2`, varsayılan 200/350₺) | Sipariş toplamı | Eşik − `BAREM_MARJI` (1₺): 200₺ → 199₺ |

Adet baremi mantığı: fiyatı bir alt bareme çekmek müşteriyi daha fazla adet
almaya zorlar, kargo yine tek sefer alınır — ör. 60₺'lik ürün 50₺'ye çekilince
zorunlu adet 2 → 3 olur, sipariş toplamı 120₺ → 150₺ çıkar. Kâr, ek adetlerin
maliyeti ciro artışını yemediği sürece artar; yani birim marjı yüksek ürünlerde
işe yarar.

`'toplam'` kapsamda hedef sipariş toplamı adede bölünerek birim fiyata çevrilir;
birim fiyat düşünce zorunlu adet artabildiğinden sabit noktaya yakınsanır ve
kuruş küsuratı **aşağı** kırpılır (aksi halde toplam eşiği aşardı). Yakınsamayan
eşik elenir.

`ZORUNLU_ADET_BAREMLERI` (`assets/calc.js`) eşik → adet tablosunun tek
kaynağıdır; `zorunluSiparisAdedi()` de bu tablodan okur.

**Komisyon tarifesiyle ilgisi yoktur.** Ürünün `guncel_komisyon`'u hem mevcut hem
indirimli senaryoda aynen kullanılır; indirimli fiyat başka bir tarife aralığına
düşse bile oran değişmez. Komisyon fiyat aralıkları (1.–4. aralık) ana tablonun
konusudur, bu sayfanın değil — bu yüzden sayfada tarife seçimi de yoktur.

Her ürünün her barem adayı ayrı satır olur ve grup içinde **kâr farkına** göre
sıralanır; kârı düşüren adaylar da listelenir (filtre yoktur), seçilenler
Excel'e aktarılabilir.

Sayfa localStorage'ı yalnızca **okur**: ürün verisini, maliyet kayıtlarını ve
kargo/hizmet ayarını ana sayfanın kaydettiği şekilde alır, hiçbirini değiştirmez.
Ana sayfadaki hesaplamaları, Maliyet Girişi'ni veya diğer panelleri etkilemez.

## Kargo Barem (`kargo-barem/`, bağımsız araç)

Sitedeki diğer sayfalardan **bilinçli olarak kopuk** bir araçtır: ana sayfadan
link verilmez, ana sayfanın ürün verisini/maliyet kayıtlarını/kargo ayarını ne
okur ne yazar. Kendi verisini `trendyol_kargo_barem_urunler_v1` anahtarında
tutar ve kargo/hizmet için `ortak.js`'teki **varsayılanları** kullanır. Yalnızca
hesaplama modülleri (`calc.js`, `ortak.js`) ortaktır.

| Sayfa | İş |
|-------|-----|
| `kargo-barem/yukle.html` | "Aktif-Pasif Ürün" .xlsx yükler (`parseAktifPasifXlsx()`), şablonu gösterir, atlanan satırları listeler, yüklü veriyi silmeye izin verir |
| `kargo-barem/index.html` | Tabloyu gösterir; veri yoksa yükleme sayfasına yönlendirir |

Şablon (kolonlar isimle eşleştirilir, sıra önemsizdir; zorunlular **kalın**):
**Barkod No** · Ürün Adı · Alış Tutarı (KDV) · **Satış Tutarı (KDV)** ·
**Komisyon Oranı** · Ürün Resim. Alış tutarı boşsa ürün listelenir ama kâr
hesaplanmaz.

Tablo her ürün için iki kargo eşiğini ayrı ayrı, her biri **iki sütun** hâlinde
gösterir (`kargoEsikAdetleri()`, `assets/calc.js`):

| Sütun | İçerik |
|-------|--------|
| **Geçiş Adedi** | Sipariş toplamının o eşiği geçtiği en küçük adet; toplam, kargonun nereden nereye çıktığı ve o adetteki kâr |
| **Altında Kalmak İçin İndirim** | Aynı adette baremin altında kalmak için **sipariş toplamından** düşülecek indirim (₺ ve %), indirim sonrası toplam/kargo ve indirimli kâr |

İndirim birim fiyata değil **sepet toplamına** uygulanır; bu yüzden hedef tutar
tam tutturulur (birim fiyata bölüp aşağı kırpmaya gerek kalmaz). Hedef, eşiğin
`kargoKademesi()`'deki kendi kuralına göre belirlenir: ikinci kademeye girmemek
için toplam `esik1`'in **altında** olmalı (hedef = `esik1 − BAREM_MARJI`,
200₺ → 199₺); üçüncü kademeye girmemek için `esik2`'ye **eşit** olabilir
(hedef = tam 350₺). Gösterilen kargo, o adetteki **gerçek** kademedir —
pahalı üründe tek adet artışı iki eşiği birden geçebilir (199₺ × 2 = 398₺ →
kargo doğrudan 42₺'den 98₺'ye).

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
