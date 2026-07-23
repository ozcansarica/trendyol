# Katkı & Çalışma Kuralları

Bu depoda **her iş için ayrı bir dal (branch)** açılır, iş bitince **`main`'e merge** edilir.
Bu kural istisnasız uygulanır.

## İş akışı (branch-per-task)

1. **Dal aç** — her yeni iş/özellik/düzeltme için `main`'den yeni bir dal:
   ```bash
   git checkout main
   git pull origin main
   git checkout -b <tur>/<kisa-aciklama>
   ```
   Dal adı öneki:
   - `feat/…`   yeni özellik
   - `fix/…`    hata düzeltme
   - `chore/…`  bakım / yapılandırma
   - `docs/…`   dokümantasyon

2. **Geliştir & commit et** — küçük ve açıklayıcı commit'ler:
   ```bash
   git add -A
   git commit -m "feat: kısa ve net açıklama"
   ```

3. **Push et ve Pull Request aç** — `main` hedefiyle:
   ```bash
   git push -u origin <tur>/<kisa-aciklama>
   ```
   PR açıldığında **CI** (`.github/workflows/ci.yml`) testleri çalıştırır.

4. **Merge et** — CI yeşil olduğunda PR `main`'e merge edilir.
   `main`'e her merge, **GitHub Pages deploy**'unu (`.github/workflows/deploy.yml`)
   otomatik tetikler.

5. **Dalı kapat** — merge sonrası iş dalı silinir.

## Test

Hesaplama mantığı `assets/calc.js` içindedir ve `tests/` altında test edilir:

```bash
npm test
```

Yeni bir hesaplama kuralı eklerken önce testi güncelleyin, sonra `assets/calc.js`'i.
Referans senaryo (ekran görüntüsündeki örnek) her zaman geçmelidir.
