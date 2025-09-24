<?php

namespace App\Livewire\Thread;

use App\Enums\AdviceStatus;
use App\Enums\Role;
use App\Enums\ThreadType;
use App\Models\Message;
use App\Models\Thread;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * @property \Filament\Schemas\Schema|mixed $form
 */
class MessageForm extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public Thread $thread;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                RichEditor::make('body')
                    ->label('Plaats een reactie')
                    ->required()
                    ->toolbarButtons([
                        ['bold', 'italic', 'underline', 'strike', 'link'],
                        ['h1', 'h2', 'h3', 'alignStart', 'alignCenter', 'alignEnd'],
                        ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
                    ]),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $this->authorize('create', [Message::class, $this->thread]);

        $data = $this->form->getState();

        Message::create([
            'thread_id' => $this->thread->id,
            'user_id' => auth()->id(),
            'body' => $data['body'],
        ]);

        if ($this->thread->type == ThreadType::Advice && $this->thread->advice_status === AdviceStatus::Asked && auth()->user()->role === Role::Advisor) {
            $this->thread->update(['advice_status' => AdviceStatus::AdvisoryReplied]);
        }

        $this->form->fill(); // reset form state

        $this->dispatch('thread-updated');

        Notification::make()
            ->title('Reactie toegevoegd')
            ->success()
            ->send();
    }

    public function render(): View
    {
        return view('livewire.thread.message-form');
    }
}
