<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 開発用 質問掲示板シーダー。
 *
 * 公開資格 5 件に各 5 スレッド（計 25 件）を作成し、20 件ページネーション、
 * 回答数、解決状態、新着順、担当コーチ回答をまとめて実機確認できるようにする。
 * 管理者の公開範囲確認用として、下書き・アーカイブ資格にも 1 件ずつ作成する。
 *
 * 依存順序: UserSeeder → CertificationSeeder（担当コーチ割当を含む）→ 本 Seeder。
 */
final class QaThreadSeeder extends Seeder
{
    /** @var array<int, int> */
    private const REPLY_COUNTS = [0, 1, 3, 0, 2];

    /** @var array<int, array{title: string, body: string}> */
    private const THREAD_CONTENTS = [
        [
            'title' => '学習を始めるときのおすすめの順番を教えてください',
            'body' => '教材を最初から順番に読む方法と、問題演習から始める方法のどちらがよいでしょうか。',
        ],
        [
            'title' => '模擬試験の復習方法について',
            'body' => '間違えた問題を復習するとき、解説を読む以外に意識するとよい点を教えてください。',
        ],
        [
            'title' => '苦手分野の勉強時間の配分に悩んでいます',
            'body' => '得意分野と苦手分野のどちらに時間を多く使うべきか、試験前の配分を知りたいです。',
        ],
        [
            'title' => '自己解決しました：教材の進捗が反映されない場合',
            'body' => 'ページを再読み込みしたところ進捗が反映されました。同じ状況の方の参考として残します。',
        ],
        [
            'title' => '試験直前一週間の過ごし方を相談したいです',
            'body' => '直前期に新しい範囲へ進むか、これまでの範囲を復習するか迷っています。',
        ],
    ];

    public function run(): void
    {
        $student = User::query()->where('email', 'student@certify-lms.test')->first();
        $secondStudent = User::query()->where('email', 'student-noquota@certify-lms.test')->first();

        if ($student === null || $secondStudent === null) {
            $this->command?->warn('QaThreadSeeder: 固定受講生が存在しません。先に UserSeeder を実行してください。');

            return;
        }

        $published = Certification::query()
            ->where('status', CertificationStatus::Published)
            ->with('coaches')
            ->orderBy('created_at')
            ->get();

        if ($published->count() !== 5) {
            $this->command?->warn("QaThreadSeeder: 公開資格は 5 件必要です（現在 {$published->count()} 件）。処理をスキップします。");

            return;
        }

        DB::transaction(function () use ($published, $student, $secondStudent): void {
            $sequence = 0;

            foreach ($published as $certificationIndex => $certification) {
                $coach = $certification->coaches->first();
                if ($coach === null) {
                    $this->command?->warn("QaThreadSeeder: {$certification->name} に担当コーチがいないため処理をスキップします。");

                    continue;
                }

                foreach (self::THREAD_CONTENTS as $threadIndex => $content) {
                    $createdAt = Carbon::now()->subHours($sequence * 3 + 1);
                    $resolved = in_array($threadIndex, [1, 3], true);
                    $thread = $this->createThread(
                        certification: $certification,
                        student: $student,
                        title: $certification->name.'：'.$content['title'],
                        body: $content['body'],
                        createdAt: $createdAt,
                        resolved: $resolved,
                    );

                    $this->createReplies(
                        thread: $thread,
                        count: self::REPLY_COUNTS[$threadIndex],
                        coach: $coach,
                        student: $secondStudent,
                        baseTime: $createdAt,
                        includeSearchKeyword: $certificationIndex === 0 && $threadIndex === 2,
                    );
                    $sequence++;
                }
            }

            $this->createNonPublishedSamples($student);
        });
    }

    private function createThread(
        Certification $certification,
        User $student,
        string $title,
        string $body,
        Carbon $createdAt,
        bool $resolved,
    ): QaThread {
        $thread = QaThread::query()->create([
            'certification_id' => $certification->id,
            'user_id' => $student->id,
            'title' => $title,
            'body' => $body,
            'status' => $resolved ? QaThreadStatus::Resolved : QaThreadStatus::Open,
            'resolved_at' => $resolved ? $createdAt->copy()->addMinutes(45) : null,
        ]);

        $thread->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $thread;
    }

    private function createReplies(
        QaThread $thread,
        int $count,
        User $coach,
        User $student,
        Carbon $baseTime,
        bool $includeSearchKeyword,
    ): void {
        $bodies = [
            $includeSearchKeyword
                ? 'まず「回答本文検索確認」というキーワードで関連する解説を探し、間違えた理由を言葉にしてみましょう。'
                : 'まず間違えた理由を言葉にしてから、該当する教材へ戻る方法がおすすめです。',
            'ありがとうございます。次回からその方法で復習し、理解できない箇所を整理してみます。',
            '一度に全部を進めず、毎日の目標を小さく区切ると継続しやすくなります。',
        ];

        for ($index = 0; $index < $count; $index++) {
            $createdAt = $baseTime->copy()->addMinutes(($index + 1) * 10);
            $reply = QaReply::query()->create([
                'qa_thread_id' => $thread->id,
                'user_id' => $index % 2 === 0 ? $coach->id : $student->id,
                'body' => $bodies[$index],
            ]);
            $reply->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }
    }

    private function createNonPublishedSamples(User $student): void
    {
        $certifications = Certification::query()
            ->whereIn('status', [CertificationStatus::Draft, CertificationStatus::Archived])
            ->orderBy('created_at')
            ->get();

        foreach ($certifications as $index => $certification) {
            $this->createThread(
                certification: $certification,
                student: $student,
                title: $certification->name.'：管理者表示確認用の質問',
                body: '公開停止中の資格に紐づく質問です。管理者のモデレーション画面でのみ確認します。',
                createdAt: Carbon::now()->subDays(10 + $index),
                resolved: $index % 2 === 1,
            );
        }
    }
}
