<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

class TrendyolApi {

    private string $baseUrl;
    private string $sellerId;
    private string $apiKey;
    private string $apiSecret;
    private string $userAgent;
    private int    $magazaId;

    /**
     * $magaza: array with ty_seller_id, ty_api_key, ty_api_secret, id
     * Falls back to config.php constants if not provided.
     */
    public function __construct(array $magaza = []) {
        $this->baseUrl   = TY_BASE_URL;
        $this->sellerId  = $magaza['ty_seller_id']  ?? TY_SELLER_ID;
        $this->apiKey    = $magaza['ty_api_key']     ?? TY_API_KEY;
        $this->apiSecret = $magaza['ty_api_secret']  ?? TY_API_SECRET;
        $this->magazaId  = (int)($magaza['id']       ?? 0);
        $this->userAgent = $this->sellerId . ' - selfIntegration';
    }

    /** API statüsünü Türkçeye çevir */
    private function normalizeStatus(string $status): string {
        $map = [
            'Delivered'            => 'Teslim Edildi',
            'Cancelled'            => 'İptal Edildi',
            'UnDelivered'          => 'Teslim Edilemedi',
            'Returned'             => 'İade Edildi',
            'Created'              => 'Yeni Sipariş',
            'Picking'              => 'Hazırlanıyor',
            'Invoiced'             => 'Faturalandı',
            'Shipped'              => 'Kargoya Verildi',
            'PickedUp'             => 'Teslim Alındı',
            'Awaiting'             => 'Beklemede',
            'InTransit'            => 'Yolda',
            'OutForDelivery'       => 'Dağıtımda',
        ];
        return $map[$status] ?? $status;
    }

    public function isConfigured(): bool {
        return !empty($this->sellerId)
            && !empty($this->apiKey)
            && !empty($this->apiSecret);
    }

    /** Ham HTTP isteği */
    private function request(string $endpoint, array $params = []): array {
        if (!$this->isConfigured()) {
            return ['error' => 'API bilgileri config.php dosyasına girilmemiş.'];
        }

        $url = $this->baseUrl . $endpoint;
        if ($params) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'User-Agent: ' . $this->userAgent,
            ],
            CURLOPT_USERPWD        => $this->apiKey . ':' . $this->apiSecret,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err) return ['error' => 'cURL hatası: ' . $err];
        if ($httpCode === 401) return ['error' => 'Yetkilendirme hatası (401). API Key/Secret kontrol edin.'];
        if ($httpCode === 403) return ['error' => 'Erişim engellendi (403). User-Agent bilginizi kontrol edin.'];
        if ($httpCode >= 400) {
            $errMsg  = "HTTP $httpCode | URL: $url";
            $bodyData = json_decode($body, true);
            if (!empty($bodyData['errors'][0]['message']))  $errMsg .= ' | ' . $bodyData['errors'][0]['message'];
            elseif (!empty($bodyData['message']))           $errMsg .= ' | ' . $bodyData['message'];
            elseif (!empty($bodyData['error']))             $errMsg .= ' | ' . $bodyData['error'];
            if (!empty($body))                             $errMsg .= ' | Body: ' . substr($body, 0, 500);
            return ['error' => $errMsg, 'body' => $body];
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'JSON parse hatası: ' . json_last_error_msg()];
        }
        return $data ?? [];
    }

    /**
     * Onaylı ürünleri çek — V1 path (V2 /sapigw/suppliers bazı hesaplarda 556 döner).
     */
    public function getApprovedProducts(int $page = 0, int $size = 200): array {
        return $this->request(
            "/integration/product/sellers/{$this->sellerId}/products",
            ['page' => $page, 'size' => $size, 'approved' => 'true']
        );
    }

    /** Onaysız / beklemedeki ürünleri çek — V1 path. */
    public function getUnapprovedProducts(int $page = 0, int $size = 200): array {
        return $this->request(
            "/integration/product/sellers/{$this->sellerId}/products",
            ['page' => $page, 'size' => $size, 'approved' => 'false']
        );
    }

    /**
     * Barkod, stok kodu veya ürün kodu ile filtreli ürün sorgulama.
     *
     * @param array $filters  ['barcode'=>'...'] | ['stockCode'=>'...'] | ['productMainId'=>'...']
     */
    public function getProductsByFilter(array $filters, int $page = 0, int $size = 50): array {
        return $this->request(
            "/integration/product/sellers/{$this->sellerId}/products",
            array_merge($filters, ['page' => $page, 'size' => $size])
        );
    }

    /**
     * Geriye dönük uyumluluk — V1'i çağıran eski kod için köprü.
     * @deprecated V2 metodlarını kullanın: getApprovedProducts() / getUnapprovedProducts()
     */
    public function getProducts(int $page = 0, int $size = 200, bool $approved = true): array {
        return $approved
            ? $this->getApprovedProducts($page, $size)
            : $this->getUnapprovedProducts($page, $size);
    }

    /**
     * Tüm ürünleri (onaylı + onaysız) sayfalayarak çek ve DB'ye kaydet.
     * Döner: ['inserted'=>int, 'updated'=>int, 'total'=>int, 'pages'=>int, 'error'=>?string]
     */
    public function syncAllProducts(): array {
        $inserted = 0;
        $updated  = 0;
        $total    = 0;
        $pages    = 0;

        foreach ([true, false] as $approved) {
            $firstPage = $approved
                ? $this->getApprovedProducts(0, 200)
                : $this->getUnapprovedProducts(0, 200);

            if (isset($firstPage['error'])) {
                // onaysız endpoint 404 dönebilir — yok sayılabilir
                if (!$approved) continue;
                return ['error' => $firstPage['error']];
            }

            $totalPages = (int)($firstPage['totalPages'] ?? 1);
            $total     += (int)($firstPage['totalElements'] ?? 0);
            $pages     += $totalPages;

            $this->upsertPage($firstPage['content'] ?? [], $inserted, $updated);

            for ($p = 1; $p < $totalPages && $p < 500; $p++) {
                $page = $approved
                    ? $this->getApprovedProducts($p, 200)
                    : $this->getUnapprovedProducts($p, 200);
                if (isset($page['error'])) break;
                $this->upsertPage($page['content'] ?? [], $inserted, $updated);
                usleep(200_000); // rate-limit: 5 req/sn
            }
        }

        // Eşleştirme güncelle
        $this->rematch();

        return [
            'inserted' => $inserted,
            'updated'  => $updated,
            'total'    => $total,
            'pages'    => $pages,
        ];
    }

    /** Bir sayfanın ürünlerini DB'ye upsert et */
    private function upsertPage(array $items, int &$inserted, int &$updated): void {
        $now      = date('Y-m-d H:i:s');
        $magazaId = $this->magazaId;
        foreach ($items as $p) {
            $tyId    = $p['id'] ?? null;
            if (!$tyId) continue;

            $imageUrl = $p['images'][0]['url'] ?? null;
            $lastUpd  = isset($p['lastUpdateDate'])
                ? date('Y-m-d H:i:s', (int)($p['lastUpdateDate'] / 1000))
                : null;

            $exists = DB::scalar(
                "SELECT COUNT(*) FROM trendyol_urunler WHERE ty_id = ? AND magaza_id = ?",
                [$tyId, $magazaId]
            );

            if ($exists) {
                DB::exec("UPDATE trendyol_urunler SET
                    barcode=?, stock_code=?, product_main_id=?, product_code=?,
                    title=?, category_name=?, brand=?, color=?, size=?,
                    list_price=?, sale_price=?, quantity=?, approved=?,
                    image_url=?, product_url=?, son_guncelleme=?, cekme_tarihi=?
                    WHERE ty_id=?", [
                    $p['barcode']      ?? null,
                    $p['stockCode']    ?? null,
                    $p['productMainId']?? null,
                    $p['productCode']  ?? null,
                    $p['title']        ?? null,
                    $p['categoryName'] ?? null,
                    $p['brand']        ?? null,
                    $p['color']        ?? null,
                    $p['size']         ?? null,
                    $p['listPrice']    ?? 0,
                    $p['salePrice']    ?? 0,
                    $p['quantity']     ?? 0,
                    ($p['approved'] ?? false) ? 1 : 0,
                    $imageUrl,
                    $p['productUrl']   ?? null,
                    $lastUpd,
                    $now,
                    $tyId,
                ]);
                $updated++;
            } else {
                DB::exec("INSERT INTO trendyol_urunler
                    (magaza_id, ty_id, barcode, stock_code, product_main_id, product_code,
                     title, category_name, brand, color, size,
                     list_price, sale_price, quantity, approved,
                     image_url, product_url, son_guncelleme, cekme_tarihi)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", [
                    $magazaId,
                    $tyId,
                    $p['barcode']      ?? null,
                    $p['stockCode']    ?? null,
                    $p['productMainId']?? null,
                    $p['productCode']  ?? null,
                    $p['title']        ?? null,
                    $p['categoryName'] ?? null,
                    $p['brand']        ?? null,
                    $p['color']        ?? null,
                    $p['size']         ?? null,
                    $p['listPrice']    ?? 0,
                    $p['salePrice']    ?? 0,
                    $p['quantity']     ?? 0,
                    ($p['approved'] ?? false) ? 1 : 0,
                    $imageUrl,
                    $p['productUrl']   ?? null,
                    $lastUpd,
                    $now,
                ]);
                $inserted++;
            }
        }
    }

    /**
     * Eşleştirme:
     *   urun_satis  → trendyol_urunler: barcode (kesin) veya stock_code (kesin)
     *   siparisler  → trendyol_urunler: sadece urun_satis üzerinden köprü kurulur
     *                 (sipariş dosyasında barkod olmadığı için fiyat eşleştirmesi
     *                  güvenilmez — yalnızca TEK TAM fiyat eşleşmesi varsa önerilir,
     *                  birden fazla ürün aynı fiyatta ise eşleştirme yapılmaz)
     */
    public function rematch(int $magazaId = 0): array {
        if (!$magazaId) $magazaId = $this->magazaId;

        // ── 1. urun_satis → trendyol_urunler: barkod ile kesin eşleştirme ──
        $r1 = DB::exec("UPDATE urun_satis us
            JOIN trendyol_urunler tu ON us.barkod = tu.barcode AND tu.magaza_id = us.magaza_id
            SET us.ty_urun_id = tu.ty_id
            WHERE us.magaza_id = ? AND (us.ty_urun_id IS NULL OR us.ty_urun_id = '')", [$magazaId]);

        // ── 2. urun_satis → trendyol_urunler: stok kodu ile kesin eşleştirme ──
        $r2 = DB::exec("UPDATE urun_satis us
            JOIN trendyol_urunler tu ON us.model_kodu = tu.stock_code AND tu.magaza_id = us.magaza_id
            SET us.ty_urun_id = tu.ty_id
            WHERE us.magaza_id = ? AND (us.ty_urun_id IS NULL OR us.ty_urun_id = '')", [$magazaId]);

        // ── 3. siparisler → trendyol_urunler: siparis_satirlari üzerinden barkod eşleştirmesi ──
        // API'den çekilen satır kalemleri varsa bunları kullan (en güvenilir yöntem)
        $r3a = DB::exec("
            UPDATE siparisler s
            JOIN siparis_satirlari ss ON s.siparis_no = ss.siparis_no AND ss.magaza_id = s.magaza_id
            JOIN trendyol_urunler tu ON ss.barcode = tu.barcode AND tu.magaza_id = s.magaza_id
            SET s.ty_urun_id = tu.ty_id, s.ty_barcode = tu.barcode
            WHERE s.magaza_id = ?
              AND (s.ty_urun_id IS NULL OR s.ty_urun_id = '')
              AND ss.barcode IS NOT NULL AND ss.barcode != ''
        ", [$magazaId]);

        // ── 4. Hâlâ eşleşmeyenler → fiyat eşleştirmesi (MySQL 5.7 uyumlu, window function yok) ──
        // Önce her birim fiyata kaç ürün düşüyor bul
        $fiyatSayaci = [];
        $rows = DB::rows("SELECT ROUND(sale_price,2) as fiyat, COUNT(*) as adet
            FROM trendyol_urunler WHERE magaza_id=? AND sale_price>0
            GROUP BY fiyat", [$magazaId]);
        foreach ($rows as $row) {
            $fiyatSayaci[(string)$row['fiyat']] = (int)$row['adet'];
        }

        // Eşleşmemiş siparişleri çek
        $bekleyenler = DB::rows("
            SELECT siparis_no,
                   ROUND(siparis_tutari / GREATEST(urun_adedi,1), 2) AS birim_fiyat
            FROM siparisler
            WHERE magaza_id = ?
              AND (ty_urun_id IS NULL OR ty_urun_id = '')
              AND urun_adedi > 0 AND siparis_tutari > 0
        ", [$magazaId]);

        $r3b = 0;
        foreach ($bekleyenler as $sip) {
            $bf  = (string)$sip['birim_fiyat'];
            // Sadece bu fiyata TAM uyan TEK ürün varsa eşleştir
            if (($fiyatSayaci[$bf] ?? 0) !== 1) continue;

            $urun = DB::row("SELECT ty_id, barcode FROM trendyol_urunler
                WHERE magaza_id=? AND ROUND(sale_price,2)=? LIMIT 1",
                [$magazaId, $sip['birim_fiyat']]);
            if (!$urun) continue;

            DB::exec("UPDATE siparisler SET ty_urun_id=?, ty_barcode=?
                WHERE siparis_no=? AND magaza_id=?
                  AND (ty_urun_id IS NULL OR ty_urun_id='')",
                [$urun['ty_id'], $urun['barcode'], $sip['siparis_no'], $magazaId]);
            $r3b++;
        }

        return [
            'urun_satis'          => $r1 + $r2,
            'siparis_barkod'      => $r3a,
            'siparis_fiyat'       => $r3b,
            'siparisler'          => $r3a + $r3b,
        ];
    }

    /**
     * Bir sipariş için fiyata göre olası ürün eşleşmelerini döndür.
     * (Siparişler sayfasında "öneri" göstermek için kullanılır)
     */
    public function getSuggestions(float $birimFiyat): array {
        return DB::rows(
            "SELECT ty_id, barcode, title, sale_price, image_url
             FROM trendyol_urunler
             WHERE ROUND(sale_price,2) = ROUND(?,2)
             ORDER BY title LIMIT 10",
            [$birimFiyat]
        );
    }

    /**
     * Sipariş API — tarih aralığına göre paketleri çek ve DB'yi güncelle.
     * startDate / endDate: YYYY-MM-DD formatı, maksimum 14 günlük aralık
     * Döner: ['synced'=>int, 'lines_saved'=>int, 'matched'=>int, 'error'=>?string]
     */

    public function syncOrders(string $startDate = '', string $endDate = ''): array {
        if (!$this->isConfigured()) {
            return ['error' => 'API bilgileri config.php dosyasına girilmemiş.'];
        }

        // Varsayılan: son 30 gün
        if (!$startDate) $startDate = date('Y-m-d', strtotime('-30 days'));
        if (!$endDate)   $endDate   = date('Y-m-d');

        $magazaId = $this->magazaId;
        $this->migrateOrderColumns();

        // Aralığı 13 günlük dilimlere böl (14 günden küçük olsun)
        $totalSynced = 0; $totalLines = 0; $totalMatched = 0;
        $cursor = strtotime($startDate);
        $end    = strtotime($endDate);

        while ($cursor <= $end) {
            $chunkEnd = min($cursor + (13 * 86400), $end); // 13 gün
            $result = $this->syncChunk(
                date('Y-m-d', $cursor),
                date('Y-m-d', $chunkEnd),
                $magazaId
            );
            if (isset($result['error'])) return $result;
            $totalSynced  += $result['synced'];
            $totalInserted = ($totalInserted ?? 0) + ($result['inserted'] ?? 0);
            $totalLines   += $result['lines_saved'];
            $cursor = $chunkEnd + 86400; // Sonraki dilime geç
            usleep(300_000); // Dilimler arası 300ms bekle
        }

        $totalMatched = $this->matchMultiLineOrders();
        return ['synced' => $totalSynced, 'inserted' => $totalInserted ?? 0, 'lines_saved' => $totalLines, 'matched' => $totalMatched];
    }

    /** Tek bir 14 günlük dilimi çeker */
    private function syncChunk(string $startDate, string $endDate, int $magazaId): array {
        $startTs = strtotime($startDate . ' 00:00:00') * 1000;
        $endTs   = strtotime($endDate   . ' 23:59:59') * 1000;
        $synced = 0; $inserted = 0; $linesSaved = 0; $page = 0;
        $now = date('Y-m-d H:i:s');

        do {
            $data = $this->request(
                "/integration/order/sellers/{$this->sellerId}/orders",
                [
                    'startDate'        => $startTs,
                    'endDate'          => $endTs,
                    'orderByField'     => 'PackageLastModifiedDate',
                    'orderByDirection' => 'DESC',
                    'size'             => 200,
                    'page'             => $page,
                ]
            );

            if (isset($data['error'])) return $data;

            $totalPages = (int)($data['totalPages'] ?? 1);
            $packages   = $data['content'] ?? [];

            foreach ($packages as $pkg) {
                $orderNo = (string)($pkg['orderNumber'] ?? '');
                $pkgId   = (string)($pkg['shipmentPackageId'] ?? '');
                if (!$orderNo) continue;

                $musteri   = trim(($pkg['customerFirstName'] ?? '') . ' ' . ($pkg['customerLastName'] ?? ''));
                $rawStatus = $pkg['shipmentPackageStatus'] ?? $pkg['status'] ?? null;
                $status    = $rawStatus ? $this->normalizeStatus($rawStatus) : null;
                $tarih     = isset($pkg['orderDate'])
                             ? date('d.m.Y H:i', (int)($pkg['orderDate']/1000)) : null;
                $tutar     = (float)($pkg['grossAmount'] ?? $pkg['packageGrossAmount'] ?? 0);
                $trackNo   = $pkg['cargoTrackingNumber'] ?? null;
                $kargoFirm = $pkg['cargoProviderName'] ?? null;

                // lines[] toplamından komisyon hesapla
                $lines = $pkg['lines'] ?? [];
                $toplamKomisyon = 0;
                foreach ($lines as $ln) {
                    $toplamKomisyon += (float)($ln['commission'] ?? 0) * (int)($ln['quantity'] ?? 1);
                }

                // Kaç ürün var
                $urunAdedi = array_sum(array_column($lines, 'quantity'));

                // Mevcut sipariş var mı?
                $mevcutId = DB::scalar(
                    "SELECT id FROM siparisler WHERE magaza_id=? AND siparis_no=? LIMIT 1",
                    [$magazaId, $orderNo]
                );

                if ($mevcutId) {
                    // Güncelle
                    DB::exec(
                        "UPDATE siparisler SET
                            musteri_tam_ad      = ?,
                            kargo_takip_no      = ?,
                            kargo_firmasi       = ?,
                            api_statusu         = ?,
                            siparis_statusu     = COALESCE(siparis_statusu, ?),
                            shipment_package_id = ?,
                            komisyon            = CASE WHEN komisyon = 0 OR komisyon IS NULL THEN ? ELSE komisyon END,
                            api_son_guncelleme  = ?
                        WHERE id = ?",
                        [
                            $musteri ?: null,
                            $trackNo,
                            $kargoFirm,
                            $status,
                            $status,
                            $pkgId,
                            -abs($toplamKomisyon),
                            $now,
                            $mevcutId,
                        ]
                    );
                    $synced++;
                } else {
                    // Yeni sipariş ekle (API'den)
                    try {
                        DB::exec(
                            "INSERT INTO siparisler
                                (magaza_id, siparis_tarihi, siparis_no, siparis_statusu,
                                 musteri, musteri_tam_ad, urun_adedi, siparis_tutari,
                                 komisyon, kargo_takip_no, kargo_firmasi,
                                 api_statusu, shipment_package_id, api_son_guncelleme, yukleme_tarihi)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                            [
                                $magazaId,
                                $tarih,
                                $orderNo,
                                $status,
                                $musteri,
                                $musteri,
                                $urunAdedi,
                                $tutar,
                                -abs($toplamKomisyon),
                                $trackNo,
                                $kargoFirm,
                                $status,
                                $pkgId,
                                $now,
                                $now,
                            ]
                        );
                        $inserted++;
                    } catch (PDOException $e) { /* duplicate, skip */ }
                }

                // Satır kalemlerini kaydet + eşleştir
                foreach ($lines as $line) {
                    $barcode    = (string)($line['barcode']     ?? $line['sku'] ?? '');
                    $stockCode  = (string)($line['stockCode']   ?? $line['merchantSku'] ?? '');
                    $prodName   = (string)($line['productName'] ?? '');
                    $qty        = (int)($line['quantity']       ?? 1);
                    $birimFiyat = (float)($line['lineGrossAmount'] ?? $line['price'] ?? 0);
                    $kalemTutar = $birimFiyat * $qty;

                    // ty_urun_id bul
                    $tyId = null;
                    if ($barcode) {
                        $tyId = DB::scalar(
                            "SELECT ty_id FROM trendyol_urunler WHERE magaza_id=? AND (barcode=? OR stock_code=?) LIMIT 1",
                            [$magazaId, $barcode, $barcode]
                        );
                    }
                    if (!$tyId && $stockCode) {
                        $tyId = DB::scalar(
                            "SELECT ty_id FROM trendyol_urunler WHERE magaza_id=? AND stock_code=? LIMIT 1",
                            [$magazaId, $stockCode]
                        );
                    }

                    $exists = DB::scalar(
                        "SELECT id FROM siparis_satirlari WHERE magaza_id=? AND siparis_no=? AND barcode=? LIMIT 1",
                        [$magazaId, $orderNo, $barcode]
                    );
                    if ($exists) {
                        DB::exec(
                            "UPDATE siparis_satirlari SET shipment_package_id=?,stock_code=?,
                             product_name=?,adet=?,birim_fiyat=?,kalem_tutari=?,ty_urun_id=?
                             WHERE id=?",
                            [$pkgId,$stockCode,$prodName,$qty,$birimFiyat,$kalemTutar,$tyId,$exists]
                        );
                    } else {
                        DB::exec(
                            "INSERT INTO siparis_satirlari
                             (magaza_id,siparis_no,shipment_package_id,barcode,stock_code,
                              product_name,adet,birim_fiyat,kalem_tutari,ty_urun_id)
                             VALUES (?,?,?,?,?,?,?,?,?,?)",
                            [$magazaId,$orderNo,$pkgId,$barcode,$stockCode,
                             $prodName,$qty,$birimFiyat,$kalemTutar,$tyId]
                        );
                        $linesSaved++;
                    }

                    // Siparişe ürün eşleştir
                    if ($tyId) {
                        DB::exec(
                            "UPDATE siparisler SET ty_urun_id=?,ty_barcode=?
                             WHERE siparis_no=? AND magaza_id=?
                               AND (ty_urun_id IS NULL OR ty_urun_id='')",
                            [$tyId,$barcode,$orderNo,$magazaId]
                        );
                    }
                }
            }

            $page++;
            usleep(100_000);
        } while ($page < $totalPages && $page < 100);

        return ['synced' => $synced, 'inserted' => $inserted, 'lines_saved' => $linesSaved];
    }

    /** Birden fazla kalem içeren siparişlerde en pahalı/tek ürünü eşleştir */
    private function matchMultiLineOrders(): int {
        // Sipariş satırlarında ty_urun_id olan ama siparişler tablosunda eşleşmemiş olanları güncelle
        return DB::exec(
            "UPDATE siparisler s
             JOIN (
                SELECT siparis_no, ty_urun_id, barcode
                FROM siparis_satirlari
                WHERE ty_urun_id IS NOT NULL
                ORDER BY kalem_tutari DESC
             ) best ON s.siparis_no = best.siparis_no
             SET s.ty_urun_id = best.ty_urun_id, s.ty_barcode = best.barcode
             WHERE s.ty_urun_id IS NULL OR s.ty_urun_id = ''"
        );
    }

    /** Yeni sütunları ekle (migration) */
    /**
     * Cari Hesap Ekstresi API'sinden ödeme verilerini çekip odeme_detay tablosuna yazar.
     * API max 15 günlük aralık kabul ettiğinden otomatik böler.
     *
     * @param string $startDate  'Y-m-d' formatı
     * @param string $endDate    'Y-m-d' formatı
     * @return array ['inserted'=>int, 'updated'=>int, 'errors'=>[], 'donem_tagi'=>string]
     */
    public function syncOdemeDetay(string $startDate, string $endDate): array {
        if (!$this->isConfigured()) {
            return ['error' => 'API bilgileri eksik. Ayarlar sayfasından Seller ID, API Key ve Secret girin.'];
        }

        $inserted   = 0;
        $updated    = 0;
        $errors     = [];
        $donemTagi  = $endDate; // dönem tagi olarak bitiş tarihi
        $now        = date('Y-m-d H:i:s');

        // Settlements: Sale + Return (sipariş bazlı kalemler)
        $settlementTypes = ['Sale', 'Return', 'Discount', 'Coupon'];

        // OtherFinancials: kargo, platform, reklam, ceza vb.
        $otherTypes = ['DeductionInvoices', 'PaymentOrder'];

        // 15 günlük dilimlere böl
        $chunks = $this->splitDateRange($startDate, $endDate, 15);

        foreach ($chunks as $chunk) {
            $startTs = strtotime($chunk[0]) * 1000;
            $endTs   = (strtotime($chunk[1]) + 86399) * 1000; // günün sonu

            // --- 1. Settlements (Sale, Return, Discount, Coupon) ---
            foreach ($settlementTypes as $tType) {
                $page = 0;
                do {
                    $data = $this->request(
                        "/integration/finance/che/sellers/{$this->sellerId}/settlements",
                        ['startDate' => $startTs, 'endDate' => $endTs,
                         'transactionType' => $tType, 'page' => $page, 'size' => 500]
                    );
                    if (isset($data['error'])) {
                        $errors[] = "settlements/$tType p$page: " . $data['error'];
                        break;
                    }
                    $items      = $data['content'] ?? [];
                    $totalPages = $data['totalPages'] ?? 1;

                    foreach ($items as $item) {
                        $r = $this->upsertOdemeDetay($item, 'settlements', $donemTagi, $now);
                        $inserted += $r[0]; $updated += $r[1];
                    }
                    $page++;
                } while ($page < $totalPages);
            }

            // --- 2. OtherFinancials (kargo, platform, reklam vb.) ---
            foreach ($otherTypes as $tType) {
                $page = 0;
                do {
                    $data = $this->request(
                        "/integration/finance/che/sellers/{$this->sellerId}/otherfinancials",
                        ['startDate' => $startTs, 'endDate' => $endTs,
                         'transactionType' => $tType, 'page' => $page, 'size' => 500]
                    );
                    if (isset($data['error'])) {
                        $errors[] = "otherfinancials/$tType p$page: " . $data['error'];
                        break;
                    }
                    $items      = $data['content'] ?? [];
                    $totalPages = $data['totalPages'] ?? 1;

                    foreach ($items as $item) {
                        $r = $this->upsertOdemeDetay($item, 'otherfinancials', $donemTagi, $now);
                        $inserted += $r[0]; $updated += $r[1];
                    }
                    $page++;
                } while ($page < $totalPages);
            }
        }

        return [
            'inserted'   => $inserted,
            'updated'    => $updated,
            'errors'     => $errors,
            'donem_tagi' => $donemTagi,
        ];
    }

    /** Tarih aralığını max N günlük parçalara böler */
    private function splitDateRange(string $start, string $end, int $maxDays): array {
        $chunks  = [];
        $current = strtotime($start);
        $endTs   = strtotime($end);
        while ($current <= $endTs) {
            $chunkEnd  = min($current + ($maxDays - 1) * 86400, $endTs);
            $chunks[]  = [date('Y-m-d', $current), date('Y-m-d', $chunkEnd)];
            $current   = $chunkEnd + 86400;
        }
        return $chunks;
    }

    /** Tek bir API kaydını odeme_detay tablosuna upsert eder */
    private function upsertOdemeDetay(array $item, string $source, string $donemTagi, string $now): array {
        $ins = 0; $upd = 0;

        // Alan eşleştirmesi — settlements ve otherfinancials alan adları farklı olabilir
        $kayitNo     = (string)($item['id'] ?? '');
        $islemTipi   = (string)($item['transactionType'] ?? '');
        $siparisNo   = (string)($item['orderNumber'] ?? '');
        $barkod      = (string)($item['barcode'] ?? '');
        $urunAdi     = (string)($item['description'] ?? ($item['invoiceDescription'] ?? ''));
        $musteri     = (string)($item['customerName'] ?? '');
        $paketNo     = (string)($item['shipmentPackageId'] ?? ($item['packageId'] ?? ''));

        // Tarihler (milisaniye timestamp)
        $islemTarihiTs  = isset($item['transactionDate'])  ? (int)($item['transactionDate'] / 1000)  : null;
        $vadeTarihiTs   = isset($item['paymentDate'])       ? (int)($item['paymentDate'] / 1000)      : null;
        $teslimTarihiTs = isset($item['deliveryDate'])      ? (int)($item['deliveryDate'] / 1000)     : null;

        $islemTarihi  = $islemTarihiTs  ? date('Y-m-d H:i:s', $islemTarihiTs)  : null;
        $vadeTarihi   = $vadeTarihiTs   ? date('Y-m-d H:i:s', $vadeTarihiTs)   : null;
        $teslimTarihi = $teslimTarihiTs ? date('Y-m-d H:i:s', $teslimTarihiTs) : null;

        // Finansal
        $saticiHakedis  = (float)($item['sellerRevenue']     ?? ($item['credit'] ?? 0));
        $komisyon       = (float)($item['commissionAmount']  ?? 0);
        $komisyonOrani  = (float)($item['commissionRate']    ?? 0);
        $stopaj         = (float)($item['withholdingTax']    ?? 0);
        $toplamTutar    = (float)($item['credit']            ?? ($item['amount'] ?? 0));
        $kdvOrani       = (float)($item['vatRate']           ?? 0);
        $vadeSuresi     = (int)($item['paymentPeriod']       ?? 0);

        // Varsa kayıt no unique key ile bak
        if (!$kayitNo) return [0, 0];

        try {
            $existing = DB::scalar(
                "SELECT id FROM odeme_detay WHERE magaza_id=? AND kayit_no=?",
                [$this->magazaId, $kayitNo]
            );
            if ($existing) {
                DB::exec(
                    "UPDATE odeme_detay SET
                        islem_tipi=?, siparis_no=?, islem_tarihi=?, vade_tarihi=?,
                        satici_hakedis=?, komisyon_orani=?, stopaj=?, toplam_tutar=?,
                        vade_suresi=?, urun_adi=?, barkod=?, musteri=?, paket_no=?,
                        donem_tagi=?, yukleme_tarihi=?
                     WHERE magaza_id=? AND kayit_no=?",
                    [$islemTipi, $siparisNo, $islemTarihi, $vadeTarihi,
                     $saticiHakedis, $komisyonOrani, $stopaj, $toplamTutar,
                     $vadeSuresi, $urunAdi, $barkod, $musteri, $paketNo,
                     $donemTagi, $now,
                     $this->magazaId, $kayitNo]
                );
                $upd = 1;
            } else {
                DB::exec(
                    "INSERT INTO odeme_detay
                     (magaza_id, kayit_no, ulke, islem_tipi, siparis_no,
                      siparis_tarihi, islem_tarihi, urun_adi, barkod,
                      komisyon_orani, ty_hakedis, satici_hakedis, stopaj,
                      kdv_orani, vade_suresi, teslim_tarihi, vade_tarihi,
                      toplam_tutar, musteri, paket_no, donem_tagi, yukleme_tarihi)
                     VALUES (?,?,?,?,?, ?,?,?,?, ?,?,?,?, ?,?,?,?, ?,?,?,?,?)",
                    [$this->magazaId, $kayitNo, 'Türkiye', $islemTipi, $siparisNo,
                     null, $islemTarihi, $urunAdi, $barkod,
                     $komisyonOrani, $toplamTutar, $saticiHakedis, $stopaj,
                     $kdvOrani, $vadeSuresi, $teslimTarihi, $vadeTarihi,
                     $toplamTutar, $musteri, $paketNo, $donemTagi, $now]
                );
                $ins = 1;
            }
        } catch (PDOException $e) {
            // kayit_no çakışması gibi durumları sessizce geç
        }

        return [$ins, $upd];
    }

    /**
     * Kategori bazlı komisyon oranlarını Trendyol API'den çekip
     * komisyon_api_kategoriler tablosuna kaydeder.
     *
     * Endpoint: GET /integration/sellers/{sellerId}/commission-rates
     * Yanıt: [{categoryId, categoryName, commissionRate, priceRanges:[{minPrice,maxPrice,rate}]}]
     *
     * @return array ['inserted'=>int,'updated'=>int,'total'=>int,'error'=>?string]
     */
    public function syncCommissionRates(): array {
        if (!$this->isConfigured()) {
            return ['error' => 'API bilgileri eksik.'];
        }

        $inserted = 0;
        $updated  = 0;
        $now      = date('Y-m-d H:i:s');
        $page     = 0;

        do {
            $data = $this->request(
                "/integration/sellers/{$this->sellerId}/commission-rates",
                ['page' => $page, 'size' => 200]
            );

            if (isset($data['error'])) {
                return ['error' => $data['error'], 'inserted' => $inserted, 'updated' => $updated];
            }

            $items      = $data['content'] ?? $data['commissionRates'] ?? $data;
            $totalPages = (int)($data['totalPages'] ?? 1);

            if (!is_array($items)) break;

            foreach ($items as $item) {
                $catId   = (string)($item['categoryId']   ?? $item['id']           ?? '');
                $catAdi  = (string)($item['categoryName'] ?? $item['name']         ?? '');
                $oran    = (float)($item['commissionRate'] ?? $item['rate']        ?? 0);
                $araliklar = json_encode(
                    $item['priceRanges'] ?? $item['commissionRates'] ?? [],
                    JSON_UNESCAPED_UNICODE
                );

                if (!$catId && !$catAdi) continue;
                if (!$catId) $catId = md5($catAdi);

                $exists = DB::scalar(
                    "SELECT id FROM komisyon_api_kategoriler WHERE magaza_id=? AND kategori_id=?",
                    [$this->magazaId, $catId]
                );

                if ($exists) {
                    DB::exec(
                        "UPDATE komisyon_api_kategoriler
                         SET kategori_adi=?, komisyon_orani=?, fiyat_araliklari=?, guncelleme=?
                         WHERE magaza_id=? AND kategori_id=?",
                        [$catAdi, $oran, $araliklar, $now, $this->magazaId, $catId]
                    );
                    $updated++;
                } else {
                    try {
                        DB::exec(
                            "INSERT INTO komisyon_api_kategoriler
                             (magaza_id,kategori_id,kategori_adi,komisyon_orani,fiyat_araliklari,guncelleme)
                             VALUES (?,?,?,?,?,?)",
                            [$this->magazaId, $catId, $catAdi, $oran, $araliklar, $now]
                        );
                        $inserted++;
                    } catch (PDOException $e) {}
                }
            }

            $page++;
        } while ($page < $totalPages && $page < 50);

        $total = $inserted + $updated;

        // Trendyol ürünlerini kategori oranıyla güncelle (kesin eşleşme: category_name → kategori_adi)
        $matched = $this->applyApiRatesToProducts();

        return [
            'inserted'      => $inserted,
            'updated'       => $updated,
            'total'         => $total,
            'urun_guncelle' => $matched,
        ];
    }

    /**
     * API'den çekilen kategori oranlarını trendyol_urunler'e uygular.
     * trendyol_urunler.category_name = komisyon_api_kategoriler.kategori_adi
     */
    private function applyApiRatesToProducts(): int {
        return DB::exec(
            "UPDATE trendyol_urunler tu
             JOIN komisyon_api_kategoriler kak
               ON tu.magaza_id = kak.magaza_id
              AND tu.category_name = kak.kategori_adi
             SET tu.api_komisyon_orani = kak.komisyon_orani
             WHERE tu.magaza_id = ?",
            [$this->magazaId]
        );
    }

    /**
     * Gerçekleşmiş uzlaşım verilerinden (odeme_detay) barkod bazlı komisyon oranı türetir.
     * En az 2 işlem olan barkodlar için ortalama hesaplanır ve komisyon_tarifeleri tablosu güncellenir.
     *
     * @return array ['barkod_sayisi'=>int,'guncellenen'=>int]
     */
    public function applySettlementCommissions(): array {
        // Barkod bazlı ortalama komisyon oranını hesapla (≥2 işlem)
        $rates = DB::rows(
            "SELECT barkod,
                    ROUND(AVG(komisyon_orani), 4) AS ort_oran,
                    COUNT(*)                       AS islem_sayisi
             FROM odeme_detay
             WHERE magaza_id = ?
               AND barkod != ''
               AND komisyon_orani > 0
             GROUP BY barkod
             HAVING COUNT(*) >= 2",
            [$this->magazaId]
        );

        if (empty($rates)) {
            return ['barkod_sayisi' => 0, 'guncellenen' => 0];
        }

        $guncellenen = 0;
        foreach ($rates as $r) {
            $affected = DB::exec(
                "UPDATE komisyon_tarifeleri
                 SET guncel_komisyon = ?
                 WHERE magaza_id = ? AND (barcode = ? OR stok_kodu = ?)",
                [$r['ort_oran'], $this->magazaId, $r['barkod'], $r['barkod']]
            );
            if ($affected) $guncellenen++;
        }

        return [
            'barkod_sayisi' => count($rates),
            'guncellenen'   => $guncellenen,
        ];
    }

    /**
     * Müşteri taleplerini (iade, hasar, kayıp) API'den çekip talepler tablosuna yazar.
     *
     * @param string $startDate  'Y-m-d' formatı
     * @param string $endDate    'Y-m-d' formatı
     * @return array ['inserted'=>int, 'updated'=>int, 'errors'=>[]]
     */
    public function syncClaims(string $startDate, string $endDate): array {
        if (!$this->isConfigured()) {
            return ['error' => 'API bilgileri eksik.'];
        }

        $inserted = 0;
        $updated  = 0;
        $errors   = [];
        $now      = date('Y-m-d H:i:s');

        $chunks = $this->splitDateRange($startDate, $endDate, 15);

        foreach ($chunks as [$chunkStart, $chunkEnd]) {
            $startTs = strtotime($chunkStart) * 1000;
            $endTs   = (strtotime($chunkEnd) + 86399) * 1000;

            $page = 0;
            do {
                $data = $this->request(
                    "/integration/claim/sellers/{$this->sellerId}/claims",
                    ['startDate' => $startTs, 'endDate' => $endTs,
                     'page' => $page, 'size' => 200]
                );

                if (isset($data['error'])) {
                    $errors[] = "claims chunk $chunkStart p$page: " . $data['error'];
                    break;
                }

                $items      = $data['content'] ?? $data['claims'] ?? [];
                $totalPages = (int)($data['totalPages'] ?? 1);

                foreach ($items as $item) {
                    $r = $this->upsertClaim($item, $now);
                    $inserted += $r[0];
                    $updated  += $r[1];
                }

                $page++;
                usleep(200_000);
            } while ($page < $totalPages && $page < 200);
        }

        return ['inserted' => $inserted, 'updated' => $updated, 'errors' => $errors];
    }

    /** Tek claim kaydını talepler tablosuna upsert et */
    private function upsertClaim(array $item, string $now): array {
        $claimId = (string)($item['id']            ?? $item['claimId'] ?? '');
        if (!$claimId) return [0, 0];

        $siparisNo    = (string)($item['orderNumber']       ?? $item['orderId']       ?? '');
        $lineItemId   = (string)($item['lineItemId']        ?? '');
        $barcode      = (string)($item['barcode']           ?? '');
        $urunAdi      = (string)($item['productName']       ?? $item['title']         ?? '');
        $talep        = (string)($item['claimType']         ?? $item['reason']        ?? '');
        $status       = (string)($item['claimStatus']       ?? $item['status']        ?? '');
        $musteri      = (string)($item['customerName']      ?? '');
        $kargoTakip   = (string)($item['cargoTrackingNumber'] ?? '');
        $neden        = (string)($item['reasonDescription'] ?? $item['description']   ?? '');
        $iadeTutar    = (float)($item['refundAmount']       ?? $item['amount']        ?? 0);

        $tarihTs   = isset($item['claimDate']) ? (int)($item['claimDate'] / 1000) : null;
        $talep_t   = $tarihTs ? date('Y-m-d H:i:s', $tarihTs) : null;

        // Barkodla ürün eşleştir
        $tyId = null;
        if ($barcode) {
            $tyId = DB::scalar(
                "SELECT ty_id FROM trendyol_urunler WHERE magaza_id=? AND barcode=? LIMIT 1",
                [$this->magazaId, $barcode]
            );
        }

        $rawJson = json_encode($item, JSON_UNESCAPED_UNICODE);

        $existing = DB::scalar(
            "SELECT id FROM talepler WHERE magaza_id=? AND claim_id=?",
            [$this->magazaId, $claimId]
        );

        if ($existing) {
            DB::exec(
                "UPDATE talepler SET siparis_no=?,barcode=?,urun_adi=?,talep_tipi=?,
                 talep_statusu=?,talep_tarihi=?,iade_tutari=?,musteri=?,
                 kargo_takip_no=?,neden=?,ty_urun_id=?,raw_json=?,yukleme_tarihi=?
                 WHERE magaza_id=? AND claim_id=?",
                [$siparisNo,$barcode,$urunAdi,$talep,$status,$talep_t,
                 $iadeTutar,$musteri,$kargoTakip,$neden,$tyId,$rawJson,$now,
                 $this->magazaId,$claimId]
            );
            return [0, 1];
        }

        try {
            DB::exec(
                "INSERT INTO talepler
                 (magaza_id,claim_id,siparis_no,line_item_id,barcode,urun_adi,
                  talep_tipi,talep_statusu,talep_tarihi,iade_tutari,musteri,
                  kargo_takip_no,neden,ty_urun_id,raw_json,yukleme_tarihi)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$this->magazaId,$claimId,$siparisNo,$lineItemId,$barcode,$urunAdi,
                 $talep,$status,$talep_t,$iadeTutar,$musteri,
                 $kargoTakip,$neden,$tyId,$rawJson,$now]
            );
            return [1, 0];
        } catch (PDOException $e) {
            return [0, 0];
        }
    }

    /**
     * Müşteri ürün sorularını API'den çekip musteri_sorulari tablosuna yazar.
     * Cevaplanmamış sorular öncelikli gelir (status=WaitingForAnswer).
     *
     * @return array ['inserted'=>int, 'updated'=>int, 'unanswered'=>int, 'errors'=>[]]
     */
    public function syncQuestions(): array {
        if (!$this->isConfigured()) {
            return ['error' => 'API bilgileri eksik.'];
        }

        $inserted   = 0;
        $updated    = 0;
        $unanswered = 0;
        $errors     = [];
        $now        = date('Y-m-d H:i:s');

        $page = 0;
        do {
            $data = $this->request(
                "/integration/claim/sellers/{$this->sellerId}/questions",
                ['page' => $page, 'size' => 200]
            );

            if (isset($data['error'])) {
                $errors[] = "questions p$page: " . $data['error'];
                break;
            }

            $items      = $data['content'] ?? $data['productQuestions'] ?? [];
            $totalPages = (int)($data['totalPages'] ?? 1);

            foreach ($items as $item) {
                $r = $this->upsertQuestion($item, $now);
                $inserted   += $r[0];
                $updated    += $r[1];
                $unanswered += $r[2];
            }

            $page++;
            usleep(200_000);
        } while ($page < $totalPages && $page < 100);

        return [
            'inserted'   => $inserted,
            'updated'    => $updated,
            'unanswered' => $unanswered,
            'errors'     => $errors,
        ];
    }

    /** Tek soru kaydını musteri_sorulari tablosuna upsert et */
    private function upsertQuestion(array $item, string $now): array {
        $questionId = (string)($item['id']             ?? $item['questionId'] ?? '');
        if (!$questionId) return [0, 0, 0];

        $barcode    = (string)($item['barcode']         ?? '');
        $urunAdi    = (string)($item['productName']     ?? $item['title']   ?? '');
        $soruMetni  = (string)($item['text']            ?? $item['question'] ?? '');
        $cevapMetni = (string)($item['answer']          ?? $item['answerText'] ?? '');
        $durum      = empty($cevapMetni) ? 'Cevaplanmadı' : 'Cevaplandı';

        $soruTs   = isset($item['creationDate'])  ? (int)($item['creationDate'] / 1000)  : null;
        $cevapTs  = isset($item['lastModifiedDate']) ? (int)($item['lastModifiedDate'] / 1000) : null;
        $soruT    = $soruTs  ? date('Y-m-d H:i:s', $soruTs)  : null;
        $cevapT   = $cevapTs ? date('Y-m-d H:i:s', $cevapTs) : null;

        $tyId = null;
        if ($barcode) {
            $tyId = DB::scalar(
                "SELECT ty_id FROM trendyol_urunler WHERE magaza_id=? AND barcode=? LIMIT 1",
                [$this->magazaId, $barcode]
            );
        }

        $existing = DB::scalar(
            "SELECT id FROM musteri_sorulari WHERE magaza_id=? AND question_id=?",
            [$this->magazaId, $questionId]
        );

        $isUnanswered = ($durum === 'Cevaplanmadı') ? 1 : 0;

        if ($existing) {
            DB::exec(
                "UPDATE musteri_sorulari SET barcode=?,urun_adi=?,soru_metni=?,
                 cevap_metni=?,soru_tarihi=?,cevap_tarihi=?,cevap_durumu=?,
                 ty_urun_id=?,yukleme_tarihi=?
                 WHERE magaza_id=? AND question_id=?",
                [$barcode,$urunAdi,$soruMetni,$cevapMetni,$soruT,$cevapT,
                 $durum,$tyId,$now,
                 $this->magazaId,$questionId]
            );
            return [0, 1, $isUnanswered];
        }

        try {
            DB::exec(
                "INSERT INTO musteri_sorulari
                 (magaza_id,question_id,barcode,urun_adi,soru_metni,cevap_metni,
                  soru_tarihi,cevap_tarihi,cevap_durumu,ty_urun_id,yukleme_tarihi)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                [$this->magazaId,$questionId,$barcode,$urunAdi,$soruMetni,$cevapMetni,
                 $soruT,$cevapT,$durum,$tyId,$now]
            );
            return [1, 0, $isUnanswered];
        } catch (PDOException $e) {
            return [0, 0, 0];
        }
    }

    /**
     * Müşteri sorusuna yanıt gönder.
     *
     * @param string $questionId  Trendyol question ID
     * @param string $cevap       Yanıt metni
     */
    public function answerQuestion(string $questionId, string $cevap): array {
        return $this->requestPost(
            "/integration/claim/sellers/{$this->sellerId}/questions",
            ['id' => $questionId, 'text' => $cevap]
        );
    }

    /** POST isteği gönder (JSON body) */
    private function requestPost(string $endpoint, array $payload): array {
        if (!$this->isConfigured()) {
            return ['error' => 'API bilgileri eksik.'];
        }

        $url  = $this->baseUrl . $endpoint;
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'User-Agent: ' . $this->userAgent,
            ],
            CURLOPT_USERPWD        => $this->apiKey . ':' . $this->apiSecret,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err) return ['error' => 'cURL hatası: ' . $err];
        if ($httpCode >= 400) return ['error' => "HTTP $httpCode", 'body' => $resp];

        return ['ok' => true, 'status' => $httpCode];
    }

    /**
     * Reklam kampanyalarını Trendyol API'den çekip reklamlar tablosuna kaydeder.
     * Endpoint: GET /sapigw/sellers/{sellerId}/advertisements
     */
    public function syncAds(): array {
        if (!$this->isConfigured()) {
            return ['error' => 'API bilgileri eksik.'];
        }

        $inserted = 0;
        $updated  = 0;
        $now      = date('Y-m-d H:i:s');
        $page     = 0;
        $statusMap = [
            'ACTIVE' => 'Yayında', 'PAUSED' => 'Duraklatıldı',
            'COMPLETED' => 'Tamamlandı', 'DRAFT' => 'Taslak',
            'SUSPENDED' => 'Askıya Alındı', 'PENDING' => 'Beklemede',
        ];

        do {
            $data = $this->request(
                "/sapigw/sellers/{$this->sellerId}/advertisements",
                ['page' => $page, 'size' => 200]
            );

            if (isset($data['error'])) {
                return ['error' => $data['error'], 'inserted' => $inserted, 'updated' => $updated];
            }

            $items      = $data['content'] ?? $data['advertisements'] ?? (isset($data[0]) ? $data : []);
            $totalPages = (int)($data['totalPages'] ?? $data['totalPage'] ?? 1);

            if (!is_array($items) || empty($items)) break;

            foreach ($items as $item) {
                $kampId = (string)($item['id'] ?? '');
                $adi    = (string)($item['name'] ?? $item['campaignName'] ?? '');
                if (!$kampId && !$adi) continue;
                if (!$kampId) $kampId = md5($adi);

                $statuRaw = (string)($item['status'] ?? $item['campaignStatus'] ?? '');
                $statuTR  = $statusMap[$statuRaw] ?? $statuRaw;

                $basT   = (string)($item['startDate']       ?? $item['startTime']       ?? '');
                $bitT   = (string)($item['endDate']         ?? $item['endTime']         ?? '');
                $urunAd = (int)($item['productCount']       ?? $item['productSize']     ?? 0);
                $butce  = (float)($item['budget']           ?? $item['totalBudget']     ?? 0);
                $kalan  = (float)($item['remainingBudget']  ?? 0);
                $harcama= (float)($item['spend']            ?? $item['totalSpend']      ?? 0);
                $tbm    = (string)($item['bid']             ?? '');
                $gcTbm  = (float)($item['realBid']         ?? $item['avgBid']           ?? 0);
                $tikl   = (int)($item['clicks']             ?? 0);
                $gorum  = (int)($item['impressions']        ?? 0);
                $dSatis = (int)($item['directSales']        ?? $item['directOrderCount'] ?? 0);
                $iSatis = (int)($item['indirectSales']      ?? $item['indirectOrderCount'] ?? 0);
                $tSatis = (int)($item['totalSales']         ?? ($dSatis + $iSatis));
                $dCiro  = (float)($item['directRevenue']    ?? $item['directAmount']    ?? 0);
                $iCiro  = (float)($item['indirectRevenue']  ?? $item['indirectAmount']  ?? 0);
                $tCiro  = (float)($item['totalRevenue']     ?? ($dCiro + $iCiro));
                $roas   = $harcama > 0 ? round($tCiro / $harcama, 2) : 0.0;

                $exists = DB::scalar(
                    "SELECT id FROM reklamlar WHERE magaza_id=? AND kampanya_id=?",
                    [$this->magazaId, $kampId]
                );

                if ($exists) {
                    DB::exec(
                        "UPDATE reklamlar
                         SET reklam_adi=?, statu=?, baslangic_tarihi=?, bitis_tarihi=?,
                             urun_adedi=?, toplam_butce=?, kalan_butce=?, harcama=?,
                             tbm_teklif=?, gerceklesen_tbm=?, tiklanma=?, goruntulenme=?,
                             dogrudan_satis=?, dolayli_satis=?, toplam_satis=?,
                             dogrudan_ciro=?, dolayli_ciro=?, toplam_ciro=?, roas=?,
                             yukleme_tarihi=?
                         WHERE magaza_id=? AND kampanya_id=?",
                        [$adi, $statuTR, $basT, $bitT, $urunAd, $butce, $kalan, $harcama,
                         $tbm, $gcTbm, $tikl, $gorum, $dSatis, $iSatis, $tSatis,
                         $dCiro, $iCiro, $tCiro, $roas, $now,
                         $this->magazaId, $kampId]
                    );
                    $updated++;
                } else {
                    try {
                        DB::exec(
                            "INSERT INTO reklamlar
                             (magaza_id, kampanya_id, reklam_adi, statu,
                              baslangic_tarihi, bitis_tarihi, urun_adedi,
                              toplam_butce, kalan_butce, harcama, tbm_teklif, gerceklesen_tbm,
                              tiklanma, goruntulenme, dogrudan_satis, dolayli_satis, toplam_satis,
                              dogrudan_ciro, dolayli_ciro, toplam_ciro, roas, yukleme_tarihi)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                            [$this->magazaId, $kampId, $adi, $statuTR,
                             $basT, $bitT, $urunAd,
                             $butce, $kalan, $harcama, $tbm, $gcTbm,
                             $tikl, $gorum, $dSatis, $iSatis, $tSatis,
                             $dCiro, $iCiro, $tCiro, $roas, $now]
                        );
                        $inserted++;
                    } catch (PDOException $e) {}
                }
            }

            $page++;
            if (count($items) < 200) break;
        } while ($page < $totalPages && $page < 20);

        return ['inserted' => $inserted, 'updated' => $updated, 'total' => $inserted + $updated];
    }

    private function migrateOrderColumns(): void {
        $cols = DB::rows("SHOW COLUMNS FROM siparisler");
        $names = array_column($cols, 'Field');
        $add = [];
        if (!in_array('musteri_tam_ad', $names))     $add[] = "ADD COLUMN musteri_tam_ad VARCHAR(100)";
        if (!in_array('kargo_takip_no', $names))     $add[] = "ADD COLUMN kargo_takip_no VARCHAR(50)";
        if (!in_array('kargo_firmasi', $names))      $add[] = "ADD COLUMN kargo_firmasi VARCHAR(60)";
        if (!in_array('api_statusu', $names))        $add[] = "ADD COLUMN api_statusu VARCHAR(40)";
        if (!in_array('shipment_package_id', $names))$add[] = "ADD COLUMN shipment_package_id VARCHAR(50)";
        if (!in_array('api_son_guncelleme', $names)) $add[] = "ADD COLUMN api_son_guncelleme DATETIME";
        if ($add) {
            DB::get()->exec("ALTER TABLE siparisler " . implode(', ', $add));
        }

        // siparis_satirlari tablosunu oluştur (magaza_id zorunlu)
        DB::get()->exec("CREATE TABLE IF NOT EXISTS siparis_satirlari (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            magaza_id INT UNSIGNED NOT NULL DEFAULT 1,
            siparis_no VARCHAR(50), shipment_package_id VARCHAR(50),
            barcode VARCHAR(100), stock_code VARCHAR(100), product_name VARCHAR(300),
            adet INT DEFAULT 1, birim_fiyat DECIMAL(10,2) DEFAULT 0,
            kalem_tutari DECIMAL(12,2) DEFAULT 0, ty_urun_id VARCHAR(60) NULL,
            INDEX idx_magaza (magaza_id), INDEX idx_sipno (siparis_no), INDEX idx_barcode (barcode)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }








}