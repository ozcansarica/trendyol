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
// Bazı maliyet/sipariş kuralları eşik bazlıdır; bir eşiğin hemen üstünde kalan
// fiyatı eşiğin altına çekmek fiyattan kaybedilenden fazlasını geri
// kazandırabilir. İki tür eşik var ve baktıkları tutar farklı:
//
//   'toplam' — Kargo kademesi eşikleri. Kargo bedeli gönderi başına bir kez
//              alındığından kademe SİPARİŞ TOPLAMINA bakar. Bir alt kademeye
//              düşmek için toplamın eşiğin ALTINDA olması gerekir, bu yüzden
//              hedef tutar = eşik − BAREM_MARJI (200₺ → 199₺).
//   'birim'  — Zorunlu sipariş adedi eşikleri. Adet BİRİM FİYATA bakar ve kural
//              "X ₺ ve altı" biçiminde olduğundan hedef birim fiyat eşiğin
//              kendisidir (50₺ → 3 adet). Fiyatı bir alt adet baremine çekmek
//              müşteriyi daha fazla adet almaya zorlar; kargo yine tek sefer
//              alındığından sipariş toplamı büyürken kargo sabit kalır.
//
// Komisyon oranı bu hesapta sabittir — ürünün güncel komisyonu neyse indirimli
// senaryoda da o kullanılır. Komisyon tarifesindeki fiyat aralıkları (1.–4.
// aralık) barem indirimiyle ilgili değildir; onlar ana tablonun konusudur.

// Barem indirimi 'toplam' kapsamda eşiğin altına inerken bırakılan pay
// (200₺ eşiği → 199₺ hedef). Burada amaç fiyatı yakın bir barem noktasına
// YUVARLAMAK olduğu için pay 1₺'dir.
export const BAREM_MARJI = 1;

// "Baremin hemen altında kalmak" hesabında ise amaç en az indirimle eşiğin
// altına inmektir; bu yüzden yuvarlama yapılmaz, tam sınıra (bir kuruş altına)
// inilir: 200₺ eşiği → 199,99₺.
export const KURUS = 0.01;

/**
 * Zorunlu sipariş adedi baremleri: birim fiyat eşiği → o eşikte (ve altında)
 * geçerli en az sipariş adedi. Artan eşik sırasında tutulur;
 * zorunluSiparisAdedi() de bu tablodan okur, tek kaynak budur.
 */
export const ZORUNLU_ADET_BAREMLERI = [
  { esik: 25, adet: 6 },
  { esik: 35, adet: 4 },
  { esik: 50, adet: 3 },
  { esik: 75, adet: 2 },
];

// Adet ↔ birim fiyat sabit noktası için üst iterasyon sınırı (bkz.
// hedefBirimFiyat): birim fiyat düştükçe zorunlu sipariş adedi artabilir, adet
// artınca hedefi tutturan birim fiyat yeniden düşer.
const MAX_ADET_ITERASYON = 6;

// Hedef sipariş toplamı aşılmasın diye kuruş küsuratı her zaman aşağı kırpılır
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
  for (const b of ZORUNLU_ADET_BAREMLERI) {
    if (birimFiyat <= b.esik) return b.adet;
  }
  return 1;
}

/**
 * Belirli bir adetteki siparişin kârı. Adet dışarıdan verilir — zorunlu sipariş
 * adedi de olabilir, "kargo baremini geçen adet" gibi başka bir sayı da.
 * @param {object} p
 * @param {number} p.birimFiyat, @param {number} p.adet, @param {number} p.maliyet
 * @param {number} p.komisyon, @param {number} p.kdvPct, @param {number} p.hizmet
 * @param {(siparisToplami:number)=>number} p.kargoIcin
 */
export function adetliSiparisKari(p) {
  const adet = p.adet;
  const siparisToplami = round2(p.birimFiyat * adet);
  const kargo = p.kargoIcin(siparisToplami);
  const c = computeMaliyet({
    satis: siparisToplami,
    alis: p.maliyet * adet,
    komPct: p.komisyon,
    kdvPct: p.kdvPct,
    kargo,
    hizmet: p.hizmet,
  });
  return {
    birimFiyat: p.birimFiyat, adet, siparisToplami, kargo,
    kar: c.kar, karOrani: c.karOrani,
  };
}

// Tek bir birim fiyat için siparişin (zorunlu adet dahil) kârını hesaplar.
function senaryoHesapla(birimFiyat, p) {
  const adetIcin = p.adetIcin || zorunluSiparisAdedi;
  return adetliSiparisKari({ ...p, birimFiyat, adet: adetIcin(birimFiyat) });
}

// Bir barem eşiğini hedef birim fiyata çevirir.
// 'birim' kapsamda hedef doğrudan eşiğin kendisidir. 'toplam' kapsamda hedef
// sipariş toplamı adede bölünür; birim fiyat düşünce zorunlu sipariş adedi
// artabildiğinden sabit noktaya yakınsanır, yakınsamazsa (adet ile fiyat
// birbirini kovalıyorsa) o eşik tutturulamaz → null.
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
 * Mevcut satış fiyatını, altında kalan barem eşiklerinin hedefine çeken indirim
 * senaryolarını üretir ve her birini mevcut durumla karşılaştırır.
 *
 * @param {object} p
 * @param {number} p.birimFiyat Mevcut birim satış fiyatı (KDV dahil)
 * @param {number} p.maliyet    Birim alış maliyeti (KDV dahil)
 * @param {number} p.komisyon   Komisyon yüzdesi — mevcut ve indirimli senaryoda aynı
 * @param {number} p.kdvPct     KDV yüzdesi
 * @param {number} p.hizmet     Hizmet bedeli (KDV dahil, sipariş başına)
 * @param {Array<{esik:number, kapsam:'toplam'|'birim', etiket?:string}>} p.baremler
 * @param {(siparisToplami:number)=>number} p.kargoIcin Sipariş toplamına göre kargo
 * @param {(birimFiyat:number)=>number} [p.adetIcin]    Varsayılan: zorunluSiparisAdedi
 * @param {number} [p.marj=BAREM_MARJI]
 * @returns {{mevcut:object|null, adaylar:object[], enIyi:object|null}}
 *   adaylar kâr farkına göre azalan sıralı; enIyi yalnızca kârı artıran bir
 *   aday varsa doludur.
 */
export function baremIndirimAnalizi(p) {
  if (!p.komisyon) return { mevcut: null, adaylar: [], enIyi: null };
  const marj = p.marj != null ? +p.marj : BAREM_MARJI;
  const mevcut = senaryoHesapla(round2(p.birimFiyat), p);

  const fiyataGore = new Map(); // aynı birim fiyata denk gelen eşikler tek adayda birleşir
  for (const barem of (p.baremler || [])) {
    const esik = +barem.esik;
    if (!(esik > 0)) continue;
    const hedefTutar = barem.kapsam === 'toplam' ? round2(esik - marj) : esik;
    if (!(hedefTutar > 0)) continue;

    const birim = hedefBirimFiyat(hedefTutar, barem.kapsam, p);
    if (birim == null || birim <= 0) continue;
    if (birim >= mevcut.birimFiyat) continue; // indirim değil, atla

    const varolan = fiyataGore.get(birim);
    if (varolan) { varolan.baremler.push(barem); continue; }

    const s = senaryoHesapla(birim, p);
    fiyataGore.set(birim, {
      ...s,
      baremler: [barem],
      indirimTutari: round2(mevcut.birimFiyat - birim),
      indirimYuzdesi: round2((mevcut.birimFiyat - birim) / mevcut.birimFiyat * 100),
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
 *
 * Her iki eşik de aynı şekilde çalışır: toplam eşiğe EŞİTSE üst kademeye
 * girilir. Alt kademede kalmak için eşiğin altında olmak gerekir — tam 200₺
 * ikinci kademedir (78₺), tam 350₺ üçüncü kademedir (98₺).
 *
 * @param {number} siparisToplami Birim fiyat × zorunlu sipariş adedi
 * @param {{esik1:number, esik2:number, kargo1:number, kargo2:number, kargo3:number}} ayar
 */
export function kargoKademesi(siparisToplami, ayar) {
  if (siparisToplami < ayar.esik1) return ayar.kargo1;
  if (siparisToplami < ayar.esik2) return ayar.kargo2;
  return ayar.kargo3;
}

// Bir koşulu sağlayan en küçük adedi bulur. Analitik tahminden başlanır, sonra
// koşul sağlanana kadar yukarı / gereksiz büyükse aşağı düzeltilir; böylece
// kuruş yuvarlamaları (round2) yüzünden bir adet şaşmaz.
const MAX_ADET_ARAMA = 100000;
function ilkAdet(kosul, birimFiyat, tahmin) {
  let n = Math.max(1, Math.ceil(tahmin - 1e-9));
  while (n > 1 && kosul(round2((n - 1) * birimFiyat))) n--;
  let adim = 0;
  while (!kosul(round2(n * birimFiyat)) && adim++ < MAX_ADET_ARAMA) n++;
  return adim >= MAX_ADET_ARAMA ? null : n;
}

/**
 * Bir birim fiyatın kargo kademesi eşiklerini kaçıncı adette geçtiğini bulur.
 * Eşik koşulları kargoKademesi() ile birebir aynıdır: bir üst kademeye girmek
 * için sipariş toplamı eşiğe EŞİT ya da üstünde olmalıdır (iki eşikte de aynı).
 *
 * Dönen `kargo`, o adetteki GERÇEK kademedir — varsayılan bir sonraki kademe
 * değil. Pahalı ürünlerde tek bir adet artışı iki eşiği birden geçebilir
 * (199₺ × 2 = 398₺ hem 200₺ hem 350₺ eşiğini geçtiğinden kargo doğrudan 98₺).
 *
 * Her eşik için ayrıca `altindaKalmak` döner: o adette baremin ALTINDA kalmak
 * için **sipariş toplamından** düşülmesi gereken EN AZ indirim. İndirim birim
 * fiyata değil sepet toplamına uygulandığından hedef tutar tam tutturulabilir
 * (birim fiyata bölüp aşağı kırpma gerekmez). Hedef yuvarlanmaz, tam sınırdır:
 * alt kademede kalmak için toplam eşiğin ALTINDA olmalı, yani hedef = eşik −
 * 1 kuruş (200₺ → 199,99₺, 350₺ → 349,99₺).
 *
 * @param {number} birimFiyat
 * @param {{esik1:number, esik2:number, kargo1:number, kargo2:number, kargo3:number}} ayar
 * @param {number} [marj=KURUS] 'altında kalma' hedefinde eşiğin altına inilirken
 *   bırakılan pay. Varsayılan bir kuruştur (en az indirim); yuvarlanmış bir
 *   fiyat noktası isteniyorsa büyütülebilir.
 * @returns {Array<{esik:number, adet:number|null, siparisToplami:number|null,
 *   kargo:number|null, oncekiKargo:number|null, altindaKalmak:object|null}>}
 *   `oncekiKargo` bir eksik adetteki kademedir (adet 1 ise null — o eşik zaten
 *   tek adette geçiliyor). Makul adette geçilemeyen eşikte adet null döner.
 */
export function kargoEsikAdetleri(birimFiyat, ayar, marj) {
  const p = +birimFiyat;
  if (!(p > 0)) return [];
  const pay = marj != null ? +marj : KURUS;
  // İki eşik de aynı kuralla çalışır: toplam eşiğe eşit ya da üstündeyse üst
  // kademeye girilir, dolayısıyla altında kalmak için eşiğin bir kuruş altına
  // inmek yeterlidir (200₺ → 199,99₺, 350₺ → 349,99₺).
  const esikler = [ayar.esik1, ayar.esik2].map(esik => ({
    esik,
    kosul: (t) => t >= esik,
    hedefToplam: round2(esik - pay),
  }));
  return esikler.map(({ esik, kosul, hedefToplam }) => {
    const adet = ilkAdet(kosul, p, esik / p);
    if (adet == null) {
      return { esik, adet: null, siparisToplami: null, kargo: null, oncekiKargo: null, altindaKalmak: null };
    }
    const siparisToplami = round2(adet * p);
    // Aynı adette baremin altında kalmak için sipariş toplamından düşülecek
    // indirim. Sepet toplamına uygulandığından hedef tutar tam tutturulur.
    const indirimTutari = round2(siparisToplami - hedefToplam);
    const altindaKalmak = hedefToplam > 0 && indirimTutari > 0 ? {
      siparisToplami: round2(hedefToplam),
      kargo: kargoKademesi(round2(hedefToplam), ayar),
      indirimTutari,
      indirimYuzdesi: round2(indirimTutari / siparisToplami * 100),
      // Bilgi amaçlı: indirim sonrası birim karşılığı. Aşağı kırpılır — yukarı
      // yuvarlansa (199,99 / 2 = 99,995 → 100,00) birim × adet eşiğe geri
      // çıkar ve yanıltıcı olurdu.
      birimFiyat: floor2(hedefToplam / adet),
    } : null;
    return {
      esik, adet, siparisToplami,
      kargo: kargoKademesi(siparisToplami, ayar),
      oncekiKargo: adet > 1 ? kargoKademesi(round2((adet - 1) * p), ayar) : null,
      altindaKalmak,
    };
  });
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
