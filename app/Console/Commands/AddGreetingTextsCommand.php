<?php

namespace App\Console\Commands;

use App\Models\Text;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AddGreetingTextsCommand extends Command
{
    protected $signature = 'texts:add-greetings {--file=8} {--user=1} {--count=50}';

    protected $description = 'Add greeting transcripts to a file (count copies of each phrase)';

    /**
     * @var array<int, string>
     */
    protected array $phrases = [
        'Assalawma áleykum.',
        'Waáleykum assalam.',
    ];

    public function handle(): void
    {
        $fileId = (int) $this->option('file');
        $userId = (int) $this->option('user');
        $count = (int) $this->option('count');

        $now = now();

        DB::beginTransaction();

        try {
            $created = 0;

            foreach ($this->phrases as $phrase) {
                for ($i = 0; $i < $count; $i++) {
                    $editOriginalTranscript = $phrase;
                    $editNormalizedTranscript = mb_strtolower(trim($editOriginalTranscript, '.?!'));

                    $words = explode(' ', $editNormalizedTranscript);
                    $tokenizedParts = array_map(function (string $word): string {
                        $chars = mb_str_split($word);

                        return implode(' ', $chars).' |';
                    }, $words);

                    $editTokenizedTranscript = implode(' ', $tokenizedParts);

                    Text::create([
                        'file_id' => $fileId,
                        'transcript_id' => null,
                        'edit_original_transcript' => $editOriginalTranscript,
                        'edit_normalized_transcript' => $editNormalizedTranscript,
                        'edit_tokenized_transcript' => $editTokenizedTranscript,
                        'edit_user_id' => $userId,
                        'edit_started_at' => $now,
                        'edit_finished_at' => $now,
                        'is_main' => true,
                    ]);

                    $created++;
                }
            }

            DB::commit();

            $this->info("Created {$created} texts for file #{$fileId}.");
        } catch (\Throwable $e) {
            DB::rollBack();

            $this->error($e->getMessage());
        }
    }
}
