// Trendyol "KomisyonTarifeleriÜrünleri" formatındaki .xlsx dosyalarını tarayıcıda okur.
// JSZip ile ZIP'i açar, XML içeriğini tarayıcının yerleşik TextDecoder + DOMParser'ıyla
// çözer (üçüncü parti kütüphanelerin bazı Türkçe karakterleri (Ç, Ğ vb.) yanlış
// çözebildiği gözlemlendiği için bilinçli olarak SheetJS gibi hazır .xlsx kütüphaneleri
// yerine bu minimal, doğrulanmış yol kullanılıyor).
// JSZip global olarak yüklenmiş olmalı (bkz. index.html <script src="assets/vendor/jszip.min.js">).

// Beklenen kolon başlıkları (Excel'in ilk satırı). Sıra değişebilir, isimle eşleştirilir.
// Not: "Maliyet" kolonu artık zorunlu değil — maliyetler sayfadaki Maliyet Girişi
// panelinden barkoda göre giriliyor (bkz. index.html applyMaliyetKayitlari()).
// Eski format dosyalarda "Maliyet" kolonu varsa yine okunur (aşağıya bakın).
//
// Tarife grupları ("Tarih aralığı (N Gün)" + ardından gelen 4 komisyon kolonu)
// dosyaya göre değişebilir: bazı haftalarda 3 Gün + 4 Gün, bazılarında yalnızca
// 7 Gün gibi tek bir grup olabilir. Bu yüzden sabit kolon adı aramak yerine
// başlık satırındaki tüm "Tarih aralığı (N Gün)" kalıpları dinamik olarak
// bulunur (bkz. TARIH_ARALIGI_REGEX).
const TARIH_ARALIGI_REGEX = /^Tarih aralığı \((\d+) Gün\)$/;

const REQUIRED_COLUMNS = [
  'ÜRÜN İSMİ',
  '1.Fiyat Alt Limit', '2.Fiyat Üst Limiti', '2.Fiyat Alt Limit',
  '3.Fiyat Üst Limiti', '3.Fiyat Alt Limit', '4.Fiyat Üst Limiti',
  'KOMİSYONA ESAS FİYAT', 'GÜNCEL KOMİSYON', 'GÜNCEL TSF',
];

function colIndexFromRef(ref) {
  const m = ref.match(/^([A-Z]+)/);
  let col = 0;
  for (const ch of m[1]) col = col * 26 + (ch.charCodeAt(0) - 64);
  return col - 1;
}

async function readXmlEntry(zip, path) {
  const entry = zip.file(path);
  if (!entry) return null;
  const bytes = await entry.async('uint8array');
  const text = new TextDecoder('utf-8').decode(bytes);
  return new DOMParser().parseFromString(text, 'application/xml');
}

// Çalışma kitabının ilk sayfasının gerçek dosya yolunu bulur (workbook.xml sırasına göre).
async function findFirstSheetPath(zip) {
  const wbDoc = await readXmlEntry(zip, 'xl/workbook.xml');
  const relsDoc = await readXmlEntry(zip, 'xl/_rels/workbook.xml.rels');
  if (!wbDoc || !relsDoc) return 'xl/worksheets/sheet1.xml'; // yedek varsayım

  const sheetEls = wbDoc.getElementsByTagName('sheet');
  if (sheetEls.length === 0) return 'xl/worksheets/sheet1.xml';
  const rId = sheetEls[0].getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id')
    || sheetEls[0].getAttribute('r:id');

  const relEls = relsDoc.getElementsByTagName('Relationship');
  for (const rel of relEls) {
    if (rel.getAttribute('Id') === rId) {
      const target = rel.getAttribute('Target').replace(/^\//, '');
      return target.startsWith('xl/') ? target : `xl/${target}`;
    }
  }
  return 'xl/worksheets/sheet1.xml';
}

async function sheetToRows(zip) {
  const sstDoc = await readXmlEntry(zip, 'xl/sharedStrings.xml');
  const sst = [];
  if (sstDoc) {
    for (const si of sstDoc.getElementsByTagName('si')) {
      let text = '';
      for (const t of si.getElementsByTagName('t')) text += t.textContent;
      sst.push(text);
    }
  }

  const sheetPath = await findFirstSheetPath(zip);
  const sheetDoc = await readXmlEntry(zip, sheetPath);
  if (!sheetDoc) throw new Error(`Çalışma sayfası bulunamadı: ${sheetPath}`);

  const rows = [];
  for (const rowEl of sheetDoc.getElementsByTagName('row')) {
    const row = [];
    for (const c of rowEl.getElementsByTagName('c')) {
      const ref = c.getAttribute('r');
      if (!ref) continue;
      const idx = colIndexFromRef(ref);
      const t = c.getAttribute('t');
      const vEl = c.getElementsByTagName('v')[0];
      let val = null;
      if (t === 's') val = vEl ? sst[parseInt(vEl.textContent, 10)] : null;
      else if (t === 'str') val = vEl ? vEl.textContent : null;
      else if (t === 'inlineStr') {
        const isT = c.getElementsByTagName('t')[0];
        val = isT ? isT.textContent : null;
      } else if (vEl) val = parseFloat(vEl.textContent);
      row[idx] = val;
    }
    rows.push(row);
  }
  return rows;
}

function num(v) {
  const n = typeof v === 'number' ? v : parseFloat(v);
  return Number.isFinite(n) ? n : 0;
}

// Başlık satırındaki "Tarih aralığı (N Gün)" kolonlarını bulur. Her biri için
// gün sayısı ve o kolonu takip eden 4 komisyon kolonunun konumu döner.
// Dosyada 3 Gün + 4 Gün gibi birden fazla grup, ya da yalnızca 7 Gün gibi
// tek bir grup olabilir — hepsi otomatik tanınır.
function bulTarifeGruplari(header) {
  const gruplar = [];
  header.forEach((h, idx) => {
    const m = h.match(TARIH_ARALIGI_REGEX);
    if (m) gruplar.push({ gun: m[1], tarihIdx: idx });
  });
  return gruplar;
}

/**
 * Excel dosyasını (ArrayBuffer) URUNLER dizisi formatına dönüştürür.
 * @param {ArrayBuffer} arrayBuffer
 * @returns {Promise<{urunler: object[], warnings: string[]}>}
 */
export async function parseUrunlerXlsx(arrayBuffer) {
  if (typeof JSZip === 'undefined') {
    throw new Error('JSZip yüklenemedi. Sayfayı yenileyip tekrar deneyin.');
  }
  const zip = await JSZip.loadAsync(arrayBuffer);
  const rows = await sheetToRows(zip);
  if (rows.length < 2) throw new Error('Excel dosyasında veri satırı bulunamadı.');

  const header = rows[0].map(h => (h == null ? '' : String(h).trim()));
  const colIdx = name => header.indexOf(name);

  const missing = REQUIRED_COLUMNS.filter(name => colIdx(name) === -1);
  if (missing.length > 0) {
    throw new Error(`Beklenen kolonlar bulunamadı: ${missing.join(', ')}. Dosyanın "KomisyonTarifeleriÜrünleri" formatında olduğundan emin olun.`);
  }

  const tarifeGruplari = bulTarifeGruplari(header);
  if (tarifeGruplari.length === 0) {
    throw new Error('Hiçbir tarife grubu bulunamadı (ör. "Tarih aralığı (3 Gün)" gibi bir kolon bekleniyor).');
  }

  const iUrun = colIdx('ÜRÜN İSMİ');
  const iBarkod = colIdx('BARKOD');
  const iModel = colIdx('MODEL KODU');
  const iKategori = colIdx('KATEGORİ');
  const iMarka = colIdx('MARKA');
  const iStok = colIdx('STOK');
  const iMaliyet = colIdx('Maliyet');
  const iF1Alt = colIdx('1.Fiyat Alt Limit');
  const iF2Ust = colIdx('2.Fiyat Üst Limiti');
  const iF2Alt = colIdx('2.Fiyat Alt Limit');
  const iF3Ust = colIdx('3.Fiyat Üst Limiti');
  const iF3Alt = colIdx('3.Fiyat Alt Limit');
  const iF4Ust = colIdx('4.Fiyat Üst Limiti');
  const iKomisyonFiyat = colIdx('KOMİSYONA ESAS FİYAT');
  const iGuncelKomisyon = colIdx('GÜNCEL KOMİSYON');
  const iGuncelTsf = colIdx('GÜNCEL TSF');

  const urunler = [];
  const warnings = [];

  for (let r = 1; r < rows.length; r++) {
    const row = rows[r];
    if (!row) continue;
    const urunAdi = row[iUrun];
    if (urunAdi == null || String(urunAdi).trim() === '') continue; // boş satır

    // Excel'de "Maliyet" kolonu yoksa 0 kabul edilir; sayfa açılışında
    // applyMaliyetKayitlari() barkoda göre kayıtlı maliyeti otomatik uygular.
    const maliyet = iMaliyet !== -1 ? num(row[iMaliyet]) : 0;
    const f1Alt = num(row[iF1Alt]);
    const guncelTsf = num(row[iGuncelTsf]);
    if (!f1Alt || !guncelTsf) {
      warnings.push(`"${urunAdi}" satırı eksik fiyat verisi nedeniyle atlandı.`);
      continue;
    }

    const tarifeler = {};
    for (const g of tarifeGruplari) {
      tarifeler[g.gun] = {
        tarih: row[g.tarihIdx] != null ? String(row[g.tarihIdx]) : '',
        k: [num(row[g.tarihIdx + 1]), num(row[g.tarihIdx + 2]), num(row[g.tarihIdx + 3]), num(row[g.tarihIdx + 4])],
      };
    }

    urunler.push({
      urun: String(urunAdi).trim(),
      barkod: row[iBarkod] != null ? String(row[iBarkod]) : '',
      model: row[iModel] != null ? String(row[iModel]) : '',
      kategori: row[iKategori] != null ? String(row[iKategori]) : '',
      marka: row[iMarka] != null ? String(row[iMarka]) : '',
      stok: num(row[iStok]),
      maliyet,
      f1_alt: f1Alt,
      f2_ust: num(row[iF2Ust]),
      f2_alt: num(row[iF2Alt]),
      f3_ust: num(row[iF3Ust]),
      f3_alt: num(row[iF3Alt]),
      f4_ust: num(row[iF4Ust]),
      tarifeler,
      komisyon_fiyat: num(row[iKomisyonFiyat]),
      guncel_komisyon: num(row[iGuncelKomisyon]),
      guncel_tsf: guncelTsf,
    });
  }

  if (urunler.length === 0) {
    throw new Error('Excel dosyasından hiç geçerli ürün satırı okunamadı.');
  }

  return { urunler, warnings };
}
