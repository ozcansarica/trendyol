// Trendyol maliyet & kâr hesaplama çekirdeği.
// Hem tarayıcıda (index.html) hem de Node testlerinde (tests/) kullanılır.

// Trendyol sabit hizmet bedeli (KDV dahil) - sipariş tutarı aralıklarına göre.
// Trendyol tarifelerini güncellemek için bu tabloyu düzenleyin.
export const HIZMET_BEDELI_TABLOSU = [
  { max: 149.99, ucret: 8.49 },
  { max: 299.99, ucret: 13.19 },
  { max: 499.99, ucret: 20.39 },
  { max: 749.99, ucret: 27.59 },
  { max: Infinity, ucret: 34.79 },
];

export function hizmetBedeli(satis) {
  for (const b of HIZMET_BEDELI_TABLOSU) {
    if (satis <= b.max) return b.ucret;
  }
  return 0;
}

// Yarıya yukarı yuvarlama (42.525 -> 42.53)
export function round2(x) {
  return Math.round((x + Number.EPSILON) * 100) / 100;
}

/**
 * Maliyet ve kâr hesaplar.
 * @param {object} p
 * @param {number} p.satis  Ürün satış fiyatı (KDV dahil)
 * @param {number} p.alis   Ürün alış fiyatı (KDV dahil)
 * @param {number} p.komPct Komisyon yüzdesi
 * @param {number} p.kdvPct KDV yüzdesi
 * @param {number} p.kargo  Kargo ücreti (KDV dahil)
 * @param {boolean} [p.saticiyaAit=true] Kargo satıcıya mı ait
 * @param {boolean} [p.ihracat=false]    İhracat (KDV istisnası)
 * @returns {object} hesaplanan değerler
 */
export function computeMaliyet(p) {
  const satis = +p.satis || 0;
  const alis = +p.alis || 0;
  const komPct = +p.komPct || 0;
  const kdvPct = +p.kdvPct || 0;
  const kargo = +p.kargo || 0;
  const saticiyaAit = p.saticiyaAit !== false;
  const ihracat = !!p.ihracat;

  const r = kdvPct / (100 + kdvPct); // KDV dahil tutar içindeki KDV oranı
  const kargoMaliyet = saticiyaAit ? kargo : 0;

  const komisyon = satis * komPct / 100;   // KDV dahil
  const hizmet = hizmetBedeli(satis);      // KDV dahil

  const satisKdv = ihracat ? 0 : satis * r; // ihracatta KDV istisnası
  const alisKdv = alis * r;
  const kargoKdv = kargoMaliyet * r;
  const komKdv = komisyon * r;
  const hizmetKdv = hizmet * r;

  let odenecekKdv = satisKdv - (alisKdv + kargoKdv + komKdv + hizmetKdv);
  if (odenecekKdv < 0) odenecekKdv = 0; // devreden KDV bu ay ödenmez

  const stopaj = ihracat ? 0 : (satis / (1 + kdvPct / 100)) * 0.01;

  const kar = satis - alis - kargoMaliyet - komisyon - hizmet - stopaj - odenecekKdv;
  const karOrani = alis > 0 ? kar / alis * 100 : 0;
  const alisNet = alis / (1 + kdvPct / 100);
  const roi = alisNet > 0 ? kar / alisNet * 100 : 0;

  return {
    komisyon, hizmet, stopaj, odenecekKdv,
    satisKdv, alisKdv, kargoKdv, komKdv, hizmetKdv,
    kargoMaliyet, kar, karOrani, roi, satis,
  };
}
