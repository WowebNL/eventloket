<?php

namespace App\Jobs\Zaak;

use App\Enums\AdviceStatus;
use App\Enums\ThreadType;
use App\Models\DefaultAdviceQuestion;
use App\Models\Message;
use App\Models\Threads\AdviceThread;
use App\Models\Zaak;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreateConceptAdviceQuestions implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Zaak $zaak
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Check if zaak has risk classification
        $risicoClassificatie = $this->zaak->reference_data->risico_classificatie ?? null;

        if (! $risicoClassificatie) {
            return;
        }

        // Get default advice questions for this municipality and risk classification
        $defaultQuestions = DefaultAdviceQuestion::query()
            ->where('municipality_id', $this->zaak->zaaktype->municipality_id)
            ->where('risico_classificatie', $risicoClassificatie)
            ->get();

        // Create concept advice threads for each default question
        foreach ($defaultQuestions as $defaultQuestion) {
            // Create the thread with concept status
            $thread = AdviceThread::create([
                'zaak_id' => $this->zaak->id,
                'type' => ThreadType::Advice,
                'title' => $defaultQuestion->title,
                'advisory_id' => $defaultQuestion->advisory_id,
                'advice_status' => AdviceStatus::Concept,
                'advice_due_at' => now()->addBusinessDays($defaultQuestion->response_deadline_days),
                'created_by' => null, // System-generated
            ]);

            // Create the initial message with the question description
            Message::create([
                'thread_id' => $thread->id,
                'user_id' => null, // System-generated
                'body' => $defaultQuestion->description,
            ]);
        }
    }
}
