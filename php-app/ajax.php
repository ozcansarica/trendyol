<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/TrendyolApi.php';

header('Content-Type: application/json; charset=utf-8');

requireLogin();
$magaza   = authMagaza();
$magazaId = (int)($magaza['id'] ?? 0);
if (!$magazaId) { http_response_code(403); echo json_encode(['error'=>'Mağaza seçilmemiş']); exit; }

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        // ------ Ödeme Detay: API'den senkronize et ------
        case 'sync_odeme_detay':
            $startDate = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate   = $_POST['end_date']   ?? date('Y-m-d');
            // Tarih doğrulama
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $startDate = date('Y-m-d', strtotime('-30 days'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate))   $endDate   = date('Y-m-d');
            if ($startDate > $endDate) { echo json_encode(['error' => 'Başlangıç tarihi bitiş tarihinden büyük olamaz']); break; }
            $api    = new TrendyolApi($magaza);
            $result = $api->syncOdemeDetay($startDate, $endDate);
            echo json_encode($result);
            break;

        // ------ AI Analiz ------
        case 'ai_analiz':
            $anthropicKey = $magaza['anthropic_api_key'] ?? '';
            if (strlen($anthropicKey) < 20) {
                echo json_encode(['error' => 'Anthropic API Key girilmemiş. Ayarlar sayfasından ekleyin.']);
                break;
            }
            $tip    = $_POST['tip']   ?? '';
            $donem  = $_POST['donem'] ?? 'bu_ay';
            if (!in_array($donem, ['bu_ay','gecen_ay','son_3_ay'])) $donem = 'bu_ay';

            // Dönem filtresini DD.MM.YYYY formatına göre hesapla
            // siparis_tarihi kolonu "28.04.2026" formatında
            $buYil  = (int)date('Y');
            $buAy   = (int)date('n');

            // Her dönem için BETWEEN koşulu oluştur (string karşılaştırma için MM.YYYY)
            $donemLabel = '';
            $aiWhere = "s.magaza_id=? AND s.siparis_statusu NOT LIKE '%İptal%' AND s.siparis_statusu NOT LIKE '%Cancel%'";
            $aiPrms  = [$magazaId];

            if ($donem === 'bu_ay') {
                $mm = sprintf('%02d', $buAy);
                $yyyy = $buYil;
                $aiWhere .= " AND SUBSTRING(s.siparis_tarihi,4,2)=? AND SUBSTRING(s.siparis_tarihi,7,4)=?";
                $aiPrms[] = $mm;
                $aiPrms[] = (string)$yyyy;
                $donemLabel = date('F Y');
            } elseif ($donem === 'gecen_ay') {
                $gecenAy = $buAy === 1 ? 12 : $buAy - 1;
                $gecenYil = $buAy === 1 ? $buYil - 1 : $buYil;
                $mm = sprintf('%02d', $gecenAy);
                $aiWhere .= " AND SUBSTRING(s.siparis_tarihi,4,2)=? AND SUBSTRING(s.siparis_tarihi,7,4)=?";
                $aiPrms[] = $mm;
                $aiPrms[] = (string)$gecenYil;
                $donemLabel = date('F Y', mktime(0,0,0,$gecenAy,1,$gecenYil));
            } elseif ($donem === 'son_3_ay') {
                // Son 3 ayın MM.YYYY çiftlerini oluştur ve OR ile birleştir
                $ayKosullari = [];
                for ($i = 0; $i < 3; $i++) {
                    $a = $buAy - $i;
                    $y = $buYil;
                    if ($a <= 0) { $a += 12; $y--; }
                    $ayKosullari[] = "(SUBSTRING(s.siparis_tarihi,4,2)=? AND SUBSTRING(s.siparis_tarihi,7,4)=?)";
                    $aiPrms[] = sprintf('%02d', $a);
                    $aiPrms[] = (string)$y;
                }
                $aiWhere .= " AND (" . implode(' OR ', $ayKosullari) . ")";
                $donemLabel = 'Son 3 Ay';
            }

            // Veri hazırla
            $veri = [];
            switch ($tip) {

                case 'stratejik':
                    // Genel KPI + önceki dönem karşılaştırma
                    $kpi = DB::row("
                        SELECT COUNT(*) AS siparis, SUM(siparis_tutari) AS ciro,
                               SUM(ABS(komisyon)) AS komisyon, SUM(ABS(gonderi_kargo)+ABS(iade_kargo)) AS kargo,
                               SUM(ABS(platform_hizmet)) AS platform, SUM(net_tutar) AS net_tutar,
                               SUM(urun_adedi) AS adet
                        FROM siparisler s WHERE $aiWhere", $aiPrms);
                    $maliyet = (float)DB::scalar("
                        SELECT COALESCE(SUM(s.urun_adedi*(m.birim_maliyet+m.kargo_maliyeti+m.paket_maliyeti+m.diger_maliyet)),0)
                        FROM siparisler s JOIN maliyetler m ON s.ty_urun_id=m.ty_urun_id AND m.magaza_id=s.magaza_id
                        WHERE $aiWhere", $aiPrms);
                    // Reklam harcamasını da dönem bazlı filtrele
                    // reklamlar.baslangic_tarihi formatına göre filtre
                    // Reklam tarihi hem DD.MM.YYYY hem YYYY-MM-DD formatında olabilir
                    // Her iki formattan ay ve yıl çıkar, karşılaştır
                    $reklamAyList = []; // [['ay'=>'05','yil'=>'2026'], ...]
                    if ($donem === 'bu_ay') {
                        $reklamAyList[] = ['ay'=>sprintf('%02d',$buAy),'yil'=>(string)$buYil];
                    } elseif ($donem === 'gecen_ay') {
                        $ga=$buAy===1?12:$buAy-1; $gy=$buAy===1?$buYil-1:$buYil;
                        $reklamAyList[] = ['ay'=>sprintf('%02d',$ga),'yil'=>(string)$gy];
                    } elseif ($donem === 'son_3_ay') {
                        for ($i=0;$i<3;$i++) {
                            $a=$buAy-$i; $y=$buYil; if($a<=0){$a+=12;$y--;}
                            $reklamAyList[] = ['ay'=>sprintf('%02d',$a),'yil'=>(string)$y];
                        }
                    }
                    $rk = []; $reklamPrms = [$magazaId];
                    foreach ($reklamAyList as $ral) {
                        // DD.MM.YYYY: pos4=ay, pos7=yil | YYYY-MM-DD: pos1=yil, pos6=ay
                        $rk[] = "(
                            (baslangic_tarihi REGEXP '^[0-9]{2}\\.[0-9]{2}\\.[0-9]{4}' AND SUBSTRING(baslangic_tarihi,4,2)=? AND SUBSTRING(baslangic_tarihi,7,4)=?)
                            OR
                            (baslangic_tarihi REGEXP '^[0-9]{4}-[0-9]{2}' AND SUBSTRING(baslangic_tarihi,6,2)=? AND SUBSTRING(baslangic_tarihi,1,4)=?)
                        )";
                        $reklamPrms[] = $ral['ay']; $reklamPrms[] = $ral['yil'];
                        $reklamPrms[] = $ral['ay']; $reklamPrms[] = $ral['yil'];
                    }
                    $reklamWhere = "magaza_id=?" . (count($rk) ? " AND (".implode(' OR ',$rk).")" : "");
                    $reklam = (float)DB::scalar("SELECT COALESCE(SUM(harcama),0) FROM reklamlar WHERE $reklamWhere", $reklamPrms);
                    $kar = (float)$kpi['net_tutar'] - $maliyet - $reklam;
                    $marj = $kpi['ciro'] > 0 ? round($kar/$kpi['ciro']*100,1) : 0;
                    $veri = [
                        'dönem' => $donemLabel,
                        'sipariş_sayısı' => (int)$kpi['siparis'],
                        'brüt_ciro_TL' => round((float)$kpi['ciro'],2),
                        'net_tutar_TL' => round((float)$kpi['net_tutar'],2),
                        'komisyon_TL' => round((float)$kpi['komisyon'],2),
                        'kargo_TL' => round((float)$kpi['kargo'],2),
                        'platform_TL' => round((float)$kpi['platform'],2),
                        'ürün_maliyeti_TL' => round($maliyet,2),
                        'reklam_harcaması_TL' => round($reklam,2),
                        'net_kâr_TL' => round($kar,2),
                        'net_kâr_marjı_yüzde' => $marj,
                    ];
                    $prompt = "Sen bir e-ticaret karlılık uzmanısın. Markdown kullanma (##, **, * gibi işaretler kullanma).  Aşağıdaki Trendyol mağaza verilerini analiz et ve stratejik bir değerlendirme yaz. Güçlü ve zayıf noktalara, kritik risklere ve somut önerilere odaklan. Yanıtı 4-5 madde halinde, her madde 2-3 cümle olacak şekilde ver. Maddeler emoji ile başlasın (🔴 kritik, 🟡 dikkat, 🟢 iyi). JSON değil düz metin ver.\n\nVeri:\n" . json_encode($veri, JSON_UNESCAPED_UNICODE);
                    break;

                case 'kategori':
                    $rows = DB::rows("
                        SELECT tu.category_name AS kategori,
                               COUNT(DISTINCT s.id) AS siparis, SUM(s.urun_adedi) AS adet,
                               SUM(s.siparis_tutari) AS ciro, SUM(s.net_tutar) AS net_tutar,
                               COALESCE(SUM(s.urun_adedi*(m.birim_maliyet+m.kargo_maliyeti+m.paket_maliyeti+m.diger_maliyet)),0) AS maliyet
                        FROM siparisler s
                        JOIN trendyol_urunler tu ON s.ty_urun_id=tu.ty_id AND tu.magaza_id=s.magaza_id
                        LEFT JOIN maliyetler m ON s.ty_urun_id=m.ty_urun_id AND m.magaza_id=s.magaza_id
                        WHERE $aiWhere GROUP BY tu.category_name ORDER BY ciro DESC LIMIT 15", $aiPrms);
                    foreach ($rows as &$r) {
                        $r['kar'] = round((float)$r['net_tutar'] - (float)$r['maliyet'], 2);
                        $r['kar_marji_yüzde'] = $r['ciro'] > 0 ? round($r['kar']/(float)$r['ciro']*100,1) : 0;
                        $r['ciro'] = round((float)$r['ciro'],2);
                        $r['net_tutar'] = round((float)$r['net_tutar'],2);
                        unset($r['maliyet']);
                    }
                    $veri = $rows;
                    $prompt = "Sen bir e-ticaret kategori analisti sin. Markdown kullanma (##, **, * gibi işaretler kullanma).  Aşağıdaki Trendyol kategori performans verilerini analiz et. Hangi kategoriler kârlılık taşıyor, hangilerinde risk var, hangilerine yatırım yapılmalı? Her önemli bulgu için ayrı madde yaz (🔴/🟡/🟢). 4-5 madde, düz metin.\n\nVeri:\n" . json_encode($veri, JSON_UNESCAPED_UNICODE);
                    break;

                case 'urun':
                    $rows = DB::rows("
                        SELECT tu.title AS ürün, tu.category_name AS kategori,
                               SUM(s.urun_adedi) AS satış_adedi, SUM(s.siparis_tutari) AS ciro,
                               SUM(s.net_tutar) AS net_tutar,
                               COALESCE(SUM(s.urun_adedi*(m.birim_maliyet+m.kargo_maliyeti+m.paket_maliyeti+m.diger_maliyet)),0) AS maliyet
                        FROM siparisler s
                        JOIN trendyol_urunler tu ON s.ty_urun_id=tu.ty_id AND tu.magaza_id=s.magaza_id
                        LEFT JOIN maliyetler m ON s.ty_urun_id=m.ty_urun_id AND m.magaza_id=s.magaza_id
                        WHERE $aiWhere GROUP BY tu.ty_id, tu.title, tu.category_name
                        ORDER BY ciro DESC LIMIT 20", $aiPrms);
                    foreach ($rows as &$r) {
                        $r['kâr'] = round((float)$r['net_tutar'] - (float)$r['maliyet'], 2);
                        $r['kâr_marjı_yüzde'] = $r['ciro'] > 0 ? round($r['kâr']/(float)$r['ciro']*100,1) : 0;
                        $r['ciro'] = round((float)$r['ciro'],2);
                        unset($r['net_tutar'],$r['maliyet']);
                    }
                    $veri = $rows;
                    $prompt = "Sen bir e-ticaret ürün portföy uzmanısın. Markdown kullanma (##, **, * gibi işaretler kullanma).  Aşağıdaki ürün performans verilerini analiz et. Gelir konsantrasyonu riski, yüksek/düşük marjlı ürünler, veri anomalileri ve fırsat alanlarını değerlendir. 4-5 madde, 🔴/🟡/🟢, düz metin.\n\nVeri:\n" . json_encode($veri, JSON_UNESCAPED_UNICODE);
                    break;

                case 'odeme':
                    $odRows = DB::rows("
                        SELECT islem_tipi, COUNT(*) AS adet,
                               SUM(satici_hakedis) AS toplam_hakedis,
                               SUM(toplam_tutar) AS toplam_tutar
                        FROM odeme_detay WHERE magaza_id=?
                        GROUP BY islem_tipi ORDER BY ABS(SUM(satici_hakedis)) DESC", [$magazaId]);
                    $netHakedis = (float)DB::scalar("SELECT COALESCE(SUM(satici_hakedis),0) FROM odeme_detay WHERE magaza_id=?", [$magazaId]);
                    $bekleyen = DB::rows("
                        SELECT siparis_no, satici_hakedis, vade_tarihi
                        FROM odeme_detay WHERE magaza_id=? AND islem_tipi='Satış'
                        AND vade_tarihi > NOW() ORDER BY vade_tarihi ASC LIMIT 10", [$magazaId]);
                    $veri = [
                        'kalem_dağılımı' => $odRows,
                        'net_hakediş_TL' => round($netHakedis,2),
                        'bekleyen_ödeme_sayısı' => count($bekleyen),
                        'bekleyen_ödeme_toplam_TL' => round(array_sum(array_column($bekleyen,'satici_hakedis')),2),
                    ];
                    $prompt = "Sen bir e-ticaret finans uzmanısın. Markdown kullanma (##, **, * gibi işaretler kullanma).  Aşağıdaki Trendyol ödeme ve hakediş verilerini analiz et. Net hakediş oranları, kargo/platform/reklam yük analizi, bekleyen ödemeler ve nakit akışı riskleri hakkında yorum yap. 4-5 madde, 🔴/🟡/🟢, düz metin.\n\nVeri:\n" . json_encode($veri, JSON_UNESCAPED_UNICODE);
                    break;

                case 'operasyonel':
                    $iadeOran = DB::row("
                        SELECT COUNT(*) AS toplam,
                               SUM(CASE WHEN siparis_statusu LIKE '%İade%' OR siparis_statusu LIKE '%Return%' THEN 1 ELSE 0 END) AS iade
                        FROM siparisler s WHERE $aiWhere", $aiPrms);
                    $maliyetsizUrun = (int)DB::scalar("
                        SELECT COUNT(DISTINCT tu.ty_id) FROM trendyol_urunler tu
                        JOIN siparisler s ON s.ty_urun_id=tu.ty_id AND s.magaza_id=tu.magaza_id
                        LEFT JOIN maliyetler m ON m.ty_urun_id=tu.ty_id AND m.magaza_id=tu.magaza_id
                        WHERE tu.magaza_id=? AND m.ty_urun_id IS NULL", [$magazaId]);
                    // Operasyonel reklam da dönem filtreli
                    $opReklamWhere = "magaza_id=?"; $opRekPrms = [$magazaId];
                    if ($donem==='bu_ay') {
                        $opReklamWhere .= " AND SUBSTRING(baslangic_tarihi,4,2)=? AND SUBSTRING(baslangic_tarihi,7,4)=?";
                        $opRekPrms[]   = sprintf('%02d',$buAy); $opRekPrms[] = (string)$buYil;
                    } elseif ($donem==='gecen_ay') {
                        $ga=$buAy===1?12:$buAy-1; $gy=$buAy===1?$buYil-1:$buYil;
                        $opReklamWhere .= " AND SUBSTRING(baslangic_tarihi,4,2)=? AND SUBSTRING(baslangic_tarihi,7,4)=?";
                        $opRekPrms[]   = sprintf('%02d',$ga); $opRekPrms[] = (string)$gy;
                    } elseif ($donem==='son_3_ay') {
                        $rk2=[];
                        for($i=0;$i<3;$i++){$a=$buAy-$i;$y=$buYil;if($a<=0){$a+=12;$y--;}
                            $rk2[]="(SUBSTRING(baslangic_tarihi,4,2)=? AND SUBSTRING(baslangic_tarihi,7,4)=?)";
                            $opRekPrms[]=sprintf('%02d',$a);$opRekPrms[]=(string)$y;}
                        $opReklamWhere.=" AND (".implode(' OR ',$rk2).")";
                    }
                    $topReklam = DB::rows("SELECT reklam_adi, harcama, tiklanma, goruntulenme FROM reklamlar WHERE $opReklamWhere ORDER BY harcama DESC LIMIT 5", $opRekPrms);
                    $veri = [
                        'toplam_sipariş' => (int)$iadeOran['toplam'],
                        'iade_sayısı' => (int)$iadeOran['iade'],
                        'iade_oranı_yüzde' => $iadeOran['toplam'] > 0 ? round($iadeOran['iade']/$iadeOran['toplam']*100,1) : 0,
                        'maliyeti_girilmemiş_ürün_sayısı' => $maliyetsizUrun,
                        'top_reklam_kampanyaları' => $topReklam,
                    ];
                    $prompt = "Sen bir e-ticaret operasyon uzmanısın. Markdown kullanma (##, **, * gibi işaretler kullanma).  Aşağıdaki operasyonel metrikleri analiz et. İade oranları, eksik maliyet verileri, reklam verimliliği ve operasyonel riskler hakkında somut öneriler ver. 4-5 madde, 🔴/🟡/🟢, düz metin.\n\nVeri:\n" . json_encode($veri, JSON_UNESCAPED_UNICODE);
                    break;

                default:
                    echo json_encode(['error' => 'Geçersiz analiz tipi']);
                    exit;
            }

            // Anthropic API çağrısı
            $payload = json_encode([
                'model' => 'claude-opus-4-5',
                'max_tokens' => 1024,
                'messages' => [['role' => 'user', 'content' => $prompt]]
            ]);

            $ch = curl_init('https://api.anthropic.com/v1/messages');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'x-api-key: ' . $anthropicKey,
                    'anthropic-version: 2023-06-01',
                ],
                CURLOPT_TIMEOUT => 60,
            ]);
            $res  = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (!$res) { echo json_encode(['error' => 'API bağlantı hatası']); break; }
            $data = json_decode($res, true);
            if ($code !== 200 || !isset($data['content'][0]['text'])) {
                echo json_encode(['error' => 'API hatası: ' . ($data['error']['message'] ?? $res)]);
                break;
            }
            $analizMetni = $data['content'][0]['text'];

            // DB'ye kaydet (aynı tip+dönem varsa güncelle)
            try {
                DB::exec(
                    "INSERT INTO ai_yorumlar (magaza_id, tip, donem, yorum, olusturuldu)
                     VALUES (?,?,?,?,NOW())
                     ON DUPLICATE KEY UPDATE yorum=VALUES(yorum), olusturuldu=NOW()",
                    [$magazaId, $tip, $donem, $analizMetni]
                );
            } catch(PDOException $e) {}

            echo json_encode(['analiz' => $analizMetni, 'tip' => $tip]);
            break;

        // ------ Toplu Sipariş Sil (sadece admin) ------
        case 'delete_siparis_bulk':
            if (!isAdmin()) { echo json_encode(['error' => 'Yetkisiz']); break; }
            $raw = trim($_POST['ids'] ?? '');
            if (!$raw) { echo json_encode(['error' => 'ID listesi boş']); break; }
            // Sadece rakam ve virgül kabul et
            $ids = array_filter(array_map('intval', explode(',', $raw)));
            if (empty($ids)) { echo json_encode(['error' => 'Geçerli ID yok']); break; }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            // Önce bu mağazaya ait olduklarını doğrula
            $valid = DB::rows(
                "SELECT id FROM siparisler WHERE id IN ($placeholders) AND magaza_id=?",
                array_merge($ids, [$magazaId])
            );
            $validIds = array_column($valid, 'id');
            if (empty($validIds)) { echo json_encode(['error' => 'Silinecek sipariş bulunamadı']); break; }
            $ph2 = implode(',', array_fill(0, count($validIds), '?'));
            DB::exec("DELETE FROM siparisler WHERE id IN ($ph2) AND magaza_id=?",
                array_merge($validIds, [$magazaId]));
            echo json_encode(['ok' => true, 'deleted' => count($validIds)]);
            break;

        // ------ Sipariş Sil (sadece admin) ------
        case 'delete_siparis':
            if (!isAdmin()) { echo json_encode(['error' => 'Yetkisiz']); break; }
            $sid = (int)($_POST['id'] ?? 0);
            if (!$sid) { echo json_encode(['error' => 'Geçersiz ID']); break; }
            // Sadece bu mağazanın siparişini silebilir
            $exists = DB::scalar("SELECT id FROM siparisler WHERE id=? AND magaza_id=?", [$sid, $magazaId]);
            if (!$exists) { echo json_encode(['error' => 'Sipariş bulunamadı']); break; }
            DB::exec("DELETE FROM siparisler WHERE id=? AND magaza_id=?", [$sid, $magazaId]);
            echo json_encode(['ok' => true]);
            break;

        // ------ Trendyol API: tüm ürünleri senkronize et ------
        case 'sync_products':
            $api    = new TrendyolApi($magaza);
            $result = $api->syncAllProducts();
            echo json_encode($result);
            break;

        // ------ Trendyol API: sipariş verilerini senkronize et ------
        case 'sync_orders':
            $startDate = $_POST['start_date'] ?? '';
            $endDate   = $_POST['end_date']   ?? '';
            $api    = new TrendyolApi($magaza);
            $result = $api->syncOrders($startDate, $endDate);
            echo json_encode($result);
            break;

        // ------ Eşleştirmeyi yeniden çalıştır ------
        case 'rematch':
            $api    = new TrendyolApi($magaza);
            $result = $api->rematch($magazaId);
            echo json_encode(['ok' => true, 'matched' => $result]);
            break;

        // ------ Siparişe manuel ürün ata ------
        case 'assign_order':
            $siparisNo = $_POST['siparis_no'] ?? '';
            $tyId      = $_POST['ty_urun_id'] ?? '';
            $barcode   = '';
            if ($tyId) {
                $row     = DB::row("SELECT barcode FROM trendyol_urunler WHERE ty_id=? AND magaza_id=?", [$tyId, $magazaId]);
                $barcode = $row['barcode'] ?? '';
            }
            DB::exec(
                "UPDATE siparisler SET ty_urun_id=?, ty_barcode=? WHERE siparis_no=? AND magaza_id=?",
                [$tyId ?: null, $barcode ?: null, $siparisNo, $magazaId]
            );
            echo json_encode(['ok' => true]);
            break;

        // ------ Maliyet kaydet ------
        case 'save_cost':
            $tyId   = trim($_POST['ty_urun_id']  ?? '');
            $barcode= trim($_POST['barcode']      ?? '');
            $ad     = trim($_POST['urun_adi']     ?? '');
            $birim  = (float)($_POST['birim_maliyet']  ?? 0);
            $kargo  = (float)($_POST['kargo_maliyeti'] ?? 0);
            $paket  = (float)($_POST['paket_maliyeti'] ?? 0);
            $diger  = (float)($_POST['diger_maliyet']  ?? 0);
            DB::exec(
                "INSERT INTO maliyetler (magaza_id,ty_urun_id,barcode,urun_adi,birim_maliyet,kargo_maliyeti,paket_maliyeti,diger_maliyet,guncelleme)
                 VALUES (?,?,?,?,?,?,?,?,NOW())
                 ON DUPLICATE KEY UPDATE
                   barcode=VALUES(barcode), urun_adi=VALUES(urun_adi),
                   birim_maliyet=VALUES(birim_maliyet), kargo_maliyeti=VALUES(kargo_maliyeti),
                   paket_maliyeti=VALUES(paket_maliyeti), diger_maliyet=VALUES(diger_maliyet),
                   guncelleme=NOW()",
                [$magazaId, $tyId, $barcode, $ad, $birim, $kargo, $paket, $diger]
            );
            echo json_encode(['ok' => true]);
            break;

        // ------ Maliyet sil ------
        case 'delete_cost':
            $id = (int)($_POST['id'] ?? 0);
            DB::exec("DELETE FROM maliyetler WHERE id=? AND magaza_id=?", [$id, $magazaId]);
            echo json_encode(['ok' => true]);
            break;

        // ------ Statüleri normalize et ------
        case 'normalize_status':
            $statusMap = [
                'Delivered'=>'Teslim Edildi','Cancelled'=>'İptal Edildi',
                'UnDelivered'=>'Teslim Edilemedi','Returned'=>'İade Edildi',
                'Created'=>'Yeni Sipariş','Picking'=>'Hazırlanıyor',
                'Invoiced'=>'Faturalandı','Shipped'=>'Kargoya Verildi',
                'Awaiting'=>'Beklemede','InTransit'=>'Yolda',
                'OutForDelivery'=>'Dağıtımda',
            ];
            $total = 0;
            foreach ($statusMap as $en => $tr) {
                $total += DB::exec(
                    "UPDATE siparisler SET siparis_statusu=?, api_statusu=COALESCE(api_statusu,?) WHERE magaza_id=? AND siparis_statusu=?",
                    [$tr, $en, $magazaId, $en]
                );
            }
            echo json_encode(['ok'=>true, 'updated'=>$total]);
            break;

        // ------ Talepleri senkronize et (Claims API) ------
        case 'sync_claims':
            $startDate = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate   = $_POST['end_date']   ?? date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $startDate = date('Y-m-d', strtotime('-30 days'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate))   $endDate   = date('Y-m-d');
            if ($startDate > $endDate) { echo json_encode(['error' => 'Başlangıç tarihi bitiş tarihinden büyük olamaz']); break; }
            $api    = new TrendyolApi($magaza);
            $result = $api->syncClaims($startDate, $endDate);
            echo json_encode($result);
            break;

        // ------ Müşteri sorularını çek (Questions API) ------
        case 'sync_questions':
            $api    = new TrendyolApi($magaza);
            $result = $api->syncQuestions();
            echo json_encode($result);
            break;

        // ------ Soruya yanıt gönder ------
        case 'answer_question':
            $questionId = trim($_POST['question_id'] ?? '');
            $cevap      = trim($_POST['cevap']       ?? '');
            if (!$questionId || !$cevap) {
                http_response_code(400);
                echo json_encode(['error' => 'question_id ve cevap zorunlu']);
                break;
            }
            $api    = new TrendyolApi($magaza);
            $result = $api->answerQuestion($questionId, $cevap);
            if (!isset($result['error'])) {
                // DB'yi güncelle
                DB::exec(
                    "UPDATE musteri_sorulari SET cevap_metni=?, cevap_tarihi=?, cevap_durumu='Cevaplandı'
                     WHERE magaza_id=? AND question_id=?",
                    [$cevap, date('Y-m-d H:i:s'), $magazaId, $questionId]
                );
            }
            echo json_encode($result);
            break;

        // ------ Talep / soru listeleri ------
        case 'list_claims':
            $status = $_GET['status'] ?? '';
            $tip    = $_GET['tip']    ?? '';
            $where  = 'magaza_id=?';
            $prms   = [$magazaId];
            if ($status) { $where .= ' AND talep_statusu=?'; $prms[] = $status; }
            if ($tip)    { $where .= ' AND talep_tipi=?';    $prms[] = $tip; }
            $rows = DB::rows(
                "SELECT claim_id,siparis_no,barcode,urun_adi,talep_tipi,talep_statusu,
                        talep_tarihi,iade_tutari,musteri,neden
                 FROM talepler WHERE $where ORDER BY talep_tarihi DESC LIMIT 200",
                $prms
            );
            echo json_encode(['claims' => $rows, 'total' => count($rows)]);
            break;

        case 'list_questions':
            $durum = $_GET['durum'] ?? '';
            $where = 'magaza_id=?';
            $prms  = [$magazaId];
            if ($durum) { $where .= ' AND cevap_durumu=?'; $prms[] = $durum; }
            $rows = DB::rows(
                "SELECT question_id,barcode,urun_adi,soru_metni,cevap_metni,
                        soru_tarihi,cevap_durumu
                 FROM musteri_sorulari WHERE $where ORDER BY soru_tarihi DESC LIMIT 200",
                $prms
            );
            $unanswered = DB::scalar(
                "SELECT COUNT(*) FROM musteri_sorulari WHERE magaza_id=? AND cevap_durumu='Cevaplanmadı'",
                [$magazaId]
            );
            echo json_encode(['questions' => $rows, 'total' => count($rows), 'unanswered' => (int)$unanswered]);
            break;

        // ------ Tabloyu temizle ------
        case 'clear_table':
            $tbl = $_POST['table'] ?? '';
            $allowed = ['siparisler', 'urun_satis', 'trendyol_urunler', 'maliyetler', 'reklamlar', 'komisyon_tarifeleri', 'odeme_detay', 'talepler', 'musteri_sorulari'];
            if (in_array($tbl, $allowed, true)) {
                DB::exec("DELETE FROM `$tbl` WHERE magaza_id=?", [$magazaId]);
                echo json_encode(['ok' => true]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Geçersiz tablo']);
            }
            break;

        // ------ API test: ham response döndür ------
        case 'api_test':
            $api = new TrendyolApi($magaza);
            if (!$api->isConfigured()) { echo json_encode(['error' => 'API yapılandırılmamış']); break; }
            $endTs   = time() * 1000;
            $startTs = strtotime('-7 days') * 1000;
            $result  = $api->testRequest(
                "/integration/order/sellers/{$magaza['ty_seller_id']}/orders",
                ['startDate' => $startTs, 'endDate' => $endTs, 'size' => 1, 'page' => 0]
            );
            echo json_encode(['raw' => $result]);
            break;



        default:
            http_response_code(400);
            echo json_encode(['error' => 'Bilinmeyen işlem: ' . $action]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}