// Referans senaryoya göre hesaplama testleri.
// Çalıştırma:  node --test
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { computeMaliyet, round2, hizmetBedeli, zorunluSiparisAdedi, ZORUNLU_ADET_BAREMLERI, baremIndirimAnalizi, kargoEsikAdetleri, kargoKademesi, adetliSiparisKari } from '../assets/calc.js';

test('referans senaryo (satis 189, alis 30, kom %22.5, kdv %20, kargo 42)', () => {
  const r = computeMaliyet({ satis: 189, alis: 30, komPct: 22.5, kdvPct: 20, kargo: 42 });
  assert.equal(round2(r.komisyon), 42.53);
  assert.equal(round2(r.roi), 197.98);
  assert.equal(round2(r.hizmet), 13.19);
  assert.equal(round2(r.stopaj), 1.58);
  assert.equal(round2(r.karOrani), 164.99);
  assert.equal(round2(r.odenecekKdv), 10.21);
  assert.equal(round2(r.satisKdv), 31.5);
  assert.equal(round2(r.alisKdv), 5);
  assert.equal(round2(r.kargoKdv), 7);
  assert.equal(round2(r.komKdv), 7.09);
  assert.equal(round2(r.hizmetKdv), 2.20);
  assert.equal(round2(r.kar), 49.50);
});

test('hizmet bedeli her zaman sabit 13.19', () => {
  assert.equal(hizmetBedeli(20), 13.19);
  assert.equal(hizmetBedeli(189), 13.19);
  assert.equal(hizmetBedeli(400), 13.19);
  assert.equal(hizmetBedeli(1000), 13.19);
});

test('ihracatta KDV istisnası ve stopaj yok', () => {
  const r = computeMaliyet({ satis: 189, alis: 30, komPct: 22.5, kdvPct: 20, kargo: 42, ihracat: true });
  assert.equal(r.satisKdv, 0);
  assert.equal(r.stopaj, 0);
  assert.equal(r.odenecekKdv, 0); // indirilecek KDV > 0, devreden KDV ödenmez
});

test('kargo satıcıya ait değilse maliyete girmez', () => {
  const r = computeMaliyet({ satis: 189, alis: 30, komPct: 22.5, kdvPct: 20, kargo: 42, saticiyaAit: false });
  assert.equal(r.kargoMaliyet, 0);
  assert.equal(r.kargoKdv, 0);
});

// --- Barem indirimi -------------------------------------------------------

// Testlerde kullanılan varsayılan kargo kademesi (index.html'deki kargoAyar):
// <200₺ → 42₺, 200–350₺ → 78₺, >350₺ → 98₺ — sipariş toplamına uygulanır.
const kargoIcinTest = (siparisToplami) =>
  siparisToplami < 200 ? 42 : (siparisToplami <= 350 ? 78 : 98);

// Zorunlu adet eşikleri, 'birim' kapsamlı barem adayı olarak.
const adetBaremleri = ZORUNLU_ADET_BAREMLERI.map(b => ({ esik: b.esik, kapsam: 'birim', adet: b.adet }));

test('zorunlu sipariş adedi baremleri', () => {
  assert.equal(zorunluSiparisAdedi(25), 6);
  assert.equal(zorunluSiparisAdedi(25.01), 4);
  assert.equal(zorunluSiparisAdedi(35), 4);
  assert.equal(zorunluSiparisAdedi(35.01), 3);
  assert.equal(zorunluSiparisAdedi(50), 3);
  assert.equal(zorunluSiparisAdedi(50.01), 2);
  assert.equal(zorunluSiparisAdedi(75), 2);
  assert.equal(zorunluSiparisAdedi(75.01), 1);
  assert.equal(zorunluSiparisAdedi(240), 1);
});

test('ZORUNLU_ADET_BAREMLERI tablosu zorunluSiparisAdedi() ile tutarlı', () => {
  for (const b of ZORUNLU_ADET_BAREMLERI) {
    assert.equal(zorunluSiparisAdedi(b.esik), b.adet, `${b.esik}₺ → ${b.adet} adet olmalı`);
  }
});

test('kargo baremi: 240₺ → 199₺ eşiğin altına inince kâr artar', () => {
  const r = baremIndirimAnalizi({
    birimFiyat: 240, maliyet: 100, komisyon: 20, kdvPct: 20, hizmet: 13.19,
    kargoIcin: kargoIcinTest,
    baremler: [{ esik: 200, kapsam: 'toplam' }, { esik: 350, kapsam: 'toplam' }],
  });

  assert.equal(r.mevcut.kargo, 78);
  assert.equal(round2(r.mevcut.kar), -1.33);

  // 350₺ eşiği mevcut fiyatın üstünde kaldığı için indirim adayı üretmez.
  assert.equal(r.adaylar.length, 1);
  const a = r.adaylar[0];
  assert.equal(a.birimFiyat, 199); // 'toplam' kapsam: eşik − BAREM_MARJI
  assert.equal(a.kargo, 42);
  assert.equal(a.indirimTutari, 41);
  assert.equal(round2(a.kar), 1.68);
  assert.equal(round2(a.karFarki), 3.01);
  assert.equal(r.enIyi, a);
});

test('adet baremi: 60₺ → 50₺ inince zorunlu adet 2→3, sipariş toplamı büyür', () => {
  const r = baremIndirimAnalizi({
    birimFiyat: 60, maliyet: 15, komisyon: 20, kdvPct: 20, hizmet: 13.19,
    kargoIcin: kargoIcinTest,
    baremler: adetBaremleri,
  });

  assert.equal(r.mevcut.adet, 2);
  assert.equal(r.mevcut.siparisToplami, 120);

  // 75₺ eşiği mevcut fiyatın üstünde → aday değil. Kalan üç eşik aday olur.
  assert.equal(r.adaylar.length, 3);
  const a = r.adaylar[0];
  assert.equal(a.birimFiyat, 50); // 'birim' kapsam: hedef eşiğin kendisi
  assert.equal(a.adet, 3);
  assert.equal(a.siparisToplami, 150); // kargo yine tek sefer alınır
  assert.equal(a.kargo, 42);
  assert.equal(round2(a.karFarki), 7.25);
  assert.equal(r.enIyi, a);

  // Daha derin adet baremleri fiyatı çok düşürdüğünden zarara geçer.
  assert.ok(r.adaylar.every(x => x.birimFiyat === 50 || x.karFarki < 0));
});

test('adet baremi: eşiğin kendisi hedeftir, eşik−marj değil', () => {
  const r = baremIndirimAnalizi({
    birimFiyat: 80, maliyet: 20, komisyon: 20, kdvPct: 20, hizmet: 13.19,
    kargoIcin: kargoIcinTest,
    baremler: [{ esik: 75, kapsam: 'birim' }],
  });
  const a = r.adaylar[0];
  assert.equal(r.mevcut.adet, 1);
  assert.equal(a.birimFiyat, 75); // 74 değil — kural "75₺ ve altı"
  assert.equal(a.adet, 2);
  assert.equal(a.siparisToplami, 150);
});

test('barem indirimi: komisyon indirimli senaryoda da aynı kalır', () => {
  // Komisyon tarifesindeki fiyat aralıkları bu hesaba karışmaz; indirimli
  // fiyat başka bir aralığa düşse bile ürünün güncel komisyonu kullanılır.
  const r = baremIndirimAnalizi({
    birimFiyat: 240, maliyet: 100, komisyon: 20, kdvPct: 20, hizmet: 13.19,
    kargoIcin: kargoIcinTest,
    baremler: [{ esik: 200, kapsam: 'toplam' }],
  });
  const a = r.adaylar[0];
  assert.equal(round2(r.mevcut.siparisToplami * 0.20), 48);
  assert.equal(round2(a.siparisToplami * 0.20), 39.8);
});

test('barem indirimi: kârı düşüren aday enIyi olarak seçilmez', () => {
  // Düşük komisyonda 41₺'lik fiyat indirimi, kargo (36₺) + komisyon kazancından
  // büyük kalır; aday yine listelenir ama enIyi olarak seçilmez.
  const r = baremIndirimAnalizi({
    birimFiyat: 240, maliyet: 100, komisyon: 8, kdvPct: 20, hizmet: 13.19,
    kargoIcin: kargoIcinTest,
    baremler: [{ esik: 200, kapsam: 'toplam' }],
  });
  assert.equal(r.adaylar.length, 1);
  assert.equal(round2(r.adaylar[0].karFarki), -1.09);
  assert.equal(r.enIyi, null);
});

test('kargo baremi: zorunlu adet artınca hedef toplam yine eşiğin altında kalır', () => {
  // 60₺ eşiği: birim fiyat düştükçe zorunlu adet 1 → 2 → 4 → 6 büyür,
  // hedef birim fiyat da adede bölünerek sabit noktaya yakınsar.
  const r = baremIndirimAnalizi({
    birimFiyat: 80, maliyet: 20, komisyon: 20, kdvPct: 20, hizmet: 13.19,
    kargoIcin: kargoIcinTest,
    baremler: [{ esik: 60, kapsam: 'toplam' }],
  });
  const a = r.adaylar[0];
  assert.equal(a.adet, 6);
  assert.equal(a.birimFiyat, 9.83); // kuruş küsuratı aşağı kırpılır
  assert.equal(a.siparisToplami, 58.98);
  assert.ok(a.siparisToplami < 60, 'sipariş toplamı barem eşiğinin altında kalmalı');
});

test('barem indirimi: aynı fiyata denk gelen eşikler tek adayda birleşir', () => {
  const r = baremIndirimAnalizi({
    birimFiyat: 240, maliyet: 100, komisyon: 20, kdvPct: 20, hizmet: 13.19,
    kargoIcin: kargoIcinTest,
    // 200₺ 'toplam' → 199₺; 199₺ 'birim' → 199₺. Aynı hedef fiyat.
    baremler: [{ esik: 200, kapsam: 'toplam' }, { esik: 199, kapsam: 'birim' }],
  });
  assert.equal(r.adaylar.length, 1);
  assert.equal(r.adaylar[0].baremler.length, 2);
});

test('barem indirimi: komisyonu bilinmeyen ürün için analiz yapılmaz', () => {
  const r = baremIndirimAnalizi({
    birimFiyat: 240, maliyet: 100, komisyon: 0, kdvPct: 20, hizmet: 13.19,
    kargoIcin: kargoIcinTest,
    baremler: [{ esik: 200, kapsam: 'toplam' }],
  });
  assert.equal(r.mevcut, null);
  assert.equal(r.adaylar.length, 0);
  assert.equal(r.enIyi, null);
});

// --- Kargo eşiğini kaç adette geçer ---------------------------------------

const kargoAyarTest = { esik1: 200, esik2: 350, kargo1: 42, kargo2: 78, kargo3: 98 };

test('kargo eşik adetleri: eşiği geçen en küçük adedi bulur', () => {
  const [ikinci, ucuncu] = kargoEsikAdetleri(60, kargoAyarTest);
  assert.equal(ikinci.adet, 4);            // 4 × 60 = 240 ≥ 200
  assert.equal(ikinci.siparisToplami, 240);
  assert.equal(ikinci.kargo, 78);
  assert.equal(ikinci.oncekiKargo, 42); // 3 × 60 = 180 → hâlâ 42₺
  assert.equal(ucuncu.adet, 6);            // 6 × 60 = 360 > 350
  assert.equal(ucuncu.siparisToplami, 360);
  assert.equal(ucuncu.kargo, 98);
});

test('kargo eşik adetleri: eşiğe tam oturan tutar ikinci kademeye girer', () => {
  // kargoKademesi: toplam < esik1 → kargo1, esik1 ≤ toplam ≤ esik2 → kargo2.
  // 10 × 20 = 200, yani tam eşik zaten 78₺ kademesinde.
  const [ikinci] = kargoEsikAdetleri(20, kargoAyarTest);
  assert.equal(ikinci.adet, 10);
  assert.equal(ikinci.siparisToplami, 200);
  assert.equal(kargoKademesi(200, kargoAyarTest), 78);
  assert.equal(kargoKademesi(199.99, kargoAyarTest), 42);
});

test('kargo eşik adetleri: üçüncü kademe için eşiğin üstüne çıkmak gerekir', () => {
  // 350₺ tam eşik hâlâ ikinci kademe (kargo2); geçmek için üstüne çıkmalı.
  const [, ucuncu] = kargoEsikAdetleri(35, kargoAyarTest);
  assert.equal(kargoKademesi(350, kargoAyarTest), 78);
  assert.equal(ucuncu.adet, 11); // 10 × 35 = 350 yetmez, 11 × 35 = 385
  assert.equal(ucuncu.siparisToplami, 385);
});

test('kargo eşik adetleri: her eşik için bulunan adet gerçekten o kademeyi verir', () => {
  for (const fiyat of [19.18, 20, 35, 49.9, 60, 75, 199, 249, 375]) {
    const [ikinci, ucuncu] = kargoEsikAdetleri(fiyat, kargoAyarTest);
    // Bulunan adet eşiği gerçekten geçmeli, bir eksiği geçmemeli.
    assert.ok(ikinci.siparisToplami >= 200, `${fiyat}₺ × ${ikinci.adet} 200₺'yi geçmeli`);
    assert.ok(ucuncu.siparisToplami > 350, `${fiyat}₺ × ${ucuncu.adet} 350₺'yi geçmeli`);
    if (ikinci.adet > 1) assert.ok(round2((ikinci.adet - 1) * fiyat) < 200, `${fiyat}₺ için ${ikinci.adet} en küçük adet olmalı`);
    if (ucuncu.adet > 1) assert.ok(round2((ucuncu.adet - 1) * fiyat) <= 350, `${fiyat}₺ için ${ucuncu.adet} en küçük adet olmalı`);
    // Gösterilen kargo, o adetteki gerçek kademe olmalı.
    for (const e of [ikinci, ucuncu]) {
      assert.equal(e.kargo, kargoKademesi(e.siparisToplami, kargoAyarTest));
    }
  }
});

test('kargo eşik adetleri: bir adet artışı iki eşiği birden geçebilir', () => {
  // 199₺ × 2 = 398₺ hem 200₺ hem 350₺ eşiğinin üstünde; 200₺ eşiği satırında
  // gösterilen kargo, varsayılan 78₺ değil o adetteki gerçek kademe olmalı.
  const [ikinci, ucuncu] = kargoEsikAdetleri(199, kargoAyarTest);
  assert.equal(ikinci.adet, 2);
  assert.equal(ikinci.siparisToplami, 398);
  assert.equal(ikinci.kargo, 98);
  assert.equal(ucuncu.adet, 2);
  assert.equal(ucuncu.kargo, 98);
});

test('kargo eşik adetleri: tek adette geçilen eşikte oncekiKargo null', () => {
  const [ikinci] = kargoEsikAdetleri(249, kargoAyarTest);
  assert.equal(ikinci.adet, 1);
  assert.equal(ikinci.oncekiKargo, null);
});

test('kargo eşik adetleri: geçersiz fiyatta boş döner', () => {
  assert.deepEqual(kargoEsikAdetleri(0, kargoAyarTest), []);
  assert.deepEqual(kargoEsikAdetleri(-5, kargoAyarTest), []);
});

test('adetliSiparisKari: adet dışarıdan verilir, kargo toplamdan bulunur', () => {
  const kargoIcin = (t) => kargoKademesi(t, kargoAyarTest);
  const r = adetliSiparisKari({ birimFiyat: 60, adet: 4, maliyet: 15, komisyon: 20, kdvPct: 20, hizmet: 13.19, kargoIcin });
  assert.equal(r.adet, 4);
  assert.equal(r.siparisToplami, 240);
  assert.equal(r.kargo, 78); // zorunlu adet 2 olsa da 4 adette kargo kademesi yükseliyor
  const c = computeMaliyet({ satis: 240, alis: 60, komPct: 20, kdvPct: 20, kargo: 78, hizmet: 13.19 });
  assert.equal(r.kar, c.kar);
});

test('altında kalmak: 200₺ eşiğinde hedef eşiğin altı, 350₺ eşiğinde eşiğin kendisi', () => {
  const [ikinci, ucuncu] = kargoEsikAdetleri(120, kargoAyarTest);

  // 2 adette 240₺ ile 200₺ eşiği geçiliyor; altında kalmak için toplam 199₺.
  assert.equal(ikinci.adet, 2);
  assert.equal(ikinci.altindaKalmak.birimFiyat, 99.5);
  assert.equal(ikinci.altindaKalmak.siparisToplami, 199);
  assert.equal(ikinci.altindaKalmak.indirimTutari, 20.5);
  assert.equal(ikinci.altindaKalmak.indirimYuzdesi, 17.08);
  assert.equal(ikinci.altindaKalmak.kargo, 42);

  // 3 adette 360₺ ile 350₺ eşiği geçiliyor; 350₺'nin kendisi hâlâ alt kademe.
  assert.equal(ucuncu.adet, 3);
  assert.equal(ucuncu.altindaKalmak.birimFiyat, 116.66); // 350 / 3 aşağı kırpılmış
  assert.equal(ucuncu.altindaKalmak.siparisToplami, 349.98);
  assert.equal(ucuncu.altindaKalmak.kargo, 78);
});

test('altında kalmak: hedef toplam hiçbir zaman eşiği aşmaz ve alt kademeyi verir', () => {
  for (const fiyat of [19.18, 20, 35, 49.9, 60, 75, 120, 199, 249, 375]) {
    const [ikinci, ucuncu] = kargoEsikAdetleri(fiyat, kargoAyarTest);
    for (const e of [ikinci, ucuncu]) {
      if (!e.altindaKalmak) continue;
      const a = e.altindaKalmak;
      // Kırpma doğru çalışmalı: toplam hedefi aşmamalı.
      assert.ok(a.siparisToplami <= (e.esik === 200 ? 199 : 350),
        `${fiyat}₺ × ${e.adet} → ${a.siparisToplami}₺ hedefi aşmamalı`);
      // Ve gerçekten geçilen kademenin altında kalmalı.
      assert.equal(a.kargo, kargoKademesi(a.siparisToplami, kargoAyarTest));
      assert.ok(a.kargo < e.kargo, `${fiyat}₺ ${e.esik}₺ eşiği: ${a.kargo}₺ < ${e.kargo}₺ olmalı`);
      // İndirim tutarı ve yüzdesi tutarlı olmalı.
      assert.equal(a.indirimTutari, round2(fiyat - a.birimFiyat));
      assert.equal(a.indirimYuzdesi, round2(a.indirimTutari / fiyat * 100));
    }
  }
});

test('altında kalmak: indirim gerekmiyorsa null döner', () => {
  // 20₺ × 10 = 200₺ tam eşik; altında kalmak 19,90₺ gerektirir (indirim var).
  assert.ok(kargoEsikAdetleri(20, kargoAyarTest)[0].altindaKalmak);
  // 375₺'lik ürün tek adette 350₺ eşiğini geçiyor; altında kalmak 350₺'ye
  // inmeyi gerektirir, yani yine indirim var.
  const [, ucuncu] = kargoEsikAdetleri(375, kargoAyarTest);
  assert.equal(ucuncu.adet, 1);
  assert.equal(ucuncu.altindaKalmak.birimFiyat, 350);
  assert.equal(ucuncu.altindaKalmak.indirimTutari, 25);
});
