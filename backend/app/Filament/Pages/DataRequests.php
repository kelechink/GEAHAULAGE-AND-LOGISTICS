<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Privacy\PersonalData;
use App\Enums\Permission;
use App\Models\Privacy\PrivacyRequest;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Data-protection requests (NDPA 2023, PRD 16): find, export or erase one person's data. */
class DataRequests extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-finger-print';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Data requests';

    protected static ?string $title = 'Data protection requests';

    protected static ?string $slug = 'data-requests';

    protected static ?int $navigationSort = 60;

    protected static string $view = 'filament.pages.data-requests';

    public string $subject = '';

    public string $confirm = '';

    /** @var array<string, int>|null */
    public ?array $counts = null;

    /** @var array<string, int|string>|null */
    public ?array $erased = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::PrivacyManage->value) ?? false;
    }

    public function search(PersonalData $data): void
    {
        $this->erased = null;
        $this->validate(['subject' => ['required', 'string', 'max:160']]);
        try {
            $this->counts = $data->counts($this->subject);
        } catch (InvalidArgumentException $e) {
            $this->counts = null;
            $this->addError('subject', $e->getMessage());
        }
    }

    public function export(PersonalData $data): ?BinaryFileResponse
    {
        if ($this->counts === null) {
            return null;
        }
        $path = $data->export($this->subject, $this->user());

        return response()->download(Storage::disk('local')->path($path), 'gea-personal-data.zip')->deleteFileAfterSend();
    }

    public function erase(PersonalData $data): void
    {
        $this->resetErrorBag();
        if ($this->counts === null) {
            return;
        }
        if (trim($this->confirm) !== 'ERASE') {
            $this->addError('confirm', 'Type ERASE in capitals to confirm.');

            return;
        }
        $this->erased = $data->erase($this->subject, $this->user());
        $this->counts = null;
        $this->confirm = '';
        Notification::make()->success()->title('Personal data erased. The request is recorded below.')->send();
    }

    /** @return Collection<int, PrivacyRequest> */
    public function history(): Collection
    {
        return PrivacyRequest::query()->with('handler')->latest()->limit(20)->get();
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
