<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ExportTemplateGenerate extends Command
{
    protected $signature = 'export:generate-templates';
    protected $description = 'CSV出力テンプレートを生成';

    public function handle(): int
    {
        $this->info('CSV出力テンプレートを生成中...');

        // 祝日CSVテンプレート
        $holidayTemplate = "日付,祝日名\n2024-01-01,元日\n2024-01-08,成人の日\n2024-02-11,建国記念の日\n2024-02-12,建国記念の日 振替休日\n2024-02-23,天皇誕生日\n2024-03-20,春分の日\n2024-04-29,昭和の日\n2024-05-03,憲法記念日\n2024-05-04,みどりの日\n2024-05-05,こどもの日\n2024-05-06,振替休日\n2024-07-15,海の日\n2024-08-11,山の日\n2024-08-12,振替休日\n2024-09-16,敬老の日\n2024-09-22,秋分の日\n2024-09-23,振替休日\n2024-10-14,スポーツの日\n2024-11-03,文化の日\n2024-11-04,振替休日\n2024-11-23,勤労感謝の日";

        Storage::disk('local')->put('templates/holidays_2024.csv', $holidayTemplate);

        // 出席予定CSVテンプレート
        $attendanceTemplate = "利用者ID,日付,時間枠,予定種別,備考\n1,2024-01-15,full,onsite,\n1,2024-01-16,full,onsite,\n1,2024-01-17,full,remote,在宅勤務\n1,2024-01-18,full,onsite,\n1,2024-01-19,full,off,有給休暇";

        Storage::disk('local')->put('templates/attendance_template.csv', $attendanceTemplate);

        $this->table(
            ['テンプレート', 'ファイル名'],
            [
                ['祝日データ', 'templates/holidays_2024.csv'],
                ['出席予定', 'templates/attendance_template.csv'],
            ]
        );

        $this->info('テンプレート生成完了！');
        $this->line('ファイルは storage/app/ に保存されました。');

        return Command::SUCCESS;
    }
}