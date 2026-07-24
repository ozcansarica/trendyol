// Maliyet Girişi için "Barkod No" + "Alış Tutarı (KDV)" kolonlarını içeren
// .xlsx dosyalarını tarayıcıda okur (Trendyol "Aktif-Pasif Ürün" gibi export'lar).
// assets/xlsx-reader.js ile aynı JSZip + native DOMParser/TextDecoder yöntemini
// kullanır (bkz. oradaki not — SheetJS gibi hazır kütüphaneler bazı Türkçe
// karakterleri yanlış çözebiliyor). Ortak bir modüle ayrıştırılmıyor; her iki
// dosya da bilinçli olarak bağımsız (leaf) kalıyor, çünkü deploy.yml'nin
// cache-busting'i yalnızca index.html'deki import'ları günceller — assets
// arası bir import zinciri olsaydı, önceden yaşanan bayat sürüm önbellek
// sorunu geri gelirdi.
// JSZip global olarak yüklenmiş olmalı (bkz. index.html <script src="assets/vendor/jszip.min.js">).

const REQUIRED_COLUMNS = ['Barkod No', 'Alış Tutarı (KDV)'];

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

/**
 * Maliyet Excel dosyasını (ArrayBuffer) barkod → maliyet eşlemesine çevirir.
 * @param {ArrayBuffer} arrayBuffer
 * @returns {Promise<{kayitlar: Object<string, number>, warnings: string[]}>}
 */
export async function parseMaliyetXlsx(arrayBuffer) {
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
    throw new Error(`Beklenen kolonlar bulunamadı: ${missing.join(', ')}.`);
  }

  const iBarkod = colIdx('Barkod No');
  const iAlis = colIdx('Alış Tutarı (KDV)');

  const kayitlar = {};
  const warnings = [];

  for (let r = 1; r < rows.length; r++) {
    const row = rows[r];
    if (!row) continue;
    const barkod = row[iBarkod];
    if (barkod == null || String(barkod).trim() === '') continue; // boş satır

    const maliyet = num(row[iAlis]);
    if (!maliyet) {
      warnings.push(`Barkod "${barkod}" için geçerli maliyet bulunamadı, atlandı.`);
      continue;
    }
    kayitlar[String(barkod).trim()] = maliyet;
  }

  if (Object.keys(kayitlar).length === 0) {
    throw new Error('Excel dosyasından hiç geçerli maliyet satırı okunamadı.');
  }

  return { kayitlar, warnings };
}
