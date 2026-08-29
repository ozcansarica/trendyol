// Referans senaryoya göre hesaplama testleri.
// Çalıştırma:  node --test
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { computeMaliyet, round2, hizmetBedeli, zorunluSiparisAdedi, baremIndirimAnalizi } from '../assets/calc.js';

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

test('barem indirimi: 240₺ → 199₺ kargo eşiğinin altına inince kâr artar', () => {
  const r = baremIndirimAnalizi({
    birimFiyat: 240, maliyet: 100, kdvPct: 20, hizmet: 13.19,
    kargoIcin: kargoIcinTest,
    komisyonIcin: () => 20,
    baremler: [
      { esik: 200, kapsam: 'toplam', tip: 'kargo' },
      { esik: 350, kapsam: 'toplam', tip: 'kargo' },
    ],
  });

  assert.equal(r.mevcut.kargo, 78);
  assert.equal(round2(r.mevcut.kar), -1.33);

  // 350₺ eşiği mevcut fiyatın üstünde kaldığı için indirim adayı üretmez.
  assert.equal(r.adaylar.length, 1);
  const a = r.adaylar[0];
  assert.equal(a.birimFiyat, 199); // 200 eşiği − BAREM_MARJI
  assert.equal(a.kargo, 42);
  assert.equal(a.indirimTutari, 41);
  assert.equal(round2(a.kar), 1.68);
  assert.equal(round2(a.karFarki), 3.01);
  assert.equal(r.enIyi, a);
});

test('barem indirimi: kârı düşüren aday enIyi olarak seçilmez', () => {
  // Düşük komisyonda 41₺'lik fiyat indirimi, kargo (36₺) + komisyon kazancından
  // büyük kalır; aday yine listelenir ama enIyi olarak seçilmez.
  const r = baremIndirimAnalizi({
    birimFiyat: 240, maliyet: 100, kdvPct: 20, hizmet: 13.19,
    kargoIcin: kargoIcinTest,
    komisyonIcin: () => 8,
    baremler: [{ esik: 200, kapsam: 'toplam', tip: 'kargo' }],
  });
  assert.equal(r.adaylar.length, 1);
  assert.equal(round2(r.adaylar[0].karFarki), -1.09);
  assert.equal(r.enIyi, null);
});

test('barem indirimi: birim kapsamlı eşik (komisyon aralığı) tam eşiğe iner', () => {
  const r = baremIndirimAnalizi({
    birimFiyat: 180, maliyet: 60, kdvPct: 20, hizmet: 13.19,
    kargoIcin: kargoIcinTest,
    komisyonIcin: (fiyat) => (fiyat <= 150 ? 15 : 25),
    mevcutKomisyon: 25,
    baremler: [{ esik: 150, kapsam: 'birim', tip: 'komisyon' }],
  });
  assert.equal(r.mevcut.komisyon, 25);
  assert.equal(r.adaylar.length, 1);
  // 'birim' kapsamda aralıklar "X ₺ ve altı" tanımlı → hedef eşiğin kendisi.
  assert.equal(r.adaylar[0].birimFiyat, 150);
  assert.equal(r.adaylar[0].komisyon, 15);
});

test('barem indirimi: zorunlu adet artınca hedef toplam yine eşiğin altında kalır', () => {
  // 60₺ eşiği: birim fiyat düştükçe zorunlu adet 1 → 2 → 4 → 6 büyür,
  // hedef birim fiyat da adede bölünerek sabit noktaya yakınsar.
  const r = baremIndirimAnalizi({
    birimFiyat: 80, maliyet: 20, kdvPct: 20, hizmet: 13.19,
    kargoIcin: kargoIcinTest,
    komisyonIcin: () => 20,
    baremler: [{ esik: 60, kapsam: 'toplam' }],
  });
  const a = r.adaylar[0];
  assert.equal(a.adet, 6);
  assert.equal(a.birimFiyat, 9.83); // kuruş küsuratı aşağı kırpılır
  assert.equal(a.siparisToplami, 58.98);
  assert.ok(a.siparisToplami < 60, 'sipariş toplamı barem eşiğinin altında kalmalı');
});

test('barem indirimi: aynı fiyata denk gelen baremler tek adayda birleşir', () => {
  const r = baremIndirimAnalizi({
    birimFiyat: 240, maliyet: 100, kdvPct: 20, hizmet: 13.19,
    kargoIcin: kargoIcinTest,
    komisyonIcin: () => 20,
    baremler: [
      { esik: 200, kapsam: 'toplam', tip: 'kargo' },
      { esik: 199, kapsam: 'birim', tip: 'komisyon' }, // aynı hedef: 199₺
    ],
  });
  assert.equal(r.adaylar.length, 1);
  assert.equal(r.adaylar[0].baremler.length, 2);
});

test('barem indirimi: komisyonu bilinmeyen fiyat için aday üretilmez', () => {
  const r = baremIndirimAnalizi({
    birimFiyat: 240, maliyet: 100, kdvPct: 20, hizmet: 13.19,
    kargoIcin: kargoIcinTest,
    komisyonIcin: (fiyat) => (fiyat > 200 ? 20 : 0), // 199₺'de komisyon tanımsız
    baremler: [{ esik: 200, kapsam: 'toplam' }],
  });
  assert.equal(r.adaylar.length, 0);
  assert.equal(r.enIyi, null);
});

test('barem indirimi: maxIndirimYuzdesi uzak baremleri eler', () => {
  const ortak = {
    birimFiyat: 240, maliyet: 100, kdvPct: 20, hizmet: 13.19,
    kargoIcin: kargoIcinTest,
    komisyonIcin: () => 20,
    // 200₺ eşiği %17.08, 150₺ eşiği %37.92 indirim gerektirir.
    baremler: [{ esik: 200, kapsam: 'toplam' }, { esik: 150, kapsam: 'toplam' }],
  };
  assert.equal(baremIndirimAnalizi(ortak).adaylar.length, 2);

  const sinirli = baremIndirimAnalizi({ ...ortak, maxIndirimYuzdesi: 20 });
  assert.equal(sinirli.adaylar.length, 1);
  assert.equal(sinirli.adaylar[0].birimFiyat, 199);
  assert.equal(sinirli.adaylar[0].indirimYuzdesi, 17.08);

  // Sınır indirim oranının tam üstündeyse aday elenmez.
  assert.equal(baremIndirimAnalizi({ ...ortak, maxIndirimYuzdesi: 17.08 }).adaylar.length, 1);
  assert.equal(baremIndirimAnalizi({ ...ortak, maxIndirimYuzdesi: 17 }).adaylar.length, 0);
});
