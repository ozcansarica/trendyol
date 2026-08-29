// index.html ve indirim/index.html sayfalarının ortak kullandığı localStorage
// anahtarları, veri okuma yardımcıları ve biçimlendiriciler.
//
// İki sayfa da aynı origin'den sunulduğu için aynı localStorage'ı paylaşır:
// ana sayfada yüklenen Excel verisi, girilen maliyetler ve kargo/hizmet ayarı
// indirim sayfasında da geçerlidir.

export const KDV_PCT = 20; // Trendyol satışlarında sabit %20 KDV

export const STORAGE_KEY = 'trendyol_urunler_data_v1';
export const MALIYET_KEY = 'trendyol_maliyet_kayitlari_v1';
export const KARGO_HIZMET_KEY = 'trendyol_kargo_hizmet_v1';

export const VARSAYILAN_KARGO_AYAR = { esik1: 200, esik2: 350, kargo1: 42, kargo2: 78, kargo3: 98 };
export const VARSAYILAN_HIZMET = 13.19;

export const tl = x => x.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '₺';
export const pc = x => x.toLocaleString('tr-TR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%';

// --- Maliyet kayıt defteri (barkod → maliyet) ---

export function loadMaliyetKayitlari() {
  try {
    const raw = localStorage.getItem(MALIYET_KEY);
    return raw ? JSON.parse(raw) : {};
  } catch {
    return {};
  }
}

// Eşleştirme anahtarı: barkod (yoksa model kodu, o da yoksa ürün adı).
export function maliyetAnahtari(u) {
  return String(u.barkod || u.model || u.urun || '').trim();
}

// Kayıtlı maliyetleri verilen ürün dizisine uygular (yerinde değiştirir).
export function applyMaliyetKayitlari(urunler) {
  const kayitlar = loadMaliyetKayitlari();
  for (const u of urunler) {
    const anahtar = maliyetAnahtari(u);
    if (anahtar && kayitlar[anahtar] != null) u.maliyet = kayitlar[anahtar];
  }
  return urunler;
}

// --- Kargo / hizmet ayarı ---

export function readKargoHizmet() {
  const sonuc = { kargoAyar: { ...VARSAYILAN_KARGO_AYAR }, hizmetAyar: VARSAYILAN_HIZMET };
  try {
    const raw = localStorage.getItem(KARGO_HIZMET_KEY);
    if (!raw) return sonuc;
    const d = JSON.parse(raw);
    if (d.kargoAyar) sonuc.kargoAyar = { ...sonuc.kargoAyar, ...d.kargoAyar };
    if (d.hizmetAyar != null) sonuc.hizmetAyar = +d.hizmetAyar;
  } catch {
    // bozuk ayar varsa varsayılana düş
  }
  return sonuc;
}

// --- Ürün verisi ---

// En son yüklenen Excel verisini döner; kayıt yoksa/bozuksa varsayılan veriyle
// döner. { urunler, meta } — meta yalnızca yüklenmiş bir dosya varsa dolu.
export function readUrunler(varsayilan) {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (raw) {
      const saved = JSON.parse(raw);
      if (saved && Array.isArray(saved.urunler) && saved.urunler.length > 0) {
        return { urunler: saved.urunler, meta: { fileName: saved.fileName, uploadedAt: saved.uploadedAt } };
      }
    }
  } catch {
    // bozuk veri varsa sessizce varsayılana düş
  }
  return { urunler: varsayilan, meta: null };
}
