// Trendyol maliyet & kâr hesaplama çekirdeği.
// Hem tarayıcıda (index.html) hem de Node testlerinde (tests/) kullanılır.

// Trendyol sabit hizmet bedeli (KDV dahil) - tüm satışlarda sabit tutar.
export const HIZMET_BEDELI_SABIT = 13.19;

export function hizmetBedeli(satis) {
  return HIZMET_BEDELI_SABIT;
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
  const hizmet = p.hizmet !== undefined ? +p.hizmet : hizmetBedeli(satis); // KDV dahil

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

// ---------------------------------------------------------------------------
// Barem indirimi
// ---------------------------------------------------------------------------
// Maliyet kalemlerinin bir kısmı (kargo, komisyon) fiyat eşiklerine göre
// kademeli artar. Bir eşiğin hemen üstünde kalan sipariş, eşiğin altına
// çekildiğinde fiyattan kaybedilenden fazlasını kargo/komisyondan geri
// kazanabilir: 240₺'lik sipariş 199₺'ye indirilince kargo kademesi 78₺ → 42₺,
// komisyon da satışla orantılı düştüğü için toplam kâr artabilir.
//
// Barem eşikleri iki kapsamda olur:
//   'toplam' — kargo eşikleri gibi sipariş toplamına bakanlar. Kargo bir alt
//              kademeye düşmesi için toplamın eşiğin ALTINDA olması gerekir,
//              bu yüzden hedef tutar = eşik − BAREM_MARJI (200 → 199).
//   'birim'  — komisyon fiyat aralıkları gibi birim fiyata bakanlar. Aralıklar
//              "X ₺ ve altı" tanımlı olduğundan hedef birim fiyat = eşiğin
//              kendisi.

// Bir 'toplam' eşiğinin altına inerken bırakılan pay (200₺ eşiği → 199₺ hedef).
export const BAREM_MARJI = 1;

// Adet ↔ birim fiyat sabit noktası için üst iterasyon sınırı (bkz.
// hedefBirimFiyat): birim fiyat düştükçe zorunlu sipariş adedi artabilir, adet
// artınca hedefi tutturan birim fiyat yeniden düşer.
const MAX_ADET_ITERASYON = 6;

// Barem hedefinin aşılmaması için kuruş küsuratı her zaman aşağı kırpılır
// (199 / 6 = 33.1666… → 33.16; yukarı yuvarlansa toplam 199.02 ile eşiği aşardı).
function floor2(x) {
  return Math.floor(x * 100 + 1e-9) / 100;
}

/**
 * Zorunlu sipariş adedi: düşük fiyatlı ürünlerde Trendyol tek adet siparişe
 * izin vermiyor, müşteri en az bu adedi almak zorunda. Kargo bedeli sipariş
 * (gönderi) başına bir kez alındığından, kâr bu adetteki siparişin toplamı
 * üzerinden hesaplanır.
 * @param {number} birimFiyat Birim satış fiyatı (KDV dahil)
 * @returns {number} Aynı siparişte satılması gereken en az adet
 */
export function zorunluSiparisAdedi(birimFiyat) {
  if (birimFiyat <= 25) return 6;
  if (birimFiyat <= 35) return 4;
  if (birimFiyat <= 50) return 3;
  if (birimFiyat <= 75) return 2;
  return 1;
}

// Tek bir birim fiyat için siparişin (zorunlu adet dahil) kârını hesaplar.
// Komisyon bilinmiyorsa (aralık tanımsız/0) kâr hesaplanamaz → null.
function senaryoHesapla(birimFiyat, p, komisyonOverride) {
  const adetIcin = p.adetIcin || zorunluSiparisAdedi;
  const adet = adetIcin(birimFiyat);
  const siparisToplami = round2(birimFiyat * adet);
  const komisyon = komisyonOverride != null ? +komisyonOverride : p.komisyonIcin(birimFiyat);
  if (!komisyon) return null;
  const kargo = p.kargoIcin(siparisToplami);
  const c = computeMaliyet({
    satis: siparisToplami,
    alis: p.maliyet * adet,
    komPct: komisyon,
    kdvPct: p.kdvPct,
    kargo,
    hizmet: p.hizmet,
  });
  return {
    birimFiyat, adet, siparisToplami, komisyon, kargo,
    kar: c.kar, karOrani: c.karOrani,
  };
}

// Bir barem hedef tutarını birim fiyata çevirir. 'toplam' kapsamda hedef
// sipariş toplamı olduğundan adede bölünür; adet de birim fiyata bağlı
// olduğundan sabit noktaya yakınsanır. Yakınsamazsa (adet ile fiyat birbirini
// kovalıyorsa) o barem tutturulamaz → null.
function hedefBirimFiyat(hedefTutar, kapsam, p) {
  if (kapsam !== 'toplam') return floor2(hedefTutar);
  const adetIcin = p.adetIcin || zorunluSiparisAdedi;
  let adet = adetIcin(p.birimFiyat);
  for (let i = 0; i < MAX_ADET_ITERASYON; i++) {
    const birim = floor2(hedefTutar / adet);
    const yeniAdet = adetIcin(birim);
    if (yeniAdet === adet) return birim;
    adet = yeniAdet;
  }
  return null;
}

/**
 * Mevcut satış fiyatını, altında kalan barem eşiklerinin hemen altına çeken
 * indirim senaryolarını üretir ve her birini mevcut durumla karşılaştırır.
 *
 * @param {object} p
 * @param {number} p.birimFiyat Mevcut birim satış fiyatı (KDV dahil)
 * @param {number} p.maliyet    Birim alış maliyeti (KDV dahil)
 * @param {number} p.kdvPct     KDV yüzdesi
 * @param {number} p.hizmet     Hizmet bedeli (KDV dahil, sipariş başına)
 * @param {Array<{esik:number, kapsam:'toplam'|'birim', tip?:string, etiket?:string}>} p.baremler
 * @param {(siparisToplami:number)=>number} p.kargoIcin      Sipariş toplamına göre kargo
 * @param {(birimFiyat:number)=>number} p.komisyonIcin       Birim fiyata göre komisyon %
 * @param {(birimFiyat:number)=>number} [p.adetIcin]         Varsayılan: zorunluSiparisAdedi
 * @param {number} [p.mevcutKomisyon] Mevcut fiyatın bilinen komisyonu (ör. güncel komisyon)
 * @param {number} [p.maxIndirimYuzdesi] Bu orandan fazla indirim gerektiren
 *   baremler elenir — amaç "yakın" bir eşiğe yuvarlamak, fiyatı dibe çekmek
 *   değil. Verilmezse sınır uygulanmaz.
 * @param {number} [p.marj=BAREM_MARJI]
 * @returns {{mevcut:object|null, adaylar:object[], enIyi:object|null}}
 *   adaylar kâr farkına göre azalan sıralı; enIyi yalnızca kârı artıran bir
 *   aday varsa doludur.
 */
export function baremIndirimAnalizi(p) {
  const marj = p.marj != null ? +p.marj : BAREM_MARJI;
  const maxIndirim = p.maxIndirimYuzdesi != null ? +p.maxIndirimYuzdesi : null;
  const mevcut = senaryoHesapla(round2(p.birimFiyat), p, p.mevcutKomisyon);
  if (!mevcut) return { mevcut: null, adaylar: [], enIyi: null };

  const fiyataGore = new Map(); // aynı birim fiyata denk gelen baremler tek adayda birleşir
  for (const barem of (p.baremler || [])) {
    const esik = +barem.esik;
    if (!(esik > 0)) continue;
    const hedefTutar = barem.kapsam === 'toplam' ? round2(esik - marj) : esik;
    if (!(hedefTutar > 0)) continue;

    const birim = hedefBirimFiyat(hedefTutar, barem.kapsam, p);
    if (birim == null || birim <= 0) continue;
    if (birim >= mevcut.birimFiyat) continue; // indirim değil, atla

    const indirimYuzdesi = round2((mevcut.birimFiyat - birim) / mevcut.birimFiyat * 100);
    if (maxIndirim != null && indirimYuzdesi > maxIndirim) continue; // eşik "yakın" sayılmıyor

    const varolan = fiyataGore.get(birim);
    if (varolan) { varolan.baremler.push(barem); continue; }

    const s = senaryoHesapla(birim, p);
    if (!s) continue;
    fiyataGore.set(birim, {
      ...s,
      baremler: [barem],
      indirimTutari: round2(mevcut.birimFiyat - birim),
      indirimYuzdesi,
      karFarki: s.kar - mevcut.kar,
      karOraniFarki: s.karOrani - mevcut.karOrani,
    });
  }

  const adaylar = [...fiyataGore.values()].sort((a, b) => b.karFarki - a.karFarki);
  const enIyi = adaylar.length > 0 && adaylar[0].karFarki > 0 ? adaylar[0] : null;
  return { mevcut, adaylar, enIyi };
}

// ---------------------------------------------------------------------------
// Tarife / kargo kademeleri (saf yardımcılar)
// ---------------------------------------------------------------------------
// Hem index.html hem indirim/index.html aynı aralık ve kademe tanımlarını
// kullanır; tek kaynak olsun diye çekirdekte durur. Değişken durum (seçili
// tarife, kullanıcının kargo ayarı) parametre olarak verilir.

/**
 * Sipariş toplamına düşen kargo kademesi. Kargo bedeli gönderi başına bir kez
 * alındığından kademe birim fiyata değil siparişin tamamına bakar.
 * @param {number} siparisToplami Birim fiyat × zorunlu sipariş adedi
 * @param {{esik1:number, esik2:number, kargo1:number, kargo2:number, kargo3:number}} ayar
 */
export function kargoKademesi(siparisToplami, ayar) {
  if (siparisToplami < ayar.esik1) return ayar.kargo1;
  if (siparisToplami <= ayar.esik2) return ayar.kargo2;
  return ayar.kargo3;
}

/**
 * Bir ürünün seçili tarifedeki 4 fiyat aralığını (komisyon dilimini) döner.
 * Kâr hesabı içermez; aralıklar en üstten en alta doğru azalan ve bitişiktir.
 * @param {object} u        Ürün
 * @param {string} tarifeKey Tarife grubu anahtarı (ör. '3', '7')
 */
export function tarifeTierleri(u, tarifeKey) {
  const grup = u.tarifeler && u.tarifeler[tarifeKey];
  const k = grup ? grup.k : [0, 0, 0, 0];
  return [
    { num: 1, komisyon: k[0], rangeText: `${u.f1_alt.toFixed(2)} ₺ ve üstü`, minPrice: u.f1_alt, maxPrice: null },
    { num: 2, komisyon: k[1], rangeText: `${u.f2_ust.toFixed(2)} ₺ ve altı`, minPrice: u.f2_alt, maxPrice: u.f2_ust },
    { num: 3, komisyon: k[2], rangeText: `${u.f3_ust.toFixed(2)} ₺ ve altı`, minPrice: u.f3_alt, maxPrice: u.f3_ust },
    { num: 4, komisyon: k[3], rangeText: `${u.f4_ust.toFixed(2)} ₺ ve altı`, minPrice: null, maxPrice: u.f4_ust },
  ];
}

/**
 * Verilen satış fiyatının hangi fiyat aralığına (komisyon dilimine) düştüğü.
 * Aralıklar azalan ve bitişik olduğundan fiyatın karşıladığı en üst aralık
 * kullanılır; en düşük aralığın da altındaysa yine en düşük aralık döner.
 */
export function tarifeTierForPrice(u, tarifeKey, price) {
  const tiers = tarifeTierleri(u, tarifeKey);
  for (const t of tiers) {
    if (t.minPrice != null && price >= t.minPrice) return t;
  }
  return tiers[tiers.length - 1];
}
