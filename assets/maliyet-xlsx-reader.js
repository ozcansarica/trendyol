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

// Sayfanın tüm drawing dosyası yollarını (hem OOXML hem VML) döner.
async function findDrawingPaths(zip, sheetPath) {
  const parts = sheetPath.split('/');
  const sheetFile = parts.pop();
  const relsPath = `${parts.join('/')}/_rels/${sheetFile}.rels`;
  const relsDoc = await readXmlEntry(zip, relsPath);
  if (!relsDoc) return [];
  const paths = [];
  for (const rel of relsDoc.getElementsByTagName('Relationship')) {
    const type = rel.getAttribute('Type') || '';
    if (!type.toLowerCase().includes('drawing')) continue;
    const target = (rel.getAttribute('Target') || '').replace(/^\//, '');
    let resolved = target;
    if (target.startsWith('../')) resolved = `xl/${target.slice(3)}`;
    else if (!target.startsWith('xl/')) resolved = `xl/${target}`;
    paths.push(resolved);
  }
  return paths;
}

// rId → medya dosyası yolunu çözer ve data URL olarak döner.
async function rIdToDataUrl(zip, relsDoc, rId, drawingDir) {
  const relMap = {};
  for (const rel of relsDoc.getElementsByTagName('Relationship')) {
    const id = rel.getAttribute('Id');
    const target = rel.getAttribute('Target');
    if (id && target) relMap[id] = target;
  }
  const target = relMap[rId];
  if (!target) return null;
  let mediaPath = target.replace(/^\//, '');
  if (mediaPath.startsWith('../')) mediaPath = `xl/${mediaPath.slice(3)}`;
  else if (!mediaPath.startsWith('xl/') && !mediaPath.startsWith('xl')) mediaPath = `${drawingDir}/${mediaPath}`;
  const entry = zip.file(mediaPath);
  if (!entry) return null;
  const bytes = await entry.async('uint8array');
  return `data:${mimeFromPath(mediaPath)};base64,${uint8ToBase64(bytes)}`;
}

// OOXML drawing XML'den satır → rId çıkarır (regex tabanlı).
function parseOoxmlDrawingAnchors(xml) {
  const rowToRId = {};
  const anchorRe = /<xdr:(?:two|one)CellAnchor[\s\S]*?<\/xdr:(?:two|one)CellAnchor>/g;
  let m;
  while ((m = anchorRe.exec(xml)) !== null) {
    const block = m[0];
    const fromM = /<xdr:from>([\s\S]*?)<\/xdr:from>/.exec(block);
    const rowM = fromM && /<xdr:row>(\d+)<\/xdr:row>/.exec(fromM[1]);
    const rIdM = /r:embed=["']([^"']+)["']/.exec(block);
    if (rowM && rIdM) {
      const rowIdx = parseInt(rowM[1], 10);
      if (!(rowIdx in rowToRId)) rowToRId[rowIdx] = rIdM[1];
    }
  }
  return rowToRId;
}

// VML drawing XML'den satır → rId çıkarır (regex tabanlı).
// VML: <v:shape ...><v:imagedata r:id="rId1"/><x:ClientData><x:Row>N</x:Row></x:ClientData></v:shape>
// x:Row 0-tabanlıdır (Excel satır no − 1 ile aynı).
function parseVmlDrawingAnchors(xml) {
  const rowToRId = {};
  const shapeRe = /<v:shape\b[\s\S]*?<\/v:shape>/g;
  let m;
  while ((m = shapeRe.exec(xml)) !== null) {
    const block = m[0];
    const rIdM = /r:id=["']([^"']+)["']/.exec(block);
    if (!rIdM) continue;
    const rowM = /<x:Row>(\d+)<\/x:Row>/.exec(block);
    if (!rowM) continue;
    const rowIdx = parseInt(rowM[1], 10);
    if (!(rowIdx in rowToRId)) rowToRId[rowIdx] = rIdM[1];
  }
  return rowToRId;
}

// Tüm drawing dosyalarından 0-tabanlı satır indeksi → data URL eşlemesi çıkarır.
async function extractRowImages(zip, sheetPath) {
  const drawingPaths = await findDrawingPaths(zip, sheetPath);
  console.log('[maliyet-resim] drawing dosyaları:', drawingPaths);

  const result = {};

  for (const drawingPath of drawingPaths) {
    const entry = zip.file(drawingPath);
    if (!entry) {
      console.log('[maliyet-resim] dosya bulunamadı:', drawingPath);
      continue;
    }
    const xml = new TextDecoder('utf-8').decode(await entry.async('uint8array'));

    // Drawing rels
    const dParts = drawingPath.split('/');
    const dFile = dParts.pop();
    const dDir = dParts.join('/');
    const dRelsPath = `${dDir}/_rels/${dFile}.rels`;
    const dRelsDoc = await readXmlEntry(zip, dRelsPath);
    if (!dRelsDoc) {
      console.log('[maliyet-resim] rels bulunamadı:', dRelsPath);
      continue;
    }

    const isVml = drawingPath.endsWith('.vml');
    const rowToRId = isVml ? parseVmlDrawingAnchors(xml) : parseOoxmlDrawingAnchors(xml);
    console.log(`[maliyet-resim] ${isVml ? 'VML' : 'OOXML'} (${drawingPath}): ${Object.keys(rowToRId).length} resim bulundu`, rowToRId);

    for (const [rowIdxStr, rId] of Object.entries(rowToRId)) {
      if (parseInt(rowIdxStr, 10) in result) continue; // önceki drawing'den zaten var
      const dataUrl = await rIdToDataUrl(zip, dRelsDoc, rId, dDir);
      if (dataUrl) result[parseInt(rowIdxStr, 10)] = dataUrl;
    }
  }

  console.log('[maliyet-resim] toplam resim:', Object.keys(result).length);
  return result;
}

function num(v) {
  const n = typeof v === 'number' ? v : parseFloat(v);
  return Number.isFinite(n) ? n : 0;
}

/**
 * Maliyet Excel dosyasını (ArrayBuffer) barkod → maliyet eşlemesine çevirir.
 * Gömülü resimleri de çıkarır (barkod → data URL).
 * @param {ArrayBuffer} arrayBuffer
 * @returns {Promise<{kayitlar: Object<string, number>, resimler: Object<string, string>, resimSayisi: number, warnings: string[]}>}
 */
export async function parseMaliyetXlsx(arrayBuffer) {
  if (typeof JSZip === 'undefined') {
    throw new Error('JSZip yüklenemedi. Sayfayı yenileyip tekrar deneyin.');
  }
  const zip = await JSZip.loadAsync(arrayBuffer);

  // ZIP içeriğini logla (tanı için)
  const zipFiles = Object.keys(zip.files).filter(f => !zip.files[f].dir);
  console.log('[maliyet-resim] ZIP dosyaları:', zipFiles.filter(f => f.includes('draw') || f.includes('media') || f.includes('_rels')));

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
  // rowNum (1-tabanlı Excel satır no) → barkod — maliyet olsun olmasın tüm barkodlu satırlar
  const rowNumToBarkod = {};

  for (let r = 1; r < rows.length; r++) {
    const row = rows[r];
    if (!row) continue;
    const barkod = row[iBarkod];
    if (barkod == null || String(barkod).trim() === '') continue; // boş satır

    const barkodStr = String(barkod).trim();
    rowNumToBarkod[rowNums[r]] = barkodStr; // resim eşleştirmesi için her barkodlu satırı izle

    const maliyet = num(row[iAlis]);
    if (!maliyet) {
      warnings.push(`Barkod "${barkodStr}" için geçerli maliyet bulunamadı, atlandı.`);
      continue;
    }
    kayitlar[barkodStr] = maliyet;
  }

  if (Object.keys(kayitlar).length === 0) {
    throw new Error('Excel dosyasından hiç geçerli maliyet satırı okunamadı.');
  }

  // Gömülü resimleri çıkar (hata olursa sessizce atla)
  const resimler = {};
  try {
    const imagesByDrawingRow = await extractRowImages(zip, sheetPath);
    for (const [drawingRowStr, dataUrl] of Object.entries(imagesByDrawingRow)) {
      const excelRowNum = parseInt(drawingRowStr, 10) + 1; // 0-tabanlı → 1-tabanlı
      const barkod = rowNumToBarkod[excelRowNum];
      if (barkod) resimler[barkod] = dataUrl;
    }
    console.log('[maliyet-resim] barkoda eşlenen resim:', Object.keys(resimler).length);
  } catch (e) {
    console.warn('[maliyet-resim] resim okuma hatası:', e);
  }

  return { kayitlar, resimler, resimSayisi: Object.keys(resimler).length, warnings };
}
