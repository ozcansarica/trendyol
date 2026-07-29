// Trendyol "YıldızlıÜrünEtiketleri" formatındaki .xlsx dosyalarını tarayıcıda okur.
// "Yıldızlı Ürün" kampanyası için önerilen 1/2/3 yıldız fiyat noktalarını
// barkoda göre çıkarır. Bu dosyada komisyon oranı YOK — sadece fiyat
// noktaları var; kâr hesaplanırken bu fiyatın zaten yüklü ürün verisindeki
// hangi fiyat aralığına düştüğü bulunup o aralığın komisyonu kullanılır
// (bkz. index.html tierForPrice()).
// assets/xlsx-reader.js ile aynı JSZip + native DOMParser/TextDecoder yöntemini
// kullanır, bilinçli olarak bağımsız (leaf) modül — bkz. maliyet-xlsx-reader.js
// başındaki not (deploy.yml'nin cache-busting'i yalnızca index.html'i hedefler).
// JSZip global olarak yüklenmiş olmalı (bkz. index.html <script src="assets/vendor/jszip.min.js">).

const REQUIRED_COLUMNS = ['ÜRÜN İSMİ', 'BARKOD', '1 YILDIZ ÜST FİYAT', '2 YILDIZ ÜST FİYAT', '3 YILDIZ ÜST FİYAT'];

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

// Bu dosyadaki fiyat kolonları Türkçe ondalık virgülle metin olarak saklanıyor
// (ör. "12,30"); binlik nokta ayracını da destekler (ör. "1.234,56").
function turkceSayi(v) {
  if (v == null) return 0;
  if (typeof v === 'number') return v;
  const s = String(v).trim().replace(/\./g, '').replace(',', '.');
  const n = parseFloat(s);
  return Number.isFinite(n) ? n : 0;
}

/**
 * Yıldızlı Ürün Excel dosyasını (ArrayBuffer) barkod → {urun, yildiz1, yildiz2, yildiz3} eşlemesine çevirir.
 * yildiz1/2/3, ilgili yıldız seviyesinin "Üst Fiyat" sınırıdır (o seviyeyi
 * en az indirimle karşılayan, satıcı için en avantajlı fiyat noktası).
 * @param {ArrayBuffer} arrayBuffer
 * @returns {Promise<{yildizVeriler: Object<string, {urun: string, yildiz1: number, yildiz2: number, yildiz3: number}>, warnings: string[]}>}
 */
export async function parseYildizliUrunXlsx(arrayBuffer) {
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
    throw new Error(`Beklenen kolonlar bulunamadı: ${missing.join(', ')}. Dosyanın "YıldızlıÜrünEtiketleri" formatında olduğundan emin olun.`);
  }

  const iUrun = colIdx('ÜRÜN İSMİ');
  const iBarkod = colIdx('BARKOD');
  const iY1Ust = colIdx('1 YILDIZ ÜST FİYAT');
  const iY2Ust = colIdx('2 YILDIZ ÜST FİYAT');
  const iY3Ust = colIdx('3 YILDIZ ÜST FİYAT');

  const yildizVeriler = {};
  const warnings = [];

  for (let r = 1; r < rows.length; r++) {
    const row = rows[r];
    if (!row) continue;
    const barkod = row[iBarkod];
    if (barkod == null || String(barkod).trim() === '') continue; // boş satır

    const yildiz1 = turkceSayi(row[iY1Ust]);
    const yildiz2 = turkceSayi(row[iY2Ust]);
    const yildiz3 = turkceSayi(row[iY3Ust]);
    if (!yildiz1 && !yildiz2 && !yildiz3) {
      warnings.push(`Barkod "${barkod}" için geçerli yıldız fiyatı bulunamadı, atlandı.`);
      continue;
    }

    yildizVeriler[String(barkod).trim()] = {
      urun: row[iUrun] != null ? String(row[iUrun]).trim() : '',
      yildiz1,
      yildiz2,
      yildiz3,
    };
  }

  if (Object.keys(yildizVeriler).length === 0) {
    throw new Error('Excel dosyasından hiç geçerli yıldız fiyatı okunamadı.');
  }

  return { yildizVeriler, warnings };
}
