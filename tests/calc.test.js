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
// <200₺ → 42₺, 200–349,99₺ → 78₺, ≥350₺ → 98₺ — sipariş toplamına uygulanır.
// Eşiğe oturan tutar üst kademeye girer (iki eşikte de aynı kural).
const kargoIcinTest = (siparisToplami) =>
  siparisToplami < 200 ? 42 : (siparisToplami < 350 ? 78 : 98);

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

test('kargo kademesi: eşiğe oturan tutar iki eşikte de üst kademeye girer', () => {
  assert.equal(kargoKademesi(199.99, kargoAyarTest), 42);
  assert.equal(kargoKademesi(200, kargoAyarTest), 78);   // tam eşik → üst kademe
  assert.equal(kargoKademesi(349.99, kargoAyarTest), 78);
  assert.equal(kargoKademesi(350, kargoAyarTest), 98);   // tam eşik → üst kademe
});

test('kargo eşik adetleri: eşiğe tam oturan adet o eşiği geçmiş sayılır', () => {
  // 10 × 20 = 200 → 78₺ kademesi.
  const [ikinci] = kargoEsikAdetleri(20, kargoAyarTest);
  assert.equal(ikinci.adet, 10);
  assert.equal(ikinci.siparisToplami, 200);
  assert.equal(ikinci.kargo, 78);

  // 10 × 35 = 350 → 98₺ kademesi.
  const [, ucuncu] = kargoEsikAdetleri(35, kargoAyarTest);
  assert.equal(ucuncu.adet, 10);
  assert.equal(ucuncu.siparisToplami, 350);
  assert.equal(ucuncu.kargo, 98);
});

test('kargo eşik adetleri: her eşik için bulunan adet gerçekten o kademeyi verir', () => {
  for (const fiyat of [19.18, 20, 35, 49.9, 60, 75, 199, 249, 375]) {
    const [ikinci, ucuncu] = kargoEsikAdetleri(fiyat, kargoAyarTest);
    // Bulunan adet eşiği gerçekten geçmeli, bir eksiği geçmemeli.
    assert.ok(ikinci.siparisToplami >= 200, `${fiyat}₺ × ${ikinci.adet} 200₺'yi geçmeli`);
    assert.ok(ucuncu.siparisToplami >= 350, `${fiyat}₺ × ${ucuncu.adet} 350₺'ye ulaşmalı`);
    if (ikinci.adet > 1) assert.ok(round2((ikinci.adet - 1) * fiyat) < 200, `${fiyat}₺ için ${ikinci.adet} en küçük adet olmalı`);
    if (ucuncu.adet > 1) assert.ok(round2((ucuncu.adet - 1) * fiyat) < 350, `${fiyat}₺ için ${ucuncu.adet} en küçük adet olmalı`);
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

test('altında kalmak: en az indirim, hedef yuvarlanmadan tam sınıra iner', () => {
  const [ikinci, ucuncu] = kargoEsikAdetleri(120, kargoAyarTest);

  // 2 adette 240₺ ile 200₺ eşiği geçiliyor. Altında kalmak için 199₺'ye
  // yuvarlamak gerekmez; bir kuruş altı (199,99₺) yeter, indirim de o kadar az.
  assert.equal(ikinci.adet, 2);
  assert.equal(ikinci.siparisToplami, 240);
  assert.equal(ikinci.altindaKalmak.siparisToplami, 199.99);
  assert.equal(ikinci.altindaKalmak.indirimTutari, 40.01); // 240 − 199,99
  assert.equal(ikinci.altindaKalmak.kargo, 42);
  // Bilgi amaçlı birim karşılığı aşağı kırpılır: 199,99 / 2 = 99,995 → 99,99
  // (yukarı yuvarlansa 100,00 × 2 = 200,00 ile eşiğe geri çıkardı).
  assert.equal(ikinci.altindaKalmak.birimFiyat, 99.99);
  assert.ok(ikinci.altindaKalmak.birimFiyat * ikinci.adet < kargoAyarTest.esik1);

  // 350₺ eşiğinde de aynı kural: tam 350₺ üst kademedir, altı 349,99₺'dir.
  assert.equal(ucuncu.adet, 3);
  assert.equal(ucuncu.altindaKalmak.siparisToplami, 349.99);
  assert.equal(ucuncu.altindaKalmak.indirimTutari, 10.01); // 360 − 349,99
  assert.equal(ucuncu.altindaKalmak.kargo, 78);
});

test('altında kalmak: eşiğe tam oturan toplamda bir kuruş indirim yeter', () => {
  // 25₺ × 8 = 200,00₺ tam eşik → 78₺ kademesinde. 1 kuruş indirim 199,99₺'ye
  // indirip kargoyu 42₺'ye çeker.
  const [ikinci, ucuncu] = kargoEsikAdetleri(25, kargoAyarTest);
  assert.equal(ikinci.siparisToplami, 200);
  assert.equal(ikinci.kargo, 78);
  assert.equal(ikinci.altindaKalmak.indirimTutari, 0.01);
  assert.equal(ikinci.altindaKalmak.siparisToplami, 199.99);
  assert.equal(ikinci.altindaKalmak.kargo, 42);

  // 25₺ × 14 = 350,00₺ tam eşik → 98₺ kademesinde; orada da 1 kuruş yeter.
  assert.equal(ucuncu.adet, 14);
  assert.equal(ucuncu.siparisToplami, 350);
  assert.equal(ucuncu.kargo, 98);
  assert.equal(ucuncu.altindaKalmak.indirimTutari, 0.01);
  assert.equal(ucuncu.altindaKalmak.siparisToplami, 349.99);
  assert.equal(ucuncu.altindaKalmak.kargo, 78);
});

test('altında kalmak: hedef toplam eşiği aşmaz ve alt kademeyi verir', () => {
  for (const fiyat of [19.18, 20, 34.9, 49.9, 60, 75, 120, 199, 249, 375]) {
    const [ikinci, ucuncu] = kargoEsikAdetleri(fiyat, kargoAyarTest);
    for (const e of [ikinci, ucuncu]) {
      if (!e.altindaKalmak) continue;
      const a = e.altindaKalmak;
      // Hedef, eşiğin kendi kuralındaki tam sınır: 200₺ için 199,99₺, 350₺ için 350₺.
      assert.equal(a.siparisToplami, round2(e.esik - 0.01));
      // Ve gerçekten geçilen kademenin altında kalmalı.
      assert.equal(a.kargo, kargoKademesi(a.siparisToplami, kargoAyarTest));
      assert.ok(a.kargo < e.kargo, `${fiyat}₺ ${e.esik}₺ eşiği: ${a.kargo}₺ < ${e.kargo}₺ olmalı`);
      // İndirim sipariş toplamı üzerinden, tutar ve yüzde tutarlı olmalı.
      assert.equal(a.indirimTutari, round2(e.siparisToplami - a.siparisToplami));
      assert.equal(a.indirimYuzdesi, round2(a.indirimTutari / e.siparisToplami * 100));
      // Bilgi amaçlı birim karşılığı hiçbir zaman hedefi aşmamalı.
      assert.ok(round2(a.birimFiyat * e.adet) <= a.siparisToplami);
    }
  }
});

test('altında kalmak: marj parametresiyle yuvarlanmış hedef de istenebilir', () => {
  // Varsayılan bir kuruş; 1₺ verilirse eski davranış (199₺'ye yuvarlama).
  const [varsayilan] = kargoEsikAdetleri(34.9, kargoAyarTest);
  const [yuvarlanmis] = kargoEsikAdetleri(34.9, kargoAyarTest, 1);
  assert.equal(varsayilan.altindaKalmak.siparisToplami, 199.99);
  assert.equal(varsayilan.altindaKalmak.indirimTutari, 9.41);
  assert.equal(yuvarlanmis.altindaKalmak.siparisToplami, 199);
  assert.equal(yuvarlanmis.altindaKalmak.indirimTutari, 10.4);
});
