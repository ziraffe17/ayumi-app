<?php

namespace App\Services;

use App\Models\Holiday;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;

class HolidayService
{
    /** 指定月(YYYY-MM)の祝日マップを返す: ['YYYY-MM-DD' => '名称'] */
    public function mapForMonth(string $yyyyMm, string $tz = 'Asia/Tokyo'): array
    {
        // キャッシュキー
        $cacheKey = "holidays:month:{$yyyyMm}";

        // キャッシュから取得（24時間有効）
        return \Cache::remember($cacheKey, 86400, function () use ($yyyyMm, $tz) {
            $start = Carbon::createFromFormat('Y-m', $yyyyMm, $tz)->startOfMonth()->toDateString();
            $end   = Carbon::createFromFormat('Y-m', $yyyyMm, $tz)->endOfMonth()->toDateString();

            return DB::table('holidays')
                ->whereBetween('holiday_date', [$start, $end])
                ->pluck('name', 'holiday_date')
                ->all();
        });
    }

    /** 祝日かどうか（mapを渡すと高速） */
    public function isHoliday(string $date, array $map): bool
    {
        return array_key_exists($date, $map);
    }

    /** 祝日名を取得（なければ null） */
    public function nameOf(string $date, array $map): ?string
    {
        return $map[$date] ?? null;
    }

    /**
     * 政府提供の祝日APIから祝日データを取得・更新
     * @param int $year 対象年（省略時は当年と翌年）
     * @return array ['success' => bool, 'message' => string, 'count' => int]
     */
    public function importFromGovernmentApi(?int $year = null): array
    {
        try {
            $years = $year ? [$year] : [Carbon::now()->year, Carbon::now()->addYear()->year];
            $totalCount = 0;

            foreach ($years as $targetYear) {
                $result = $this->fetchHolidaysForYear($targetYear);
                if ($result['success']) {
                    $totalCount += $result['count'];
                }
            }

            return [
                'success' => true,
                'message' => "{$totalCount}件の祝日を取り込みました",
                'count' => $totalCount
            ];

        } catch (\Exception $e) {
            \Log::error('Holiday API import failed', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'message' => '祝日APIからの取り込みに失敗しました: ' . $e->getMessage(),
                'count' => 0
            ];
        }
    }

    /**
     * 指定年の祝日を内閣府APIから取得
     */
    private function fetchHolidaysForYear(int $year): array
    {
        // 内閣府の祝日CSV API（実際のAPI）
        $url = "https://www8.cao.go.jp/chosei/shukujitsu/syukujitsu.csv";
        
        try {
            $response = Http::timeout(30)->get($url);
            
            if (!$response->successful()) {
                throw new \Exception("HTTP {$response->status()}: API取得失敗");
            }

            $csv = $response->body();
            $lines = array_filter(explode("\n", $csv));
            $count = 0;

            foreach ($lines as $line) {
                if (empty(trim($line)) || strpos($line, '国民の祝日') !== false) {
                    continue; // ヘッダー行をスキップ
                }

                $data = str_getcsv($line);
                if (count($data) >= 2) {
                    $date = trim($data[0]);
                    $name = trim($data[1]);

                    // 指定年のデータのみ処理
                    if (strpos($date, (string)$year) === 0) {
                        Holiday::updateOrCreate(
                            ['holiday_date' => $date],
                            [
                                'name' => $name,
                                'source' => 'government_api',
                                'imported_at' => now()
                            ]
                        );
                        $count++;
                    }
                }
            }

            return ['success' => true, 'count' => $count];

        } catch (\Exception $e) {
            \Log::error("Holiday fetch failed for year {$year}", ['error' => $e->getMessage()]);
            
            // フォールバック: 基本的な祝日のみ登録
            return $this->createBasicHolidays($year);
        }
    }

    /**
     * フォールバック用：基本的な祝日のみ作成
     */
    private function createBasicHolidays(int $year): array
    {
        $basicHolidays = [
            '01-01' => '元日',
            '02-11' => '建国記念の日',
            '04-29' => '昭和の日',
            '05-03' => '憲法記念日',
            '05-04' => 'みどりの日',
            '05-05' => 'こどもの日',
            '08-11' => '山の日',
            '11-03' => '文化の日',
            '11-23' => '勤労感謝の日',
        ];

        $count = 0;
        foreach ($basicHolidays as $monthDay => $name) {
            $date = $year . '-' . $monthDay;
            
            Holiday::updateOrCreate(
                ['holiday_date' => $date],
                [
                    'name' => $name,
                    'source' => 'basic',
                    'imported_at' => now()
                ]
            );
            $count++;
        }

        return ['success' => true, 'count' => $count];
    }

    /**
     * 個別手動入力
     */
    public function addManualHoliday(string $date, string $name): array
    {
        try {
            Holiday::updateOrCreate(
                ['holiday_date' => $date],
                [
                    'name' => $name,
                    'source' => 'manual',
                    'imported_at' => now()
                ]
            );

            return [
                'success' => true,
                'message' => "祝日「{$name}」を追加しました"
            ];

        } catch (\Exception $e) {
            \Log::error('Manual holiday addition failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => '祝日の追加に失敗しました: ' . $e->getMessage()
            ];
        }
    }

    /**
     * CSVファイルから祝日を取り込み
     */
    public function importFromCsv(string $csvContent): array
    {
        try {
            $lines = array_filter(explode("\n", $csvContent));
            $count = 0;
            $errors = [];

            foreach ($lines as $lineNumber => $line) {
                $line = trim($line);
                if (empty($line)) continue;

                $data = str_getcsv($line);
                if (count($data) < 2) {
                    $errors[] = "行 " . ($lineNumber + 1) . ": データが不正です";
                    continue;
                }

                $date = trim($data[0]);
                $name = trim($data[1]);

                // 日付形式チェック
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    $errors[] = "行 " . ($lineNumber + 1) . ": 日付形式が不正です（{$date}）";
                    continue;
                }

                try {
                    Holiday::updateOrCreate(
                        ['holiday_date' => $date],
                        [
                            'name' => $name,
                            'source' => 'csv_import',
                            'imported_at' => now()
                        ]
                    );
                    $count++;
                } catch (\Exception $e) {
                    $errors[] = "行 " . ($lineNumber + 1) . ": {$e->getMessage()}";
                }
            }

            $message = "{$count}件の祝日を取り込みました";
            if (!empty($errors)) {
                $message .= "（エラー: " . count($errors) . "件）";
            }

            return [
                'success' => true,
                'message' => $message,
                'count' => $count,
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            \Log::error('CSV holiday import failed', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'message' => 'CSV取り込みに失敗しました: ' . $e->getMessage(),
                'count' => 0,
                'errors' => []
            ];
        }
    }

    /**
     * 古い祝日データを削除
     */
    public function cleanupOldHolidays(int $keepYears = 3): int
    {
        $cutoffDate = Carbon::now()->subYears($keepYears)->format('Y-01-01');
        
        return Holiday::where('holiday_date', '<', $cutoffDate)->delete();
    }

    /**
     * 祝日統計を取得
     */
    public function getStatistics(): array
    {
        $currentYear = Carbon::now()->year;
        
        return [
            'total' => Holiday::count(),
            'current_year' => Holiday::whereYear('holiday_date', $currentYear)->count(),
            'next_year' => Holiday::whereYear('holiday_date', $currentYear + 1)->count(),
            'sources' => Holiday::selectRaw('source, COUNT(*) as count')
                ->groupBy('source')
                ->pluck('count', 'source')
                ->toArray(),
            'oldest' => Holiday::min('holiday_date'),
            'newest' => Holiday::max('holiday_date'),
            'last_import' => Holiday::max('imported_at'),
        ];
    }
}
