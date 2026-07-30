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

// Drawing XML ad alanları
const XDR_NS = 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing';
const A_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';
const R_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

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

// Satırları ve her satırın Excel satır numarasını (1-tabanlı, <row r="N">) döner.
async function sheetToRowsWithNums(zip) {
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
  const rowNums = [];
  for (const rowEl of sheetDoc.getElementsByTagName('row')) {
    const rNum = parseInt(rowEl.getAttribute('r') || '0', 10);
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
    rowNums.push(rNum);
  }
  return { rows, rowNums, sheetPath };
}

// Uint8Array → base64 (büyük diziler için yığın yığın işler)
function uint8ToBase64(bytes) {
  let bin = '';
  const chunk = 8192;
  for (let i = 0; i < bytes.length; i += chunk) {
    bin += String.fromCharCode.apply(null, bytes.subarray(i, i + chunk));
  }
  return btoa(bin);
}

function mimeFromPath(path) {
  const ext = path.split('.').pop().toLowerCase();
  const map = { png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg', gif: 'image/gif', webp: 'image/webp', bmp: 'image/bmp' };
  return map[ext] || 'image/png';
}

// Sayfanın drawing dosyası yolunu ilişki (rels) dosyasından bulur.
async function findDrawingPath(zip, sheetPath) {
  const parts = sheetPath.split('/');
  const sheetFile = parts.pop();
  const relsPath = `${parts.join('/')}/_rels/${sheetFile}.rels`;
  const relsDoc = await readXmlEntry(zip, relsPath);
  if (!relsDoc) return null;
  for (const rel of relsDoc.getElementsByTagName('Relationship')) {
    const type = rel.getAttribute('Type') || '';
    if (!type.includes('drawing')) continue;
    const target = rel.getAttribute('Target').replace(/^\//, '');
    if (target.startsWith('../')) return `xl/${target.slice(3)}`;
    return target.startsWith('xl/') ? target : `xl/${target}`;
  }
  return null;
}

// Drawing XML'den D sütunundaki (0-tabanlı sütun 3) resimleri çıkarır.
// Dönen nesne: { [drawingRowIdx]: dataURL } (drawingRowIdx 0-tabanlı = Excel satır no − 1)
async function extractRowImages(zip, sheetPath) {
  const drawingPath = await findDrawingPath(zip, sheetPath);
  if (!drawingPath) return {};

  const drawingDoc = await readXmlEntry(zip, drawingPath);
  if (!drawingDoc) return {};

  // Drawing rels yolu
  const dParts = drawingPath.split('/');
  const dFile = dParts.pop();
  const dRelsPath = `${dParts.join('/')}/_rels/${dFile}.rels`;
  const dRelsDoc = await readXmlEntry(zip, dRelsPath);
  if (!dRelsDoc) return {};

  // rId → medya dosyası yolu
  const relMap = {};
  for (const rel of dRelsDoc.getElementsByTagName('Relationship')) {
    const id = rel.getAttribute('Id');
    const target = rel.getAttribute('Target');
    if (id && target) relMap[id] = target;
  }

  // Drawing anchors: D sütunu (col === 3), 0-tabanlı satır → rId
  const rowToRId = {};
  for (const anchor of drawingDoc.getElementsByTagNameNS(XDR_NS, 'twoCellAnchor')) {
    const fromEl = anchor.getElementsByTagNameNS(XDR_NS, 'from')[0];
    if (!fromEl) continue;
    const colEl = fromEl.getElementsByTagNameNS(XDR_NS, 'col')[0];
    const rowEl = fromEl.getElementsByTagNameNS(XDR_NS, 'row')[0];
    if (!colEl || !rowEl) continue;
    if (parseInt(colEl.textContent, 10) !== 3) continue; // yalnızca D sütunu
    const blipEl = anchor.getElementsByTagNameNS(A_NS, 'blip')[0];
    if (!blipEl) continue;
    const rId = blipEl.getAttributeNS(R_NS, 'embed') || blipEl.getAttribute('r:embed');
    if (rId) rowToRId[parseInt(rowEl.textContent, 10)] = rId;
  }

  // rId → data URL
  const result = {};
  for (const [rowIdxStr, rId] of Object.entries(rowToRId)) {
    const target = relMap[rId];
    if (!target) continue;
    let mediaPath = target.replace(/^\//, '');
    if (mediaPath.startsWith('../')) mediaPath = `xl/${mediaPath.slice(3)}`;
    else if (!mediaPath.startsWith('xl/')) mediaPath = `xl/${mediaPath}`;
    const entry = zip.file(mediaPath);
    if (!entry) continue;
    const bytes = await entry.async('uint8array');
    result[parseInt(rowIdxStr, 10)] = `data:${mimeFromPath(mediaPath)};base64,${uint8ToBase64(bytes)}`;
  }
  return result; // 0-tabanlı drawing satır indeksi → data URL
}

function num(v) {
  const n = typeof v === 'number' ? v : parseFloat(v);
  return Number.isFinite(n) ? n : 0;
}

/**
 * Maliyet Excel dosyasını (ArrayBuffer) barkod → maliyet eşlemesine çevirir.
 * D sütunundaki gömülü resimleri de çıkarır (barkod → data URL).
 * @param {ArrayBuffer} arrayBuffer
 * @returns {Promise<{kayitlar: Object<string, number>, resimler: Object<string, string>, warnings: string[]}>}
 */
export async function parseMaliyetXlsx(arrayBuffer) {
  if (typeof JSZip === 'undefined') {
    throw new Error('JSZip yüklenemedi. Sayfayı yenileyip tekrar deneyin.');
  }
  const zip = await JSZip.loadAsync(arrayBuffer);
  const { rows, rowNums, sheetPath } = await sheetToRowsWithNums(zip);
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
  // rowNum (1-tabanlı Excel satır no) → barkod (veri satırları için)
  const rowNumToBarkod = {};

  for (let r = 1; r < rows.length; r++) {
    const row = rows[r];
    if (!row) continue;
    const barkod = row[iBarkod];
    if (barkod == null || String(barkod).trim() === '') continue; // boş satır

    const barkodStr = String(barkod).trim();
    const maliyet = num(row[iAlis]);
    if (!maliyet) {
      warnings.push(`Barkod "${barkodStr}" için geçerli maliyet bulunamadı, atlandı.`);
      continue;
    }
    kayitlar[barkodStr] = maliyet;
    rowNumToBarkod[rowNums[r]] = barkodStr;
  }

  if (Object.keys(kayitlar).length === 0) {
    throw new Error('Excel dosyasından hiç geçerli maliyet satırı okunamadı.');
  }

  // D sütunundaki gömülü resimleri çıkar (hata olursa sessizce atla)
  const resimler = {};
  try {
    const imagesByDrawingRow = await extractRowImages(zip, sheetPath);
    // Drawing satır indeksi 0-tabanlı = Excel satır no − 1
    for (const [drawingRowStr, dataUrl] of Object.entries(imagesByDrawingRow)) {
      const excelRowNum = parseInt(drawingRowStr, 10) + 1;
      const barkod = rowNumToBarkod[excelRowNum];
      if (barkod) resimler[barkod] = dataUrl;
    }
  } catch {
    // Resim okuma hatası kritik değil — veri olmadan devam et
  }

  return { kayitlar, resimler, warnings };
}
