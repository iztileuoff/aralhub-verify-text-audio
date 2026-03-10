<?php

namespace App\Console\Commands;

use App\Models\Action;
use App\Models\Text;
use Illuminate\Console\Command;

class TestCommand extends Command
{
    protected $signature = 'test';

    protected $description = 'Command description';

    public function handle(): void
    {
        $texts = Text::query()
            ->whereNotNull('edit_user_id')
            ->get();

        foreach ($texts as $text) {
            $action = Action::create([
                'text_id' => $text->id,
                'user_id' => $text->edit_user_id,
                'old_text' => $text->filter_original_transcript,
                'new_text' => $text->edit_original_transcript,
            ]);
        }

        $this->info('Success!');
    }
}
