<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class HolidayController extends Controller
{
    /**
     * 祝日一覧取得
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'sometimes|integer|min:2020|max:2030',
            'start_date' => 'sometimes|date|date_format:Y-m-d',
            'end_date' => 'sometimes|date|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        try {
            // キャッシュキーを生成
            $cacheKey = 'holidays:';
            if ($request->has('year')) {
                $year = $request->integer('year');
                $cacheKey .= "year:{$year}";
            } elseif ($request->has('start_date') && $request->has('end_date')) {
                $cacheKey .= "range:{$request->start_date}_{$request->end_date}";
            } else {
                $cacheKey .= "year:" . now()->year;
            }

            // キャッシュから取得（1時間有効）
            $holidays = \Cache::remember($cacheKey, 3600, function () use ($request) {
                $query = Holiday::query();

                if ($request->has('year')) {
                    $year = $request->integer('year');
                    $query->whereYear('holiday_date', $year);
                } elseif ($request->has('start_date') && $request->has('end_date')) {
                    $query->whereBetween('holiday_date', [$request->start_date, $request->end_date]);
                } else {
                    // デフォルト：今年
                    $query->whereYear('holiday_date', now()->year);
                }

                return $query->orderBy('holiday_date')->get();
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'holidays' => $holidays->map(function ($holiday) {
                        return [
                            'date' => $holiday->holiday_date,
                            'name' => $holiday->name,
                            'source' => $holiday->source,
                            'imported_at' => $holiday->imported_at?->toISOString(),
                        ];
                    }),
                    'count' => $holidays->count(),
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Holiday list fetch failed', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '祝日一覧の取得に失敗しました',
            ], 500);
        }
    }

    /**
     * 祝日手動追加
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('manageHolidays', auth()->user());

        $request->validate([
            'date' => 'required|date|date_format:Y-m-d|unique:holidays,holiday_date',
            'name' => 'required|string|max:50',
        ]);

        try {
            DB::beginTransaction();

            $holiday = Holiday::create([
                'holiday_date' => $request->date,
                'name' => $request->name,
                'source' => 'manual',
                'imported_at' => now(),
            ]);

            // 監査ログ記録
            $this->auditLog('create', 'holiday', null, [
                'date' => $request->date,
                'name' => $request->name,
                'source' => 'manual',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '祝日を追加しました',
                'data' => [
                    'date' => $holiday->holiday_date,
                    'name' => $holiday->name,
                    'source' => $holiday->source,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Holiday creation failed', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '祝日の追加に失敗しました',
            ], 500);
        }
    }

    /**
     * 祝日削除
     */
    public function destroy(string $date): JsonResponse
    {
        Gate::authorize('manageHolidays', auth()->user());

        try {
            DB::beginTransaction();

            $holiday = Holiday::where('holiday_date', $date)->firstOrFail();
            
            $holidayData = $holiday->toArray();
            $holiday->delete();

            // 監査ログ記録
            $this->auditLog('delete', 'holiday', null, [
                'deleted_data' => $holidayData,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '祝日を削除しました',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Holiday deletion failed', [
                'date' => $date,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '祝日の削除に失敗しました',
            ], 500);
        }
    }

    /**
     * CSVファイルから祝日一括取り込み
     */
    public function importCsv(Request $request): JsonResponse
    {
        Gate::authorize('manageHolidays', auth()->user());

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
            'year' => 'required|integer|min:2020|max:2030',
            'overwrite' => 'sometimes|boolean',
        ]);

        try {
            DB::beginTransaction();

            $file = $request->file('csv_file');
            $year = $request->integer('year');
            $overwrite = $request->boolean('overwrite', false);

            // CSVファイル読み込み
            $csvData = $this->parseCsvFile($file);
            
            // バリデーション
            $validationResult = $this->validateCsvData($csvData, $year);
            if (!$validationResult['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'CSVデータにエラーがあります',
                    'errors' => $validationResult['errors'],
                ], 422);
            }

            // 既存データの処理
            if ($overwrite) {
                Holiday::whereYear('holiday_date', $year)
                    ->where('source', 'import')
                    ->delete();
            }

            // 祝日データ一括挿入
            $importedCount = 0;
            $skippedCount = 0;
            $errors = [];

            foreach ($validationResult['data'] as $index => $row) {
                try {
                    $existing = Holiday::where('holiday_date', $row['date'])->first();
                    
                    if ($existing && !$overwrite) {
                        $skippedCount++;
                        continue;
                    }

                    if ($existing && $overwrite) {
                        $existing->update([
                            'name' => $row['name'],
                            'source' => 'import',
                            'imported_at' => now(),
                        ]);
                    } else {
                        Holiday::create([
                            'holiday_date' => $row['date'],
                            'name' => $row['name'],
                            'source' => 'import',
                            'imported_at' => now(),
                        ]);
                    }

                    $importedCount++;

                } catch (\Exception $e) {
                    $errors[] = "行{$index}: " . $e->getMessage();
                }
            }

            // 監査ログ記録
            $this->auditLog('import', 'holiday_csv', null, [
                'year' => $year,
                'imported_count' => $importedCount,
                'skipped_count' => $skippedCount,
                'overwrite' => $overwrite,
                'filename' => $file->getClientOriginalName(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '祝日データの取り込みが完了しました',
                'data' => [
                    'imported_count' => $importedCount,
                    'skipped_count' => $skippedCount,
                    'total_processed' => $importedCount + $skippedCount,
                    'errors' => $errors,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Holiday CSV import failed', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '祝日データの取り込みに失敗しました',
            ], 500);
        }
    }

    /**
     * 内閣府から祝日データを自動取得（将来実装用）
     */
    public function fetchGovernmentData(Request $request): JsonResponse
    {
        Gate::authorize('manageHolidays', auth()->user());

        $request->validate([
            'year' => 'required|integer|min:2020|max:2030',
        ]);

        try {
            $year = $request->integer('year');

            // 内閣府の祝日CSVのURL
            $url = "https://www8.cao.go.jp/chosei/shukujitsu/syukujitsu.csv";

            // HTTP クライアントでCSVデータを取得
            $csvContent = $this->fetchGovernmentCsv($url);
            
            if (!$csvContent) {
                return response()->json([
                    'success' => false,
                    'message' => '政府サイトからデータを取得できませんでした',
                ], 422);
            }

            // CSVデータを解析して指定年のみ抽出
            $holidays = $this->parseGovernmentCsv($csvContent, $year);

            // データベースに保存
            $importedCount = $this->saveGovernmentHolidays($holidays, $year);

            // 監査ログ記録
            $this->auditLog('import', 'government_holiday', null, [
                'year' => $year,
                'imported_count' => $importedCount,
                'source_url' => $url,
            ]);

            return response()->json([
                'success' => true,
                'message' => "政府データから{$year}年の祝日を取得しました",
                'data' => [
                    'year' => $year,
                    'imported_count' => $importedCount,
                    'holidays' => $holidays,
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Government holiday fetch failed', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '政府データの取得に失敗しました',
            ], 500);
        }
    }

    /**
     * CSVファイル解析
     */
    private function parseCsvFile($file): array
    {
        $csvData = [];
        $handle = fopen($file->getRealPath(), 'r');

        // BOM除去
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // ヘッダー行をスキップ
        $header = fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) >= 2 && !empty($row[0]) && !empty($row[1])) {
                $csvData[] = [
                    'date' => trim($row[0]),
                    'name' => trim($row[1]),
                ];
            }
        }

        fclose($handle);
        return $csvData;
    }

    /**
     * CSVデータバリデーション
     */
    private function validateCsvData(array $csvData, int $year): array
    {
        $errors = [];
        $validData = [];

        foreach ($csvData as $index => $row) {
            $rowNumber = $index + 2; // ヘッダー行を考慮

            // 日付形式チェック
            try {
                $date = Carbon::createFromFormat('Y-m-d', $row['date']);
                if (!$date || $date->year !== $year) {
                    $errors[] = "行{$rowNumber}: 日付が{$year}年ではありません ({$row['date']})";
                    continue;
                }
            } catch (\Exception $e) {
                $errors[] = "行{$rowNumber}: 日付形式が正しくありません ({$row['date']})";
                continue;
            }

            // 祝日名チェック
            if (empty($row['name']) || mb_strlen($row['name']) > 50) {
                $errors[] = "行{$rowNumber}: 祝日名が無効です";
                continue;
            }

            $validData[] = $row;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $validData,
        ];
    }

    /**
     * 政府CSVデータ取得
     */
    private function fetchGovernmentCsv(string $url): ?string
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'AYUMI Holiday Importer/1.0',
                ]
            ]);

            $content = file_get_contents($url, false, $context);
            return $content ?: null;

        } catch (\Exception $e) {
            \Log::warning('Government CSV fetch failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 政府CSVデータ解析
     */
    private function parseGovernmentCsv(string $csvContent, int $year): array
    {
        $holidays = [];
        $lines = explode("\n", $csvContent);

        foreach ($lines as $line) {
            $row = str_getcsv($line);
            
            if (count($row) >= 2 && !empty($row[0]) && !empty($row[1])) {
                try {
                    $date = Carbon::createFromFormat('Y/m/d', trim($row[0]));
                    
                    if ($date && $date->year === $year) {
                        $holidays[] = [
                            'date' => $date->format('Y-m-d'),
                            'name' => trim($row[1]),
                        ];
                    }
                } catch (\Exception $e) {
                    continue; // 無効な行はスキップ
                }
            }
        }

        return $holidays;
    }

    /**
     * 政府祝日データ保存
     */
    private function saveGovernmentHolidays(array $holidays, int $year): int
    {
        DB::beginTransaction();

        try {
            // 既存の政府データを削除
            Holiday::whereYear('holiday_date', $year)
                ->where('source', 'import')
                ->delete();

            $importedCount = 0;

            foreach ($holidays as $holiday) {
                Holiday::create([
                    'holiday_date' => $holiday['date'],
                    'name' => $holiday['name'],
                    'source' => 'import',
                    'imported_at' => now(),
                ]);
                $importedCount++;
            }

            DB::commit();
            return $importedCount;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 監査ログ記録
     */
    private function auditLog(string $action, string $entity, ?int $entityId, array $meta = []): void
    {
        try {
            \App\Models\AuditLog::create([
                'actor_type' => 'staff',
                'actor_id' => auth()->id(),
                'occurred_at' => now(),
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'diff_json' => null,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'meta' => json_encode($meta),
            ]);
        } catch (\Exception $e) {
            \Log::error('Audit log creation failed', [
                'action' => $action,
                'entity' => $entity,
                'error' => $e->getMessage(),
            ]);
        }
    }
}