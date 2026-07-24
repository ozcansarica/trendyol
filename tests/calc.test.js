// Referans senaryoya göre hesaplama testleri.
// Çalıştırma:  node --test
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { computeMaliyet, round2, hizmetBedeli } from '../assets/calc.js';

test('referans senaryo (satis 189, alis 30, kom %22.5, kdv %20, kargo 42)', () => {
  const r = computeMaliyet({ satis: 189, alis: 30, komPct: 22.5, kdvPct: 20, kargo: 42 });
  assert.equal(round2(r.komisyon), 42.53);
  assert.equal(round2(r.roi), 213.98);
  assert.equal(round2(r.hizmet), 8.39);
  assert.equal(round2(r.stopaj), 1.58);
  assert.equal(round2(r.karOrani), 178.32);
  assert.equal(round2(r.odenecekKdv), 11.01);
  assert.equal(round2(r.satisKdv), 31.5);
  assert.equal(round2(r.alisKdv), 5);
  assert.equal(round2(r.kargoKdv), 7);
  assert.equal(round2(r.komKdv), 7.09);
  assert.equal(round2(r.hizmetKdv), 1.4);
  assert.equal(round2(r.kar), 53.5);
});

test('hizmet bedeli her zaman sabit 8.39', () => {
  assert.equal(hizmetBedeli(20), 8.39);
  assert.equal(hizmetBedeli(189), 8.39);
  assert.equal(hizmetBedeli(400), 8.39);
  assert.equal(hizmetBedeli(1000), 8.39);
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
