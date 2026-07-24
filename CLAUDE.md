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
| `assets/urunler-data.js` | Excel'den içe aktarılan ürün verisi (fiyat aralıkları, komisyonlar, maliyet) |
| `tests/calc.test.js` | Referans senaryo ve kenar durum testleri |
| `.github/workflows/ci.yml` | PR/branch testleri |
| `.github/workflows/deploy.yml` | `main` → GitHub Pages deploy (JS import'larına cache-busting sürüm etiketi de bu adımda uygulanır) |

## Hesaplama mantığı (özet)

Girdiler KDV **dahil** verilir. KDV sabit **%20**: `r = 20 / 120`.
Kargo, satış fiyatına göre otomatik kademeli: `<200₺→42₺`, `200–350₺→78₺`, `>350₺→98₺`.

- Komisyon = Satış × Komisyon%
- Hizmet Bedeli = satış tutarı aralığına göre sabit tablo (`HIZMET_BEDELI_TABLOSU`)
- ...Oluşan KDV = ilgili tutar × `r`
- Ödenecek KDV = Satıştan KDV − (Alış + Kargo + Komisyon + Hizmet Bedeli KDV'leri); negatifse 0 (devreden)
- Stopaj = (Satış / (1 + kdv/100)) × %1
- Kâr = Satış − Alış − Kargo − Komisyon − Hizmet Bedeli − Stopaj − Ödenecek KDV
- Kâr Oranı = Kâr / Alış × 100

Her ürün için 4 fiyat aralığının komisyonuyla ayrı ayrı hesaplanır; en yüksek
kâr oranını veren aralık arayüzde 🏆 ile vurgulanır.

Hesaplama kuralını değiştirirken önce `tests/calc.test.js`, sonra `assets/calc.js`.

## Yeni ürün eklemek/güncellemek

`assets/urunler-data.js` içindeki `URUNLER` dizisini düzenleyin (Excel'den dışa
aktarılan alanlarla aynı yapıda: `maliyet`, `f1_alt/f2_ust/f2_alt/f3_ust/f3_alt/f4_ust`,
`k3_1..4`, `k4_1..4`, `komisyon_fiyat`, `guncel_komisyon`, `guncel_tsf`).
