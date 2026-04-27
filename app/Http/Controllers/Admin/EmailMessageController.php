<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailMessage;
use App\Models\EmailMessageAttachment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmailMessageController extends Controller
{
    public function show(Request $request, int $emailMessage): View
    {
        $companyId = (int) $request->user()->company_id;
        $message = $this->findCompanyMessageOrFail($companyId, $emailMessage);
        $this->authorize('view', $message);

        if (! $message->is_seen) {
            $message->forceFill([
                'is_seen' => true,
            ])->save();
        }

        return view('admin.email.messages.show', [
            'message' => $message,
            'ccHeader' => $this->extractHeaderValue($message, 'Cc'),
        ]);
    }

    public function downloadAttachment(
        Request $request,
        int $emailMessage,
        int $emailMessageAttachment
    ): StreamedResponse {
        $companyId = (int) $request->user()->company_id;
        $message = $this->findCompanyMessageOrFail($companyId, $emailMessage);
        $this->authorize('view', $message);

        $attachment = EmailMessageAttachment::query()
            ->forCompany($companyId)
            ->where('email_message_id', $message->id)
            ->whereKey($emailMessageAttachment)
            ->firstOrFail();

        $this->authorize('download', $attachment);

        $storagePath = (string) ($attachment->storage_path ?? '');
        if ($storagePath === '' || ! str_starts_with(trim($storagePath, '/'), 'email/')) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($storagePath)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $storagePath,
            $attachment->filename
        );
    }

    private function findCompanyMessageOrFail(int $companyId, int $messageId): EmailMessage
    {
        return EmailMessage::query()
            ->forCompany($companyId)
            ->with([
                'attachments',
                'account',
            ])
            ->whereKey($messageId)
            ->firstOrFail();
    }

    private function extractHeaderValue(EmailMessage $message, string $headerName): ?string
    {
        $rawHeaders = $message->raw_headers;
        if (! is_array($rawHeaders)) {
            return null;
        }

        $header = isset($rawHeaders['header']) && is_string($rawHeaders['header'])
            ? $rawHeaders['header']
            : null;

        if (! is_string($header) || trim($header) === '') {
            return null;
        }

        $pattern = '/^'.preg_quote($headerName, '/').':\s*(.+)$/im';
        if (preg_match($pattern, $header, $matches) !== 1) {
            return null;
        }

        $value = trim((string) ($matches[1] ?? ''));

        return $value !== '' ? $value : null;
    }
}
