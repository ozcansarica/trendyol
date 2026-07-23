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

| Yol | Açıklama |
|-----|----------|
| `index.html` | Maliyet & Kâr hesaplama arayüzü (ana sayfa) |
| `assets/calc.js` | Hesaplama çekirdeği (tarayıcı + testler ortak kullanır) |
| `trendyol_komisyon_hesaplayici.html` | Ürün bazlı komisyon/tarife karşılaştırma aracı |
| `tests/calc.test.js` | Referans senaryo ve kenar durum testleri |
| `.github/workflows/ci.yml` | PR/branch testleri |
| `.github/workflows/deploy.yml` | `main` → GitHub Pages deploy |

## Hesaplama mantığı (özet)

Girdiler KDV **dahil** verilir. `r = kdv / (100 + kdv)`.

- Komisyon = Satış × Komisyon%
- Hizmet Bedeli = satış tutarı aralığına göre sabit tablo (`HIZMET_BEDELI_TABLOSU`)
- ...Oluşan KDV = ilgili tutar × `r`
- Ödenecek KDV = Satıştan KDV − (Alış + Kargo + Komisyon + Hizmet Bedeli KDV'leri); negatifse 0 (devreden)
- Stopaj = (Satış / (1 + kdv/100)) × %1
- Kâr = Satış − Alış − Kargo − Komisyon − Hizmet Bedeli − Stopaj − Ödenecek KDV
- Kâr Oranı = Kâr / Alış × 100
- Yatırım Geri Dönüş Oranı (ROI) = Kâr / (Alış / (1 + kdv/100)) × 100

İhracat seçiliyse satış KDV'si ve stopaj 0 (KDV istisnası) kabul edilir.
Kargo "Satıcıya Ait" değilse kargo maliyeti hesaba katılmaz.

Hesaplama kuralını değiştirirken önce `tests/calc.test.js`, sonra `assets/calc.js`.
