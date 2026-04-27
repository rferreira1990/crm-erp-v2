<?php

namespace App\Services\Admin;

use App\Models\EmailAccount;
use Carbon\Carbon;
use RuntimeException;

class EmailAccountConnectionService
{
    private const CONNECT_TIMEOUT_SECONDS = 8;
    private const MAX_ATTACHMENT_BYTES = 10485760;

    /**
     * @throws RuntimeException
     */
    public function testConnection(EmailAccount $account): void
    {
        $connection = $this->openConnection($account, true);

        $this->closeConnection($connection);
    }

    /**
     * @return array<int, array{
     *   uid:string,
     *   message_id:?string,
     *   folder:string,
     *   from_email:?string,
     *   from_name:?string,
     *   to_email:?string,
     *   to_name:?string,
     *   subject:?string,
     *   body_text:?string,
     *   body_html:?string,
     *   snippet:?string,
     *   received_at:?string,
     *   is_seen:bool,
     *   has_attachments:bool,
     *   raw_headers:array<string,mixed>,
     *   attachments:array<int, array{
     *     filename:string,
     *     mime_type:?string,
     *     size_bytes:?int,
     *     content_id:?string,
     *     is_inline:bool,
     *     content_base64:?string
     *   }>
     * }>
     *
     * @throws RuntimeException
     */
    public function fetchLatestInbox(EmailAccount $account, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $connection = $this->openConnection($account);

        try {
            $uids = $this->resolveLatestUids($connection, $limit);
            if ($uids === []) {
                return [];
            }

            $messages = [];

            foreach ($uids as $uid) {
                $mapped = $this->mapMessageByUid($connection, (int) $uid, (string) ($account->imap_folder ?: 'INBOX'));

                if ($mapped !== null) {
                    $messages[] = $mapped;
                }
            }

            return $messages;
        } finally {
            $this->closeConnection($connection);
        }
    }

    /**
     * @param resource|\IMAP\Connection $connection
     * @return array<int, int>
     */
    private function resolveLatestUids($connection, int $limit): array
    {
        $totalMessages = (int) imap_num_msg($connection);
        if ($totalMessages <= 0) {
            return [];
        }

        $start = max(1, $totalMessages - $limit + 1);
        $uids = [];

        for ($messageNumber = $totalMessages; $messageNumber >= $start; $messageNumber--) {
            $uid = (int) imap_uid($connection, $messageNumber);
            if ($uid > 0) {
                $uids[] = $uid;
            }
        }

        return $uids;
    }

    /**
     * @return resource|\IMAP\Connection
     */
    private function openConnection(EmailAccount $account, bool $halfOpen = false)
    {
        $this->assertImapExtensionIsAvailable();
        $this->configureTimeouts();
        $this->preflightNetworkConnection($account);

        $password = $account->resolveImapPassword();
        if (! is_string($password) || trim($password) === '') {
            throw new RuntimeException('Nao existe password IMAP valida configurada para esta conta.');
        }

        $mailbox = $this->buildMailboxString($account);
        $flags = ($halfOpen && defined('OP_HALFOPEN')) ? OP_HALFOPEN : 0;

        $connection = @imap_open(
            $mailbox,
            (string) $account->imap_username,
            $password,
            $flags,
            1
        );

        if ($connection === false) {
            throw new RuntimeException($this->lastImapError('Falha de ligacao IMAP.'));
        }

        return $connection;
    }

    private function configureTimeouts(): void
    {
        if (function_exists('ini_set')) {
            @ini_set('default_socket_timeout', (string) self::CONNECT_TIMEOUT_SECONDS);
        }

        if (function_exists('imap_timeout')) {
            if (defined('IMAP_OPENTIMEOUT')) {
                @imap_timeout(IMAP_OPENTIMEOUT, self::CONNECT_TIMEOUT_SECONDS);
            }
            if (defined('IMAP_READTIMEOUT')) {
                @imap_timeout(IMAP_READTIMEOUT, self::CONNECT_TIMEOUT_SECONDS);
            }
            if (defined('IMAP_WRITETIMEOUT')) {
                @imap_timeout(IMAP_WRITETIMEOUT, self::CONNECT_TIMEOUT_SECONDS);
            }
            if (defined('IMAP_CLOSETIMEOUT')) {
                @imap_timeout(IMAP_CLOSETIMEOUT, self::CONNECT_TIMEOUT_SECONDS);
            }
        }
    }

    private function preflightNetworkConnection(EmailAccount $account): void
    {
        $host = trim((string) $account->imap_host);
        $port = (int) ($account->imap_port ?: 993);

        if ($host === '') {
            throw new RuntimeException('Host IMAP em falta.');
        }

        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('Porta IMAP invalida.');
        }

        $errorNumber = 0;
        $errorMessage = '';

        $socket = @stream_socket_client(
            sprintf('tcp://%s:%d', $host, $port),
            $errorNumber,
            $errorMessage,
            self::CONNECT_TIMEOUT_SECONDS,
            STREAM_CLIENT_CONNECT
        );

        if ($socket === false) {
            $message = trim($errorMessage) !== ''
                ? $errorMessage
                : 'Falha de ligacao de rede ao servidor IMAP.';

            throw new RuntimeException($message);
        }

        fclose($socket);
    }

    /**
     * @param resource|\IMAP\Connection $connection
     */
    private function closeConnection($connection): void
    {
        if (is_resource($connection) || is_object($connection)) {
            @imap_close($connection);
        }
    }

    private function assertImapExtensionIsAvailable(): void
    {
        if (! function_exists('imap_open')) {
            throw new RuntimeException(
                'A extensao IMAP nao esta ativa no servidor. Ative a extensao "imap" no PHP para usar a Inbox.'
            );
        }
    }

    private function buildMailboxString(EmailAccount $account): string
    {
        $host = trim((string) $account->imap_host);
        if ($host === '') {
            throw new RuntimeException('Host IMAP em falta.');
        }

        $port = (int) ($account->imap_port ?: 993);
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('Porta IMAP invalida.');
        }

        $folder = trim((string) ($account->imap_folder ?: 'INBOX'));
        $folder = str_replace('}', '', $folder);
        if ($folder === '') {
            $folder = 'INBOX';
        }

        $flags = ['imap'];
        $encryption = (string) ($account->imap_encryption ?: EmailAccount::ENCRYPTION_SSL);

        if ($encryption === EmailAccount::ENCRYPTION_SSL) {
            $flags[] = 'ssl';
        } elseif ($encryption === EmailAccount::ENCRYPTION_TLS) {
            $flags[] = 'tls';
        } else {
            $flags[] = 'notls';
        }

        return sprintf('{%s:%d/%s}%s', $host, $port, implode('/', $flags), $folder);
    }

    /**
     * @param resource|\IMAP\Connection $connection
     * @return array{
     *   uid:string,
     *   message_id:?string,
     *   folder:string,
     *   from_email:?string,
     *   from_name:?string,
     *   to_email:?string,
     *   to_name:?string,
     *   subject:?string,
     *   body_text:?string,
     *   body_html:?string,
     *   snippet:?string,
     *   received_at:?string,
     *   is_seen:bool,
     *   has_attachments:bool,
     *   raw_headers:array<string,mixed>,
     *   attachments:array<int, array{
     *     filename:string,
     *     mime_type:?string,
     *     size_bytes:?int,
     *     content_id:?string,
     *     is_inline:bool,
     *     content_base64:?string
     *   }>
     * }|null
     */
    private function mapMessageByUid($connection, int $uid, string $folder): ?array
    {
        $messageNumber = imap_msgno($connection, $uid);
        if ($messageNumber <= 0) {
            return null;
        }

        $overview = imap_fetch_overview($connection, (string) $uid, FT_UID);
        $entry = is_array($overview) && isset($overview[0]) ? $overview[0] : null;
        $headerInfo = @imap_headerinfo($connection, $messageNumber);

        $from = $this->extractAddress($headerInfo?->from[0] ?? null, (string) ($entry->from ?? ''));
        $to = $this->extractAddress($headerInfo?->to[0] ?? null, (string) ($entry->to ?? ''));
        $subject = $this->decodeMimeHeader((string) ($entry->subject ?? ''));
        $receivedAt = $this->parseDate((string) ($entry->date ?? ''));
        $structure = @imap_fetchstructure($connection, (string) $uid, FT_UID);
        $parts = $this->extractParts($connection, $uid, $structure);
        $rawHeader = @imap_fetchheader($connection, (string) $uid, FT_UID);

        $bodyText = $parts['body_text'];
        $bodyHtml = $parts['body_html'];
        $snippetSource = $bodyText;

        if ((! is_string($snippetSource) || trim($snippetSource) === '') && is_string($bodyHtml)) {
            $snippetSource = trim(strip_tags($bodyHtml));
        }

        $snippet = null;
        if (is_string($snippetSource) && trim($snippetSource) !== '') {
            $normalizedSnippet = (string) (preg_replace('/\s+/u', ' ', $snippetSource) ?: '');
            $snippet = mb_substr(trim($normalizedSnippet), 0, 220);
            $snippet = $snippet !== '' ? $snippet : null;
        }

        return [
            'uid' => (string) $uid,
            'message_id' => isset($entry->message_id) ? $this->decodeMimeHeader((string) $entry->message_id) : null,
            'folder' => $folder,
            'from_email' => $from['email'],
            'from_name' => $from['name'],
            'to_email' => $to['email'],
            'to_name' => $to['name'],
            'subject' => $subject !== '' ? $subject : null,
            'body_text' => $bodyText,
            'body_html' => $bodyHtml,
            'snippet' => $snippet,
            'received_at' => $receivedAt,
            'is_seen' => (bool) ($entry->seen ?? false),
            'has_attachments' => $parts['attachments'] !== [],
            'raw_headers' => [
                'header' => is_string($rawHeader) ? $rawHeader : null,
            ],
            'attachments' => $parts['attachments'],
        ];
    }

    /**
     * @param resource|\IMAP\Connection $connection
     * @param object|null $structure
     * @return array{
     *   body_text:?string,
     *   body_html:?string,
     *   attachments:array<int, array{
     *     filename:string,
     *     mime_type:?string,
     *     size_bytes:?int,
     *     content_id:?string,
     *     is_inline:bool,
     *     content_base64:?string
     *   }>
     * }
     */
    private function extractParts($connection, int $uid, ?object $structure, string $partNumber = ''): array
    {
        $result = [
            'body_text' => null,
            'body_html' => null,
            'attachments' => [],
        ];

        if (! $structure) {
            return $result;
        }

        if (isset($structure->parts) && is_array($structure->parts) && $structure->parts !== []) {
            foreach ($structure->parts as $index => $part) {
                $nextPartNumber = $partNumber === ''
                    ? (string) ($index + 1)
                    : $partNumber.'.'.($index + 1);

                $child = $this->extractParts($connection, $uid, $part, $nextPartNumber);

                if ($result['body_text'] === null && is_string($child['body_text'])) {
                    $result['body_text'] = $child['body_text'];
                }

                if ($result['body_html'] === null && is_string($child['body_html'])) {
                    $result['body_html'] = $child['body_html'];
                }

                if ($child['attachments'] !== []) {
                    $result['attachments'] = array_merge($result['attachments'], $child['attachments']);
                }
            }

            return $result;
        }

        $parameters = $this->extractParameters($structure);
        $filename = $parameters['filename'] ?? $parameters['name'] ?? null;
        $disposition = strtolower((string) ($structure->disposition ?? ''));
        $isAttachment = $filename !== null || in_array($disposition, ['attachment', 'inline'], true);

        if ($isAttachment) {
            $resolvedFilename = $filename ?: ('attachment-'.$uid.'-'.($partNumber !== '' ? $partNumber : '0'));
            $sizeBytes = isset($structure->bytes) ? (int) $structure->bytes : null;
            $decodedBinary = $this->fetchAttachmentBody($connection, $uid, $partNumber, $structure);
            $contentBase64 = null;

            if (
                is_string($decodedBinary)
                && $decodedBinary !== ''
                && ($sizeBytes === null || $sizeBytes <= self::MAX_ATTACHMENT_BYTES)
                && strlen($decodedBinary) <= self::MAX_ATTACHMENT_BYTES
            ) {
                $contentBase64 = base64_encode($decodedBinary);
            }

            $result['attachments'][] = [
                'filename' => $this->decodeMimeHeader($resolvedFilename),
                'mime_type' => $this->resolveMimeType($structure),
                'size_bytes' => $sizeBytes,
                'content_id' => isset($structure->id) ? trim((string) $structure->id, '<>') : null,
                'is_inline' => $disposition === 'inline',
                'content_base64' => $contentBase64,
            ];

            return $result;
        }

        $payload = $this->fetchTextPartBody($connection, $uid, $partNumber, $structure);

        if ((int) ($structure->type ?? -1) === 0) {
            $subtype = strtolower((string) ($structure->subtype ?? 'plain'));

            if ($subtype === 'plain' && trim($payload) !== '') {
                $result['body_text'] = $payload;
            } elseif ($subtype === 'html' && trim($payload) !== '') {
                $result['body_html'] = $payload;
            }
        }

        return $result;
    }

    /**
     * @param resource|\IMAP\Connection $connection
     * @param object $structure
     */
    private function fetchTextPartBody($connection, int $uid, string $partNumber, object $structure): string
    {
        $decoded = $this->fetchDecodedPartBinary($connection, $uid, $partNumber, $structure);
        if ($decoded === '') {
            return '';
        }

        $parameters = $this->extractParameters($structure);
        $charset = isset($parameters['charset']) ? strtolower((string) $parameters['charset']) : null;

        if ($charset && $charset !== 'utf-8') {
            $converted = @mb_convert_encoding($decoded, 'UTF-8', $charset);

            if (is_string($converted) && $converted !== '') {
                $decoded = $converted;
            }
        }

        return trim(str_replace(["\r\n", "\r"], "\n", $decoded));
    }

    /**
     * @param resource|\IMAP\Connection $connection
     * @param object $structure
     */
    private function fetchAttachmentBody($connection, int $uid, string $partNumber, object $structure): string
    {
        return $this->fetchDecodedPartBinary($connection, $uid, $partNumber, $structure);
    }

    /**
     * @param resource|\IMAP\Connection $connection
     * @param object $structure
     */
    private function fetchDecodedPartBinary($connection, int $uid, string $partNumber, object $structure): string
    {
        $raw = $this->fetchRawPart($connection, $uid, $partNumber);
        if ($raw === '') {
            return '';
        }

        return $this->decodeTransferEncodedPayload($raw, (int) ($structure->encoding ?? 0));
    }

    /**
     * @param resource|\IMAP\Connection $connection
     */
    private function fetchRawPart($connection, int $uid, string $partNumber): string
    {
        $flags = FT_UID | FT_PEEK;
        $raw = $partNumber === ''
            ? imap_body($connection, (string) $uid, $flags)
            : imap_fetchbody($connection, (string) $uid, $partNumber, $flags);

        return is_string($raw) ? $raw : '';
    }

    private function decodeTransferEncodedPayload(string $raw, int $encoding): string
    {
        return match ($encoding) {
            3 => base64_decode($raw, true) ?: '',
            4 => quoted_printable_decode($raw),
            default => $raw,
        };
    }

    /**
     * @param object $structure
     * @return array<string, string>
     */
    private function extractParameters(object $structure): array
    {
        $parameters = [];

        foreach (['parameters', 'dparameters'] as $property) {
            if (! isset($structure->{$property}) || ! is_array($structure->{$property})) {
                continue;
            }

            foreach ($structure->{$property} as $parameter) {
                $attribute = strtolower((string) ($parameter->attribute ?? ''));
                $value = (string) ($parameter->value ?? '');

                if ($attribute !== '' && $value !== '') {
                    $parameters[$attribute] = $this->decodeMimeHeader($value);
                }
            }
        }

        return $parameters;
    }

    private function resolveMimeType(object $structure): ?string
    {
        $type = (int) ($structure->type ?? -1);
        $subtype = strtoupper((string) ($structure->subtype ?? ''));

        if ($type < 0 || $subtype === '') {
            return null;
        }

        $major = match ($type) {
            0 => 'text',
            1 => 'multipart',
            2 => 'message',
            3 => 'application',
            4 => 'audio',
            5 => 'image',
            6 => 'video',
            default => 'application',
        };

        return $major.'/'.strtolower($subtype);
    }

    /**
     * @param object|null $address
     * @return array{email:?string,name:?string}
     */
    private function extractAddress(?object $address, string $fallback): array
    {
        $email = null;
        $name = null;

        if ($address) {
            $mailbox = isset($address->mailbox) ? trim((string) $address->mailbox) : '';
            $host = isset($address->host) ? trim((string) $address->host) : '';

            if ($mailbox !== '' && $host !== '') {
                $email = strtolower($mailbox.'@'.$host);
            }

            $personal = isset($address->personal) ? trim((string) $address->personal) : '';
            if ($personal !== '') {
                $name = $this->decodeMimeHeader($personal);
            }
        }

        if (! $email && $fallback !== '' && preg_match('/<([^>]+)>/', $fallback, $matches) === 1) {
            $email = strtolower(trim($matches[1]));
        }

        if (! $name && $fallback !== '') {
            $name = trim(preg_replace('/<[^>]+>/', '', $fallback) ?: '');
            $name = $name !== '' ? $this->decodeMimeHeader(trim($name, "\"' ")) : null;
        }

        if ($name !== null && $name === '') {
            $name = null;
        }

        return [
            'email' => $email,
            'name' => $name,
        ];
    }

    private function parseDate(string $value): ?string
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function decodeMimeHeader(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (! function_exists('imap_mime_header_decode')) {
            return $value;
        }

        $decodedParts = @imap_mime_header_decode($value);
        if (! is_array($decodedParts) || $decodedParts === []) {
            return $value;
        }

        $result = '';
        foreach ($decodedParts as $part) {
            $text = (string) ($part->text ?? '');
            $charset = strtolower((string) ($part->charset ?? 'default'));

            if ($charset !== '' && $charset !== 'default' && $charset !== 'utf-8') {
                $converted = @mb_convert_encoding($text, 'UTF-8', $charset);
                $text = is_string($converted) && $converted !== '' ? $converted : $text;
            }

            $result .= $text;
        }

        return trim($result);
    }

    private function lastImapError(string $fallback): string
    {
        $errors = function_exists('imap_errors') ? imap_errors() : null;
        if (is_array($errors) && $errors !== []) {
            return (string) end($errors);
        }

        $lastError = function_exists('imap_last_error') ? imap_last_error() : null;

        return is_string($lastError) && trim($lastError) !== '' ? $lastError : $fallback;
    }
}
